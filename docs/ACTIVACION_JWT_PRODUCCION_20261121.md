# Ruta H&D V2 — Cierre de autenticación JWT en producción

Fecha: 21-11-2026. Procedimiento reservado al operador autorizado.

## Confirmado hasta ahora

- Base productiva: `if0_43460400_rutahyd2_es`.
- Estructura: 46 tablas, 5 roles, 1 administrador activo y hash Argon2id válido.
- DDL18 y DDL19 ya existen; **no repetirlos**. No se necesita DDL nuevo para activar el JWT.
- Frontend productivo sin demostración publicado con login obligatorio.
- Gateway `/api/v1/estado` devuelve HTTP 503 `no_habilitada`; no es una caída de Laravel interno.
- El titular confirma que importó el SQL privado de reemplazo de contraseña del administrador. La sonda verifica existencia y tipo de hash, **no la coincidencia exacta con la contraseña recibida**.
- La auditoría del archivo `.env` de esta instancia informó `MAIL_MAILER` distinto de `smtp` y campos SMTP sin configurar; el titular informa entregas de correos desde el backend. Resolver cuál proceso hace los envíos y desde qué configuración antes de dar por probada la recuperación Laravel.

## Preflight obligatorio (sin escribir en MySQL)

1. Conservar respaldo externo actual y clave de recuperación bajo custodia del titular; comprobar que puede restaurarse.
2. Verificar en `/htdocs/api-v2/.env` protegido que `APP_ENV=production`, `APP_DEBUG=false`, base correcta, `APP_KEY` y `RUTAHD_JWT_SECRET` presentes. No mostrar sus valores.
3. Verificar que Laravel responde internamente y rechaza `GET /api/v1/perfil` sin JWT con HTTP 401.
4. Confirmar por canal privado que el administrador reconoce y posee la contraseña vigente. No pegarla en archivos, issues, logs ni chat.
5. Resolver SMTP/recuperación. La entrega por otro servicio de Gmail no demuestra que Laravel tenga configurado `MAIL_MAILER=smtp` ni que sus endpoints de recuperación funcionen.
6. Confirmar que `/htdocs/api-v2/.htaccess` conserva la denegación de acceso directo y que el gateway público es el único punto de entrada.

## Activación operativa controlada

La compuerta usa **la existencia de un archivo privado**, no una variable de frontend:

`/htdocs/api-v2/storage/app/private/rutahd2_api_ready`

**No crear el marcador sin cumplir el preflight**. La creación debe realizarla el operador autorizado mediante los controles seguros del hosting, con plan de reversión. No publicar esta ruta como recurso accesible por HTTP, ni subir credenciales. El marcador nunca debe transferirse desde el frontend.

Una vez habilitado temporalmente, verificar desde navegador limpio, sin conservar tokens:

- `GET /api/v1/estado`: HTTP 200 JSON `estado=disponible`.
- `GET /api/v1/perfil` sin Bearer: HTTP 401.
- Login del administrador mediante interfaz HTTPS con la clave vigente: HTTP 200 y token Bearer.
- Con JWT: `GET /api/v1/perfil` responde con rol autorizado.
- Probar lectura de Productos, clasificaciones, Usuarios, Perfiles, Compras, Ventas, Clientes, Proveedores y Bodega, según permisos.
- Ejecutar logout y comprobar rechazo del mismo JWT (401); no guardar ni imprimir token.
- Verificar estados 403 sin escalar permisos, 422 en validaciones y 409 en conflictos, usando registros de prueba controlados, nunca datos operativos al azar.

**Ante cualquier error de seguridad/autenticación o credenciales no verificadas, retirar el marcador inmediatamente** para restaurar HTTP 503. No modificar datos para intentar “arreglar” el resultado.

## Pendientes de alcance funcional

Los módulos Compras, Ventas, Proveedores, Clientes y Bodega disponen actualmente de consultas API de solo lectura; Cuentas corrientes aún necesita definir modelo/DDL y endpoint específico. No presentar esos recursos como CRUD completo. Los cambios estructurales se entregarán exclusivamente como scripts **DDL SQL**, después de revisar el esquema real y el respaldo.

No usar Firecrawl. No registrar contraseñas, hashes, secretos JWT, app passwords ni tokens en GitHub.
