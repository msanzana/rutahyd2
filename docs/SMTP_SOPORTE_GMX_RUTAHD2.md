# Ruta H&D V2 — correo de soporte separado (preparación, no activado)

## Dirección prevista
- Proveedor: GMX Mail.
- Dirección candidata: `soporte.rutahyd2@gmx.com` (**sujeta a disponibilidad; no está registrada ni verificada**).
- El titular debe completar el registro con su propio teléfono y la prueba anti-bots. La cuenta se considera existente solo cuando el proveedor confirma el alta.
- El correo `soporte@rutahyd2.free.nf` **no** puede darse por existente sin un servicio de correo y control del dominio correspondiente.

## Configuración SMTP compatible con InfinityFree/Laravel
Después de verificar la cuenta en GMX y habilitar acceso desde aplicaciones externas, configurar **solo en el `.env` privado del servidor**:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=mail.gmx.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=soporte.rutahyd2@gmx.com
MAIL_PASSWORD=<CLAVE_SMTP_PRIVADA_NO_PUBLICAR>
MAIL_FROM_ADDRESS=soporte.rutahyd2@gmx.com
MAIL_FROM_NAME="Ruta H&D V2"
```

La contraseña SMTP debe configurarse directamente en el entorno privado, nunca incluirse en Github, capturas, chat o logs. Si se habilita doble factor, utilizar contraseña específica de aplicación cuando GMX lo permita. No activar el gateway API por el mero hecho de configurar correo.

## Verificaciones antes del correo de creación de contraseña
1. Confirmar que la cuenta GMX existe y puede iniciar sesión.
2. Probar desde Laravel una conexión SMTP real al puerto 587 con STARTTLS, y confirmar la recepción de un correo sin contraseña ni tokens.
3. Verificar respaldo externo recuperable de la base española de Ruta H&D.
4. Instalar migraciones 18 (recuperación) y 19 (RUT de usuario) solo si faltan.
5. Crear administrador mediante el comando seguro, enviar un enlace temporal de definición de contraseña, confirmar su recepción y validar primer JWT.
6. Solo tras esas pruebas, habilitar el gateway/API.

Documentación del proveedor:
https://support.gmx.com/pop-imap/imap/server.html
https://support.gmx.com/pop-imap/toggle.html
https://support.gmx.com/account/login-details/register.html
