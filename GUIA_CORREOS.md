# 📧 Guía de Configuración - Sistema de Correos

## ⚠️ Problema Actual
El sistema intenta enviar correos pero **XAMPP no está configurado para enviarlos**.

## ✅ Soluciones

### Opción 1: Configurar SMTP en XAMPP (Recomendado para Gmail)

#### Paso 1: Obtener Contraseña de Aplicación de Gmail
1. Ve a: https://myaccount.google.com/apppasswords
2. Selecciona:
   - **Aplicación:** Correo
   - **Dispositivo:** Windows
3. Copia la contraseña de 16 caracteres (ej: `bzxf mgwb iasq kaon`)
4. **Nota:** Debe tener **autenticación de 2 factores habilitada** en Gmail

#### Paso 2: Editar php.ini de XAMPP
1. Abre: `C:\xampp\php\php.ini`
2. Busca la sección `[mail function]`
3. Actualiza:
```ini
[mail function]
; For Win32 only.
SMTP = smtp.gmail.com
smtp_port = 587
sendmail_from = matienda33@gmail.com

; For Unix/Linux only
;sendmail_path = "/usr/sbin/sendmail -t -i"
```

4. Guarda el archivo
5. Reinicia Apache desde el Control Panel de XAMPP

---

### Opción 2: Usar Mailtrap (Servicio Gratuito - Recomendado para Testing)

1. Ve a: https://mailtrap.io
2. Crea una cuenta gratuita
3. Crea una "Inbox" nueva
4. En "Integrations" selecciona "PHP"
5. Copia tu SMTP_HOST y SMTP_PORT
6. Actualiza `config.php`:
```php
define('SMTP_HOST', 'smtp.mailtrap.io');
define('SMTP_PORT', 2525);
define('SMTP_USER', 'tu_usuario_mailtrap');
define('SMTP_PASS', 'tu_pass_mailtrap');
```

Los correos aparecerán en tu panel de Mailtrap, **no se envían realmente** pero es perfecto para testing.

---

### Opción 3: Usar Sendinblue (SMTP Gratuito - Mejor para Producción)

1. Ve a: https://www.brevo.com (antes Sendinblue)
2. Crea una cuenta
3. Obtén tus credenciales SMTP en "Settings > SMTP"
4. Usa el SMTP que proporcionan

---

### Opción 4: Local - MailHog (Sin conexión a internet)

Para testing completamente local:
1. Descarga: https://github.com/mailhog/MailHog/releases
2. Ejecuta: `MailHog.exe`
3. Accede a: `http://localhost:1025` (interfaz web)
4. Actualiza `config.php`:
```php
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 1025);
```

---

## 🧪 Verificar Configuración

Accede a: `http://localhost:8080/tiendaAA/diagnostico_correos.php`

Este archivo te mostrará:
- ✓ Si PHP tiene función `mail()` habilitada
- ✓ Si los directorios de logs existen
- ✓ Los últimos correos enviados
- ✓ Botón para enviar una prueba

---

## 📋 Archivos Modificados

- `config.php` - Configuración SMTP (ya existe)
- `modules/mod3/api/enviar_factura.php` - Envío de comprobantes
- `modules/mod3/comprobante.php` - Modal de correo
- `modules/mod3/detalle_factura.php` - Botón de email
- `diagnostico_correos.php` - **NUEVO - Herramienta de diagnóstico**
- `api/test_email.php` - **NUEVO - API de prueba**

---

## 🔍 Troubleshooting

### Los correos no se envían pero dice que sí

**Causa:** PHP mail() no está funcionando
**Solución:** Configura SMTP (Opción 1-4 arriba)

### Gmail rechaza los correos

**Causa:** Contraseña incorrecta o cuenta sin 2FA
**Solución:**
1. Habilita 2FA en tu cuenta Google
2. Genera contraseña de aplicación
3. Usa la contraseña en `config.php`

### Los logs no se guardan

**Causa:** Permisos del directorio
**Solución:**
```bash
# En Windows (PowerShell como Admin)
mkdir logs
```

---

## 📝 Verificación Final

1. Accede a: `http://localhost:8080/tiendaAA/diagnostico_correos.php`
2. Ingresa tu correo en "Prueba de Envío"
3. Haz clic en "Enviar Prueba"
4. Revisa tu correo (spam también)
5. Si recibiste el correo: ✓ **¡Todo funciona!**
6. Si NO recibiste: Revisa "Solución de Problemas" en el diagnóstico

---

## 💡 Recomendación

Para **desarrollo local**: Usa **Mailtrap** (gratis, sin configuración complicada)
Para **producción**: Usa **Sendinblue** o **Gmail** con contraseña de aplicación

Los correos se guardarán en `logs/correos_YYYY-MM-DD.log` como respaldo independientemente de la opción elegida.
