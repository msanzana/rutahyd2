# Despliegue de RutaH&D2 V2 (Laravel API) en InfinityFree

**Destino propuesto aislado:** `https://rutahyd2.free.nf/api-v2` con carpeta FTP `/htdocs/api-v2/`. No sobrescribir `/htdocs/index2.html` ni la configuración general del sitio.
Esta guía **no equivale a una publicación realizada**.

## Comprobar entorno

InfinityFree ha anunciado PHP 8.4 para hosting gratuito. PHP real de la cuenta y soporte de extensiones todavía deben comprobarse antes de activar producción. No hay acceso SSH/Composer/Artisan remoto. Puedes usar PHP 8.4 localmente para compilar.

FTP: host `ftp.infinityfree.com`, puerto `21`, usuario `if0_43460400`. Preferir FTPS explícito, con credencial almacenada **solo** en tu cliente FTP/gestor de secretos.

## Preparación local

1. Usar código rama `feature/backend-v2-es`.
2. `composer install --no-dev --prefer-dist --no-interaction`.
3. `composer dump-autoload --no-dev` sin optimizar classmap; InfinityFree puede rechazar archivos PHP >1MB.
4. `php artisan key:generate --show`: guardar el resultado **fuera de GitHub**.
5. Crear `.env` local de producción a partir de `.env.example`, con:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rutahyd2.free.nf/api-v2
FRONTEND_ORIGIN=https://DOMINIO_REAL_DEL_FRONTEND
DB_CONNECTION=mysql
DB_HOST=sql307.infinityfree.com
DB_PORT=3306
DB_DATABASE=if0_43460400_rutahyd2_es
DB_USERNAME=if0_43460400
DB_PASSWORD=TU_CONTRASENA_REAL_NUNCA_EN_GITHUB
APP_KEY=base64:CLAVE_GENERADA_DE_MANERA_SEGURA
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```
6. No subir `node_modules`, `.git` ni pruebas de desarrollo. Sí subir `vendor` generado.
7. El esquema de 34 tablas **ya existe** en la BD nueva. Crear en ella una **tabla adicional** `tokens_acceso` a través de phpMyAdmin, importando `database/espanol/12_tokens_acceso_opcional.sql` con la BD correcta seleccionada. No ejecutar scripts de `CREATE DATABASE` sobre la base nueva.
8. Crear usuario administrador mediante procedimiento seguro de hashing y `INSERT` en `usuarios` (no usar contraseñas en texto plano). En hosting no hay `artisan`. Una alternativa es generar y verificar los registros en un entorno local aislado y migrarlos con un respaldo SQL selectivo.

## Transferencia

- Crear `/htdocs/api-v2` y copiar **dentro** `app`, `bootstrap`, `config`, `public`, `routes`, `vendor`, `artisan`, `composer.json`, `.env` y carpetas `storage` con permisos de escritura.
- Copiar `deployment/infinityfree/htaccess_api_v2.txt` como `/htdocs/api-v2/.htaccess` **ANTES** de colocar `.env` o cualquier archivo sensible.
- Mantener `/htdocs/api-v2/public/.htaccess` incluido con Laravel.
- No cambiar el `.htaccess` de `/htdocs`. Aislar la API del contenido existente.
- Verificar antes de subir `.env` que `https://rutahyd2.free.nf/api-v2/app/Models/Usuario.php` y `https://rutahyd2.free.nf/api-v2/.env` NO sirven archivos internos. Si están accesibles detener el despliegue.
- Después, verificar `https://rutahyd2.free.nf/api-v2/api/estado`. No intentar iniciar sesión antes de instalar tokens y crear administrador.

## Límites del hosting

InfinityFree: no cron, no workers, sin Composer remoto, sin acceso MySQL desde PC, FTP con límite de archivos/inodos. El endpoint de preparación DTE no manda documentos. Se debe probar CORS con el dominio exacto del frontend y HTTPS.

**Seguridad:** jamás subir `.env` o credenciales a GitHub; APP_DEBUG false, HTTPS, roles y claves aleatorias. No usar un FTP no cifrado.
