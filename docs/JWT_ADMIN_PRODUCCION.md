# Habilitación JWT Bearer y administrador Ruta H&D2

**Estado de seguridad:** el código JWT está probado en GitHub Actions. La base de producción `if0_43460400_rutahyd2_es` debe comprobarse y respaldarse; la API no puede abrirse hasta completar la configuración privada. Este documento NO incluye contraseñas reales, sus hashes ni claves de firma.

## Autenticación implementada
- Contraseñas: `Hash::make` / `Hash::check` de Laravel; usar `HASH_DRIVER=argon2id` cuando PHP lo admita, o bcrypt.
- JWT: dependencia Composer `firebase/php-jwt` (HS256), duración **3600 segundos**, `sub`, `jti`, `iat`, `nbf`, `exp`, `iss` y `aud`.
- Firma: variable privada `RUTAHD_JWT_SECRET` de al menos 32 bytes de entropía suficiente, independiente de `APP_KEY` y de la contraseña del administrador.
- Revocación: tabla `tokens_acceso` existente, guardando solo el SHA-256 del identificador aleatorio `jti`. La salida de sesión elimina el registro.
- Contrato: `POST /api/v1/sesion` devuelve `token_type: Bearer`, `token`, `expires_in`, `vence_en` y `usuario` con permisos; `Authorization: Bearer <jwt>` en operaciones autenticadas, `DELETE /api/v1/sesion` revoca.
- Los permisos se vuelven a consultar en MySQL para cada petición; una revocación de rol no espera a que expire el JWT.
- El navegador conserva el JWT solo en memoria, no en `localStorage`, `sessionStorage` ni cookies.

## Base de datos requerida
La tabla `tokens_acceso` del SQL 12 sirve **tanto para tokens antiguos como para JWT**; no hace falta otro `ALTER TABLE` para el nuevo formato. El backend JWT no acepta tokens heredados. Las migraciones SQL 12, 13, 14 y 16 deben ejecutarse **solo si realmente faltan**.

1. Iniciar sesión personalmente en InfinityFree → phpMyAdmin y seleccionar únicamente `if0_43460400_rutahyd2_es` (no la base inglesa ni `db_sii_dte`).
2. Exportar copia completa y verificar que puede abrirse y contiene tablas y datos.
3. Ejecutar `15_preflight_catalogos_remoto_solo_lectura.sql` y revisar salida; si hay tablas parciales, detenerse y corregir sin repetir `ALTER TABLE`.
4. Instalar únicamente las migraciones faltantes: 12 tokens, 13 catálogos, 14 columnas de productos, 16 permisos. SQL 14 **no es idempotente**.
5. Ejecutar `17_verificar_clasificacion_y_perfiles_lectura.sql`. Resultado esperado para instalación completa: 45 tablas, 10 catálogos, 10 columnas nuevas, 10 claves foráneas, 20 permisos y `categoria_id` heredado preservado.

## Crear el usuario administrador sin exponer su contraseña
- **No** usar el JWT para «encriptar» contraseñas. JWT firma tokens, no cifra secretos.
- Generar el hash de forma local con `php tools/generar_admin_sql.php` y proporcionar el correo, nombre y contraseña exclusivamente en la consola privada con eco desactivado.
- Importar el `INSERT` resultante únicamente mediante sesión propia y autenticada de phpMyAdmin, una vez corroborada la tabla `roles` con `codigo='administrador'`.
- La salida SQL incluye un **verificador secreto** (hash); no enviarla a GitHub, ChatGPT, correo, screenshots ni reportes de CI. Destruirla después de importarla.
- Si el usuario ya existe, el generador no lo sobrescribe. Para rotar credenciales de usuario existente, realizar procedimiento de cambio separado y autenticado.
- Puesto que la contraseña prevista fue escrita en un chat, **se recomienda cambiarla por una nueva y exclusiva antes de usarla en producción**.

## Configuración privada de Laravel
Configurar `/htdocs/api-v2/.env` únicamente por canal administrativo seguro, detrás de `.htaccess` que ya bloquea el código privado; no subirlo a GitHub:
```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rutahyd2.free.nf
FRONTEND_ORIGIN=https://rutahyd2.free.nf
DB_CONNECTION=mysql
DB_HOST=sql307.infinityfree.com
DB_PORT=3306
DB_DATABASE=if0_43460400_rutahyd2_es
DB_USERNAME=USUARIO_PRIVADO_MYSQL
DB_PASSWORD=SECRETO_PRIVADO_MYSQL
APP_KEY=base64:CLAVE_ALEATORIA_PRIVADA
RUTAHD_JWT_SECRET=CLAVE_JWT_ALEATORIA_Y_DIFERENTE
HASH_DRIVER=argon2id
```
Confirmar que el hosting ofrece Argon2id. Si no, configurar bcrypt y validar con `Hash::check` en el entorno real. No fijar `HASH_DRIVER` a uno incompatible con el hash importado.

## Activación protegida
El gateway `/htdocs/api/index.php` está construido para devolver HTTP 503 hasta que existan tanto `.env` como el marcador **`/htdocs/api-v2/storage/app/private/rutahd2_api_ready`**. No crear el marcador hasta verificar PHP real, configuración, seguridad de la carpeta privada, migraciones, roles, credenciales, `/api/estado`, 401 sin JWT, 401 ante firma incorrecta, 403 sin rol y recuperación de MySQL.

Si falla una prueba después de activar, retirar inmediatamente el marcador y revisar los registros privados: el endpoint volverá al modo no habilitado, sin despublicar el frontend.

## Pruebas reproducibles
- GitHub Actions backend: MySQL efímero, firma HS256 válida, invalidación de firma, JWT vencido, token antiguo rechazado, cierre de sesión y revocación, roles, CRUD y FKs.
- GitHub Actions frontend: navegador Chromium, inicio de sesión HTTP real con Laravel y MySQL efímero, catálogo Rubros y permisos de lectura/escritura.
- **Ninguna prueba de CI accede a `if0_43460400_rutahyd2_es`.** Para la instalación productiva falta acceso autorizado a phpMyAdmin y a la configuración privada.
