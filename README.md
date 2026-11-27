# RESMAP

Sistema web para administrar repuestos y maquinaria: catálogo público, inventario, ventas POS, solicitudes web, clientes, usuarios, notificaciones, reportes, auditoría y respaldos.

## Requisitos

- PHP 8.3 o superior
- Composer
- MySQL 8 o MariaDB, o SQLite para pruebas
- Node.js y npm solo si se desea compilar Vite
- Extensiones PHP `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` y `zip`

## Instalación local

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DatabaseSeeder
npm install
npm run build
php artisan serve
```

La aplicación queda normalmente en `http://127.0.0.1:8000`.

La migración de identificadores reemplaza los códigos de barras y de origen por códigos `RESMAP-` basados en el ID del producto y distingue los nombres duplicados. Conserva los valores anteriores en `producto_identificadores_anteriores`; los datos originales del CSV también permanecen en `productos_importados`. Los productos nuevos reciben su código automáticamente y los nombres no pueden repetirse, incluso con diferencias de mayúsculas. Antes de migrar, genera y verifica un respaldo y considera que las etiquetas y referencias externas con códigos anteriores deberán actualizarse.

Las cuentas de prueba del `UserSeeder` son `admin` y `vendedor1`. Sus contraseñas temporales aleatorias se muestran al ejecutar el seeder.

## Funciones

- `/`: catálogo público y acceso a solicitudes, con navegación superior independiente de la configuración interna.
- `/catalogo`: búsqueda de productos y creación de solicitudes web.
- `/login`: inicio de sesión por usuario o correo.
- `/password/forgot`: recuperación de contraseña.
- `/productos`: inventario paginado, etiquetas, búsqueda y exportación CSV/PDF.
- `/pos`: búsqueda por código de barras, carrito y registro de ventas.
- `/solicitudes-web`: gestión de solicitudes por administrador y vendedor.
- `/inventario/movimientos`: ajustes y trazabilidad del stock; permite registrar proveedores al ingresar una adquisición cuando se cuenta con los permisos correspondientes.
- `/proveedores`: gestión de proveedores con teléfono móvil chileno (`+56 9` y ocho dígitos), correo electrónico y página web opcional. Los proveedores anteriores sin estos datos se conservan y deben completarse al editarlos.
- `/reportes`: ventas por período, total, IVA, stock crítico y productos más vendidos.
- `/auditoria`: altas, modificaciones y eliminaciones con usuario, IP y fecha.
- `/administracion`: acceso desde «Administración» en la navegación para administrar categorías, proveedores, usuarios y backups; permite elegir la navegación superior o lateral para todos los usuarios autenticados, sin modificar la navegación pública. En móviles se mantiene el menú superior.
- `/backups`: listado y descarga de respaldos para administradores.
- `/usuarios`: creación de usuarios por administradores. La contraseña inicial aleatoria se muestra una sola vez y debe entregarse por un medio seguro.
- `/perfil`: permite cambiar la contraseña indicando la contraseña actual.
- `/categorias`: gestión de categorías mediante modales.
- `/clientes`: gestión de clientes.
- `/notificaciones`: avisos de stock crítico y solicitudes nuevas; la papelera permite restaurar o eliminar definitivamente todos los avisos propios.

Los administradores pueden configurar permisos individuales de vendedores en `/usuarios`; las cuentas existentes conservan sus accesos anteriores hasta que se guarden sus permisos. Al habilitar una acción se habilita también la visualización del módulo. El dashboard incluye indicadores de ventas y stock; los reportes incluyen ventas y stock. El POS y las cotizaciones usan datos de clientes y productos, y registrar una venta puede actualizar clientes y descontar inventario. Registrar una venta desde una cotización o servicio requiere permisos de gestión de cotizaciones y de registro de ventas. Usuarios, respaldos y actividad siguen reservados a administradores.

Después de actualizar el código, ejecuta `php artisan migrate` para agregar los permisos a las cuentas.

## Estructura principal

### Aplicación

- `app/Http/Controllers`: recibe peticiones, valida datos y coordina modelos y vistas.
- `app/Models`: representa tablas y relaciones Eloquent.
- `app/Observers/AuditoriaObserver.php`: registra automáticamente creación, edición y eliminación.
- `app/Rules/RutChileno.php`: valida formato y dígito verificador de RUT.
- `app/Notifications`: notificaciones de solicitudes y stock crítico.
- `app/Console/Commands/BackupDatabase.php`: crea backups ZIP verificados de toda la base de datos y de los archivos subidos.
- `app/Console/Commands/RestoreDatabaseBackup.php`: restaura un ZIP verificado tras crear primero un backup de seguridad.
- `app/Http/Middleware/RoleMiddleware.php`: restringe rutas por rol.
- `app/Http/Middleware/NoStoreMiddleware.php`: evita cachear pantallas internas.

### Base de datos

- `database/migrations`: crea usuarios, categorías, productos, clientes, ventas, solicitudes, movimientos y auditorías.
- `database/seeders/UserSeeder.php`: cuentas de prueba.
- `database/seeders/ProductoRealSeeder.php`: conservado para una futura carga controlada de inventario real.

### Interfaz

- `resources/views/layouts/app.blade.php`: navegación y estilos comunes de pantallas internas.
- `resources/views/productos`: inventario, formularios y etiquetas.
- `resources/views/pos`: punto de venta y lector tipo teclado USB.
- `resources/views/solicitudes`: listado, filtros y detalle de solicitudes.
- `resources/views/reportes`: reportes y auditoría.
- `resources/views/backups`: listado de respaldos descargables.
- `resources/views/auth`: login y recuperación de contraseña.
- `resources/css/app.css`, `resources/js/app.js`, `vite.config.js`: entradas de Vite/Tailwind. Las vistas actuales usan Tailwind CDN, por lo que Vite no es obligatorio para ejecutar localmente.

## Comandos operativos

```powershell
php artisan migrate
php artisan migrate:status
php artisan db:seed --class=UserSeeder
php artisan app:backup-database
php artisan app:backup-database --baseline
php artisan app:verify-database-backup database-AAAA-MM-DD_HH-mm-ss.zip
php artisan app:restore-database-backup database-AAAA-MM-DD_HH-mm-ss.zip --force
php artisan schedule:run
php artisan queue:work --queue=default --tries=3 --timeout=90
php artisan route:list
php artisan view:cache
php artisan config:cache
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

`app:backup-database` guarda ZIPs en `storage/app/private/backups`, incluye todas las tablas del esquema configurado y los archivos de `storage/app/public`, y comprueba los checksums SHA-256 antes de guardarlos. No usa JSON como alternativa: si `mysqldump` falla, el comando falla explícitamente. `--baseline` conserva una copia inicial fuera de la rotación automática; los backups diarios mantienen los últimos `BACKUP_KEEP` archivos (14 por defecto). En producción, `BACKUP_ARCHIVE_PASSWORD` es obligatorio y cifra el ZIP con AES-256; guarda esa clave fuera de los backups y consérvala para poder verificar o restaurar.

Los PDFs y envíos de cotizaciones, WhatsApp y avisos internos se procesan en la cola. En producción debe mantenerse activo `php artisan queue:work --queue=default --tries=3 --timeout=90`; el scheduler no reemplaza al worker. Supervisa `failed_jobs` y reinicia el worker después de desplegar código. Si se usa `QUEUE_CONNECTION=sync`, los trabajos vuelven a ejecutarse dentro de la petición y no se obtiene esta optimización.

El scheduler ejecuta el backup todos los días a las 02:00 y registra los fallos como críticos. La tarea del sistema operativo no se activa sola. En Windows, crea una tarea en el Programador de tareas con trigger diario a las 02:00, programa `php.exe` (obtenlo con `where.exe php`), argumentos `artisan schedule:run` y directorio de inicio `C:\laragon\www\resmap-app`; el equipo debe estar encendido. En Linux, configura la entrada cron estándar cada minuto.

Para una segunda copia, configura `BACKUP_SECONDARY_PATH` con una ruta de otro disco, un NAS o una carpeta de sincronización cloud. El comando fallará si no puede guardar la copia secundaria. Para cumplir 3-2-1, verifica que esa ruta realmente esté en un medio distinto y fuera del sitio; una carpeta local o partición del mismo disco no cuenta como copia off-site. Esta configuración usa un destino de archivos local/montado; el adaptador S3 no está instalado en este proyecto.

En `/backups`, los administradores pueden restaurar cualquier ZIP nuevo con dos confirmaciones en pantalla; la segunda requiere escribir `RESTAURAR`. El servidor vuelve a validar el nombre y la confirmación y genera un backup preventivo antes de llamar al proceso de restauración. Los backups JSON, SQL o SQLite antiguos siguen descargables, pero no se pueden restaurar desde la interfaz porque no incluyen el manifiesto/integridad del formato nuevo.

Prueba recomendada: crea un backup manual, ejecuta `php artisan app:verify-database-backup <archivo.zip>` y verifica que el resultado diga que todos los archivos coinciden. Para comprobar recuperación, restaura primero en un entorno aislado con otra base de datos, confirma conteos de productos, ventas, movimientos y solicitudes, y abre imágenes restauradas. La restauración real exige `--force` y genera primero un backup preventivo; no la pruebes contra producción.

## Cotizaciones B2B y WhatsApp

Las solicitudes públicas de repuestos crean reservas temporales; no reducen `productos.stock`. El POS y los ajustes manuales respetan esas reservas. Las etapas de cotización avanzan automáticamente según las acciones realizadas; registrar la aceptación no crea una venta. Al registrar la venta, el equipo elige entre pagada (con medio de pago) o pendiente de pago. El stock se descuenta una sola vez en ambos casos, previa comprobación de disponibilidad. No se exige adjuntar una orden de compra ni se emite un DTE real.

Las ventas con saldo pendiente se destacan y pueden filtrarse en Cotizaciones / Servicios; el seguimiento mantiene la etapa de pago abierta. Un vendedor o administrador con permisos de gestión de cotizaciones y registro de ventas puede confirmar después el pago total, sin abonos ni nuevo descuento de stock. El monto se obtiene de la venta en el servidor y se registra la fecha de confirmación. Las ventas históricas impagas mantienen su saldo pendiente. El PDF de cotización muestra producto, cantidad, precio unitario y subtotal, sin códigos internos.

El medio de pago aparece al elegir pagada y los errores de pago se muestran en español junto al campo correspondiente. Una vez generado, el resumen interno puede descargarse desde el detalle de la cotización, debajo del botón del PDF de cotización, sin ingresar a Reportes. Los nuevos resúmenes usan un encabezado y datos del cliente compactos; los PDFs ya guardados conservan su contenido original.

Crear una nueva versión invalida automáticamente todas las anteriores, aunque su fecha original de vigencia no haya vencido. Sus enlaces públicos dejan de estar disponibles; el equipo conserva el acceso al PDF histórico, marcado como invalidado y sin mostrar una vigencia activa.

Los estados de correo y WhatsApp se consultan sin recargar el detalle ni interrumpir formularios: cada 5 segundos, hasta 12 consultas, con reintento manual y aviso si continúa pendiente o falla la consulta. Los envíos pendientes se cancelan al registrar la respuesta o reemplazar la versión; los jobs omiten versiones que ya no están vigentes. Con `QUEUE_CONNECTION=database` debe mantenerse activo un worker (`php artisan queue:work --tries=3 --timeout=90`); sin él, las tareas permanecen en cola y no se contacta a SMTP ni a Meta. Procesar la cola puede enviar mensajes reales.

Al preparar una cotización se muestra el stock físico y la disponibilidad considerando las reservas de otras cotizaciones. El aviso de stock es informativo durante la edición; al crear la versión se vuelve a validar la disponibilidad porque esa operación reserva unidades. Al abrir el detalle de una cotización, sus avisos de solicitud pendientes se marcan como leídos solo para el usuario actual y permanecen en el historial.

El correo usa SMTP configurable; para Mailtrap configura sus credenciales en el `.env` local. Para WhatsApp Cloud API se requieren `WHATSAPP_ENABLED`, la versión de Graph API vigente, el identificador de número, el token, el secreto de aplicación y el token de verificación del webhook. `WHATSAPP_QUOTE_TEMPLATE` debe ser el nombre de una plantilla aprobada que tenga cuatro parámetros de texto en el cuerpo y tres botones de respuesta rápida, en este orden: aceptar, rechazar y solicitar cambios. El idioma debe coincidir con la plantilla aprobada.

En pruebas, mantén `WHATSAPP_TEST_MODE=true` y añade el destinatario autorizado únicamente a `WHATSAPP_TEST_RECIPIENTS` en el `.env` local, nunca en un archivo versionado. Un cliente solo recibe mensajes si registró consentimiento; los mensajes `STOP`, `BAJA`, `SALIR` y `CANCELAR` revocan ese consentimiento. Configura el webhook de Meta con una URL HTTPS pública; las solicitudes entrantes se validan con la firma HMAC del secreto de aplicación. La aceptación de SMTP o Meta confirma recepción del proveedor, no entrega al destinatario.

Las migraciones habituales (`php artisan migrate`) agregan cambios de esquema y no deben borrar datos. No uses `migrate:fresh` ni `migrate:refresh` en esta base: eliminan tablas/datos. Antes de desplegar migraciones, genera y verifica un backup. La copia baseline conserva el estado actual, pero no bloquea cambios de esquema ni sustituye backups diarios.

## Configuración de producción

Usa `.env.production.example` como referencia y configura valores reales:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://dominio-real`
- base de datos MySQL y contraseña única
- `SESSION_DRIVER=database`, cookies seguras y `SESSION_ENCRYPT=true`
- `MAIL_MAILER=smtp` con credenciales del administrador
- certificado TLS/HTTPS en el servidor web
- ejecución periódica del scheduler
- permisos de escritura únicamente en `storage` y `bootstrap/cache`

La impresora Niimbot y la configuración SMTP dependen de hardware, credenciales y datos que debe entregar el administrador.

## Verificación antes de entregar

```powershell
php artisan config:clear
php artisan migrate:status
php artisan test --compact
php artisan view:cache
vendor/bin/pint --dirty --format agent
```

La suite incluye pruebas de reservas y cotizaciones B2B, el momento del descuento de inventario, el envío simulado de una plantilla WhatsApp y la baja de consentimiento mediante webhook firmado, además de acceso, roles, ventas y otras áreas de la aplicación.

## Archivos que se conservaron intencionalmente

- `database/migrations/2026_09_02_000000_create_sessions_table.php` es una migración histórica; se conserva porque ya puede formar parte del historial de una instalación.
- `resources/js/app.js` es una entrada de Vite declarada en `vite.config.js`, aunque las vistas actuales cargan Tailwind por CDN.
- `AGENTS.md`, `boost.json` y `.github/copilot-instructions.md` contienen configuración de asistencia/desarrollo, no son código de la aplicación.
