# Usuarios, JWT y recuperación — código de implementación (21-11-2026)

## Situación de seguridad
La cuenta inicial de administrador se crea **solo** por consola privada mediante \`php artisan rutahd:crear-administrador correo@example.com\` o el generador SQL local ya existente. El nuevo CRUD permite crear, listar, editar, activar o desactivar **usuarios operativos** y cambiar sus perfiles no administrativos. Una cuenta con rol \`administrador\` no puede ser modificada ni eliminada desde el CRUD; tampoco se puede conceder ese rol mediante la UI. Esto evita escalada administrativa accidental. El administrador obtiene todos los permisos mediante la comprobación del rol en el backend.

No se crean cuentas productivas, no se guardan contraseñas proporcionadas por chat y no se habilita el gateway al instalar estos archivos.

## API
- \`POST /api/v1/sesion\`: JWT Bearer existente, requiere tabla \`tokens_acceso\`, secreto privado JWT y cuenta activa.
- \`GET /api/v1/usuarios\`: usuarios + roles permitidos, solo administrador autenticado.
- \`POST /api/v1/usuarios\`: invitación con contraseña aleatoria no conocida, correo de definición de contraseña.
- \`PUT /api/v1/usuarios/{id}\`: cambios de nombre, correo, rol o estado; revoca sesiones y enlaces de recuperación.
- \`DELETE /api/v1/usuarios/{id}\`: baja lógica con revocación de sesiones, sin destruir referencias históricas.
- \`POST /api/v1/recuperacion/solicitar\`: petición anónima; respuesta genérica y limitación de tasa.
- \`POST /api/v1/recuperacion/restablecer\`: token de 32 bytes, almacenado solo como SHA-256, un uso, 30 minutos, contraseña min. 12 y confirmación; revoca JWT anteriores.

## Requisitos antes de producción
1. Exportar y verificar respaldo de la base **correcta**; luego verificar SQL 12–17 y aplicar SQL 18 solo si falta.
2. Configurar \`MAIL_MAILER=smtp\`, \`MAIL_HOST\`, \`MAIL_PORT\`, \`MAIL_ENCRYPTION\`, \`MAIL_USERNAME\`, \`MAIL_PASSWORD\`, \`MAIL_FROM_ADDRESS\` y \`MAIL_FROM_NAME\` en \`.env\` **privado**, nunca en código ni CI. Comprobar en el hosting que puede entregar SMTP externo.
3. Configurar \`APP_URL=https://rutahyd2.free.nf\`, \`RUTAHD_JWT_SECRET\` y \`APP_KEY\` privados y permisos de archivos.
4. Crear el primer administrador con contraseña NUEVA, única, de 12 caracteres o más mediante consola privada, nunca mediante GitHub Actions ni chat. Si la contraseña se compartió en conversación, cambiarla.
5. Probar solicitudes inexistentes, expiradas, repetidas y correctas; comprobar respuesta genérica, enlace por correo, cambio de contraseña, invalidación de JWT y bloqueo de administración sin rol.
6. No activar \`rutahd2_api_ready\` hasta superar respaldo, autenticación, pruebas 401/403 y verificación HTTP.

## Pendiente de despliegue
Estos cambios de código NO equivalen a haber ejecutado SQL 18, generado correos de prueba, creado administrador o habilitado la API de producción.
