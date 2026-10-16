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

Para cargar el inventario real desde el CSV incluido:

```powershell
php artisan db:seed --class=ProductoRealSeeder
```

Las cuentas de prueba del `UserSeeder` son `admin` y `vendedor1`, ambas con contraseña `password123`.

## Funciones

- `/`: catálogo público y acceso a solicitudes.
- `/catalogo`: búsqueda de productos y creación de solicitudes web.
- `/login`: inicio de sesión por usuario o correo.
- `/password/forgot`: recuperación de contraseña.
- `/productos`: inventario paginado, etiquetas, búsqueda y exportación CSV/PDF.
- `/pos`: búsqueda por código de barras, carrito y registro de ventas.
- `/solicitudes-web`: gestión de solicitudes por administrador y vendedor.
- `/inventario/movimientos`: ajustes y trazabilidad del stock para administradores.
- `/reportes`: ventas por período, total, IVA, stock crítico y productos más vendidos.
- `/auditoria`: altas, modificaciones y eliminaciones con usuario, IP y fecha.
- `/backups`: listado y descarga de respaldos para administradores.
- `/usuarios`: creación de usuarios por administradores.
- `/categorias`: gestión de categorías mediante modales.
- `/clientes`: gestión de clientes.
- `/notificaciones`: avisos de stock crítico y solicitudes nuevas.

## Estructura principal

### Aplicación

- `app/Http/Controllers`: recibe peticiones, valida datos y coordina modelos y vistas.
- `app/Models`: representa tablas y relaciones Eloquent.
- `app/Observers/AuditoriaObserver.php`: registra automáticamente creación, edición y eliminación.
- `app/Rules/RutChileno.php`: valida formato y dígito verificador de RUT.
- `app/Notifications`: notificaciones de solicitudes y stock crítico.
- `app/Console/Commands/BackupDatabase.php`: crea backups SQLite o MySQL; usa JSON como fallback si `mysqldump` no está disponible.
- `app/Console/Commands/RestoreDatabaseBackup.php`: restaura un JSON con confirmación explícita.
- `app/Http/Middleware/RoleMiddleware.php`: restringe rutas por rol.
- `app/Http/Middleware/NoStoreMiddleware.php`: evita cachear pantallas internas.

### Base de datos

- `database/migrations`: crea usuarios, categorías, productos, clientes, ventas, solicitudes, movimientos y auditorías.
- `database/seeders/UserSeeder.php`: cuentas de prueba.
- `database/seeders/ProductoRealSeeder.php`: importa el CSV real y conserva datos de origen.
- `database/seeders/csv/LISTADO DE PRECIOS RESMAP.csv`: fuente del inventario importado.

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
php artisan db:seed --class=ProductoRealSeeder
php artisan app:backup-database
php artisan app:restore-database-backup database-AAAA-MM-DD_HH-mm-ss.json --force
php artisan schedule:run
php artisan route:list
php artisan view:cache
php artisan config:cache
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

`app:backup-database` guarda archivos en `storage/app/private/backups` y conserva la cantidad indicada por `BACKUP_KEEP`, cuyo valor recomendado es 14. `app:restore-database-backup` reemplaza datos de tablas de aplicación, por eso exige `--force`; no restaura migraciones, sesiones ni cachés.

El scheduler ejecuta el backup todos los días a las 02:00. En Windows hay que programar `php artisan schedule:run` cada minuto en el Programador de tareas. En Linux se usa una entrada cron cada minuto.

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

La integración SII/DTE, la impresora Niimbot y la configuración SMTP dependen de credenciales, hardware y datos que debe entregar el administrador.

## Verificación antes de entregar

```powershell
php artisan config:clear
php artisan migrate:status
php artisan test --compact
php artisan view:cache
vendor/bin/pint --dirty --format agent
```

La suite actual cubre acceso, roles, validación de categorías y ajustes de inventario. Antes de producción conviene ampliar pruebas de ventas, solicitudes, restauración de backups, recuperación de contraseña y exportaciones.

## Archivos que se conservaron intencionalmente

- `database/migrations/2026_09_02_000000_create_sessions_table.php` es una migración histórica; se conserva porque ya puede formar parte del historial de una instalación.
- `resources/js/app.js` es una entrada de Vite declarada en `vite.config.js`, aunque las vistas actuales cargan Tailwind por CDN.
- `AGENTS.md`, `CLAUDE.md` y `boost.json` contienen configuración de asistencia/desarrollo, no son código de la aplicación.
<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
