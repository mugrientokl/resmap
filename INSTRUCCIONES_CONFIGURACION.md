# 🔧 Instrucciones de Configuración - Email y Dashboard

## ✅ Cambios Realizados

### 1. **Kits y Sets Restaurados** ✓
- Se creó un seeder `KitsSetSeeder.php` con 8 productos (KIT-001 a JUEGO-002)
- Ahora se crean automáticamente cuando ejecutas `php artisan migrate:fresh --seed`
- Categoría: **KITS Y SETS**

### 2. **Dashboard Colores Rojizos** ✓
- Paleta de colores actualizada a rojo (acordes al sistema)
- Colores principales:
  - Rojo oscuro: `#C41E3A`
  - Rojo medio: `#E63946`
  - Rojo claro degradado
  - Negros complementarios: `#A4161A`, `#8B0000`

### 3. **Notificaciones por Email Configuradas** ✓
- Creada notificación `SolicitudWebRecibida` con:
  - Envío automático a admins cuando se recibe solicitud
  - Detalles completos: tipo, cliente, email, teléfono, descripción
  - Botón directo a la solicitud
  
- Creada notificación `ResetPasswordNotification` para:
  - Restablecer contraseña por email
  - Enlace válido por 60 minutos
  - Templating personalizado

### 4. **Configuración SMTP (MAILTRAP)** ✓
- .env actualizado para usar SMTP real
- Por defecto: **Mailtrap** (perfecto para desarrollo y testing)

---

## 📧 Configurar Correos Reales (IMPORTANTE)

Actualmente el .env está configurado con Mailtrap como ejemplo. Necesitas configurar un servicio real.

### **Opción 1: Usar Mailtrap (RECOMENDADO para Testing)**

1. Ve a https://mailtrap.io
2. Regístrate (es gratuito)
3. Crea una bandeja de entrada
4. Ve a "Integrations" → "Laravel"
5. Copia las credenciales y actualiza tu `.env`:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=live.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=tu_username_mailtrap
MAIL_PASSWORD=tu_password_mailtrap
MAIL_FROM_ADDRESS="admin@resmap.cl"
MAIL_FROM_NAME="RESMAP"
```

### **Opción 2: Usar Gmail**

1. Crea una contraseña de aplicación en tu cuenta de Google
2. Actualiza `.env`:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=gabriielalexiss@gmail.com
MAIL_PASSWORD=tu_contraseña_app_google
MAIL_FROM_ADDRESS="gabriielalexiss@gmail.com"
MAIL_FROM_NAME="RESMAP"
```

### **Opción 3: Usar SendGrid**

1. Regístrate en https://sendgrid.com
2. Obtén tu API key
3. Actualiza `.env`:

```env
MAIL_MAILER=sendgrid
SENDGRID_API_KEY=tu_sendgrid_api_key
MAIL_FROM_ADDRESS="admin@resmap.cl"
MAIL_FROM_NAME="RESMAP"
```

---

## 🧪 Probar que los Correos Funcionan

### **Opción 1: Tinker (CLI)**

```bash
php artisan tinker
```

Luego ejecuta:

```php
$user = App\Models\User::first();
$user->notify(new App\Notifications\ResetPasswordNotification('test-token'));
```

### **Opción 2: Verificar en tu Aplicación**

1. Ve a `/password/forgot`
2. Ingresa tu email
3. Deberías recibir un email con enlace de reset

---

## 🔐 Reset de Contraseña - Cómo Funciona

1. Usuario solicita reset en `/password/forgot`
2. Recibe email con enlace válido por 60 minutos
3. Hace clic en el enlace
4. Se abre formulario en `/password/reset/{token}`
5. Ingresa nueva contraseña (mínimo 8 caracteres)
6. ¡Listo! Contraseña actualizada

**Nota:** El email se enviará usando el mailer configurado en `.env`

---

## 📬 Solicitudes Web - Flujo de Notificaciones

Cuando un cliente envía una solicitud web:

1. ✉️ Todos los usuarios con rol **Administrador** reciben email con:
   - Detalles de la solicitud
   - Información del cliente
   - Descripción completa
   - Botón para ver solicitud

2. 🔔 Se guarda notificación en DB (visible en `/notificaciones`)

3. 📊 La solicitud aparece en `/solicitudes-web`

---

## 🎨 Paleta de Colores Dashboard

```css
Rojo oscuro:  #C41E3A
Rojo medio:   #E63946
Rojo claro:   #D62828
Marrón:       #A4161A
Negro:        #8B0000
```

---

## 📝 Verificar Cambios

### Comprobar Kits y Sets en BD:
```bash
php artisan migrate:fresh --seed
```

### Comprobar Colores:
- Abre Dashboard
- Los colores deben ser rojizos, no RGB

### Comprobar Correos:
- Verifica `.env` está configurado correctamente
- Intenta reset de contraseña
- Verifica que mail llegue a tu bandeja

---

## 🆘 Solución de Problemas

### "SMTP Error 500"
→ Verifica credenciales en `.env`
→ Verifica que el puerto SMTP esté abierto (587 para TLS)

### "Correos no llegan"
→ Revisa configuración de `.env`
→ Verifica carpeta de SPAM
→ En Mailtrap, ve a "Live" para ver emails en desarrollo

### "Dashboard se ve con colores RGB"
→ Limpia cache: `php artisan cache:clear`
→ Recarga la página (Ctrl+Shift+R)

---

¡Listo! Ahora tu sistema tiene:
✅ Kits y Sets desde el inicio
✅ Dashboard con colores acordes
✅ Notificaciones por email
✅ Reset de contraseña funcional
