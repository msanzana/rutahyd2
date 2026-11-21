# Ruta H&D V2 — Cierre de autenticación JWT en producción

Fecha: 21-11-2026. Procedimiento reservado al operador autorizado.

## Confirmado hasta ahora

- Base productiva: `if0_43460400_rutahyd2_es`.
- Estructura: 46 tablas, 5 roles, 1 administrador activo y hash Argon2id válido.
- DDL18 y DDL19 ya existen; **no repetirlos**. No se necesita DDL nuevo para activar el JWT.
- Frontend productivo sin demostración publicado con login obligatorio.
- **Gateway público ACTIVADO**: la ejecución [44963708303](https://github.com/msanzana/rutahyd2/actions/runs/44963708303) confirmó `/api/v1/estado` HTTP 200 `disponible` y cuatro verificaciones de acceso anónimo/JWT inválido con HTTP 401. El marcador se confirmó por lectura FTPS byte a byte.
- El titular confirmó la importación del SQL privado del administrador y el resultado **`cuentas_actualizadas=1`**. La sonda verifica existencia y tipo de hash, **no la coincidencia exacta con la contraseña recibida**.
- **SMTP ya instalado y confirmado**. Tras corregir el Repository secret, [44962575559](https://github.com/msanzana/rutahyd2/actions/runs/44962575559) instaló y comparó el `.env` por FTPS. La [auditoría privada 44962636619](https://github.com/msanzana/rutahyd2/actions/runs/44962636619) confirmó todos los campos SMTP, la base y el JWT; la [auditoría PHP 44962780428](https://github.com/msanzana/rutahyd2/actions/runs/44962780428) comprobó `smtp_config_laravel_efectiva=true`. **No hay aún prueba del envío y recepción real desde Laravel**; no confundir configuración con entrega.

## Preflight obligatorio (sin escribir en MySQL)

1. Conservar respaldo externo actual y clave de recuperación bajo custodia del titular; comprobar que puede restaurarse.
2. Verificar en `/htdocs/api-v2/.env` protegido que `APP_ENV=production`, `APP_DEBUG=false`, base correcta, `APP_KEY` y `RUTAHD_JWT_SECRET` presentes. No mostrar sus valores.
3. Verificar que Laravel responde internamente y rechaza `GET /api/v1/perfil` sin JWT con HTTP 401.
4. Confirmar por canal privado que el administrador reconoce y posee la contraseña vigente. No pegarla en archivos, issues, logs ni chat.
5. SMTP configurado y cargado por Laravel: confirmado. Falta una prueba controlada de envío y recepción real desde Laravel y del flujo de recuperación; nunca se publican contraseñas ni enlaces de recuperación.
6. Confirmar que `/htdocs/api-v2/.htaccess` conserva la denegación de acceso directo y que el gateway público es el único punto de entrada.

## Activación operativa controlada

La compuerta usa **la existencia de un archivo privado**, no una variable de frontend:

`/htdocs/api-v2/storage/app/private/rutahd2_api_ready`

**Marcador ya creado y verificado** por workflow automatizado de producción con rollback ante fallos. No volver a crearlo. El acceso con credenciales administradoras reales aún debe probarse desde la interfaz del titular; no afirmar login exitoso antes de esa prueba. No publicar esta ruta como recurso accesible por HTTP, ni subir credenciales. El marcador nunca debe transferirse desde el frontend.

La activación HTTP/401 ya fue verificada. Para cerrar el proceso, el titular debe verificar desde navegador limpio, sin compartir ni conservar tokens:

- `GET /api/v1/estado`: HTTP 200 JSON `estado=disponible`.
- `GET /api/v1/perfil` sin Bearer: HTTP 401.
- Login del administrador mediante interfaz HTTPS con la clave vigente: HTTP 200 y token Bearer.
- Con JWT: `GET /api/v1/perfil` responde con rol autorizado.
- Probar lectura de Productos, clasificaciones, Usuarios, Perfiles, Compras, Ventas, Clientes, Proveedores y Bodega, según permisos.
- Ejecutar logout y comprobar rechazo del mismo JWT (401); no guardar ni imprimir token.
- Verificar estados 403 sin escalar permisos, 422 en validaciones y 409 en conflictos, usando registros de prueba controlados, nunca datos operativos al azar.

**Si se detecta un fallo real de seguridad en las protecciones**, retirar el marcador privado para restaurar HTTP 503 y auditar. Un error de contraseña del usuario, por sí solo, no justifica alterar la base de datos ni retirar automáticamente el gateway; diagnosticar el HTTP y la cuenta antes de cualquier cambio.

## Pendientes de alcance funcional

Los módulos Compras, Ventas, Proveedores, Clientes y Bodega disponen actualmente de consultas API de solo lectura; Cuentas corrientes aún necesita definir modelo/DDL y endpoint específico. No presentar esos recursos como CRUD completo. Los cambios estructurales se entregarán exclusivamente como scripts **DDL SQL**, después de revisar el esquema real y el respaldo.

No usar Firecrawl. No registrar contraseñas, hashes, secretos JWT, app passwords ni tokens en GitHub.
