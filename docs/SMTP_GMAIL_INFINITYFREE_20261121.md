# SMTP Gmail para Ruta H&D V2 — configuración segura

**Cuenta emisora:** neo.hiper.xavier@gmail.com
**Estado:** código preparado; la cuenta Gmail todavía NO está configurada como SMTP de producción.

## Paso privado del titular (no compartir contraseñas en ChatGPT)

1. Abrir GitHub: https://github.com/msanzana/rutahyd2/settings/secrets/actions
2. Elegir New repository secret.
3. Nombre EXACTO: RUTAHD_GMAIL_APP_PASSWORD
4. Valor: los 16 caracteres de la contraseña de aplicación de Google (se pueden incluir espacios de agrupación). No utilizar la contraseña normal de Gmail.

La autorización del conector Gmail no proporciona acceso a la contraseña de aplicación. No se pueden recuperar secretos de Google desde ChatGPT.

## Automatización preparada

Workflow: .github/workflows/configurar-smtp-gmail-20261121.yml

Lee INFINITYFREE_FTP_PASSWORD y RUTAHD_GMAIL_APP_PASSWORD desde GitHub Actions Secrets. Respalda el archivo privado .env desde /htdocs/api-v2/.env, comprueba protección de acceso directo y ausencia del marcador rutahd2_api_ready. Actualiza solamente variables de correo conservando la base de datos, APP_KEY, JWT y demás ajustes.

Valores no sensibles:

- MAIL_MAILER=smtp
- MAIL_HOST=smtp.gmail.com
- MAIL_PORT=587
- MAIL_ENCRYPTION=tls
- MAIL_USERNAME=neo.hiper.xavier@gmail.com
- MAIL_FROM_ADDRESS=neo.hiper.xavier@gmail.com
- MAIL_FROM_NAME=Ruta H&D V2
- MAIL_PASSWORD=secreto privado RUTAHD_GMAIL_APP_PASSWORD, NUNCA EN REPOSITORIO

El workflow verifica la subida mediante una segunda descarga y comparacion de bytes; en caso de fallo intenta restaurar el .env previo. Nunca imprime ni sube a GitHub el contenido privado.

**No activa la API, no modifica MariaDB y no crea cuentas.** Una vez configurado, todavía falta probar desde el servidor InfinityFree el envío SMTP y confirmar recepción antes de crear el administrador o activar el login.

Referencia Google: https://support.google.com/mail/answer/185833
Referencia InfinityFree: https://forum.infinityfree.com/t/which-is-the-best-smtp-server-to-send-emails/113413
