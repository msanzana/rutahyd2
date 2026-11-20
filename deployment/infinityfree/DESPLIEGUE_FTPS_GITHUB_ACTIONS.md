# Despliegue del backend V2 mediante GitHub Actions y FTPS

**La instalación se realiza en DOS fases y requiere tu participación para guardar el secreto FTP en GitHub, sin enviarlo al chat.**

## Fase 1: código en staging protegido

1. Abrir GitHub `msanzana/rutahyd2` → **Settings → Secrets and variables → Actions → New repository secret**.
2. Crear secreto con nombre EXACTO **`INFINITYFREE_FTP_PASSWORD`**. El valor es la contraseña FTP de tu cuenta de InfinityFree `if0_43460400`. Puedes consultar FTP Details en InfinityFree; nunca colocarla en el código ni en chats.
3. En GitHub → **Actions** → flujo **Preparar backend RutaHD2 V2 por FTPS (staging bloqueado)** → **Run workflow** → seleccionar rama `main`. El flujo de `main` descargará y desplegará el código de `feature/backend-v2-es`.
4. El flujo compila Laravel, sube primero `/htdocs/api-v2/.htaccess` que bloquea accesos, comprueba HTTP 403 y luego transfiere `app`, `vendor`, `config`, `routes`, `public`, etc. por **FTPS explícito** (TLS).
5. La API permanece **bloqueada a propósito** hasta configurar la seguridad y activarla. No hay `.env` ni credenciales en el despliegue. Comprobar que el workflow termine con marca verde.

**Importante:** el flujo no usa `mirror --delete` ni sobrescribe los archivos de `/htdocs` fuera de `/htdocs/api-v2`. El archivo `.htaccess` guardia no se sustituye por una regla de redirección automática.

## Fase 2: configuración y activación manual

1. Validar PHP ≥8.3 en el servidor (el panel no muestra versión; requiere comprobación segura de PHP).
2. En phpMyAdmin seleccionar **`if0_43460400_rutahyd2_es`** y crear la tabla técnica `tokens_acceso` usando `database/espanol/12_tokens_acceso_opcional.sql`. Sin esto el login no funcionará.
3. Generar `APP_KEY` localmente con Laravel e instalar `.env` en la carpeta mediante canal de administración seguro. Credenciales FTP y BD nunca en GitHub. Configurar `APP_DEBUG=false`, `APP_ENV=production`, HTTPS, y `DB_DATABASE=if0_43460400_rutahyd2_es`.
4. Crear el primer administrador con hash bcrypt/argon generado de forma segura en un entorno local y guardar únicamente hash en `usuarios.hash_contrasena`.
5. Cuando se haya probado que el servidor protege `/api-v2/app/`, `/api-v2/vendor/` y `/api-v2/.env`, sustituir `/htdocs/api-v2/.htaccess` por las reglas de reescritura de `deployment/infinityfree/htaccess_api_v2.txt`.
6. Probar `https://rutahyd2.free.nf/api-v2/api/estado`, permisos API y CORS.

**No activar mientras la carga, configuración o pruebas estén incompletas.**

## Datos FTP confirmados
- Host: `ftpupload.net` (el certificado TLS corresponde a este host).
- Puerto: 21, FTP sobre TLS explícito; NO SFTP.
- Usuario: `if0_43460400`.
- Directorio de staging: `/htdocs/api-v2`.

El flujo puede fallar si InfinityFree limita conexiones FTPS desde GitHub. En ese caso descargar artifact ZIP y subir manualmente por FileZilla con **Require explicit FTP over TLS**; no recurrir a FTP sin cifrado ni desactivar validación de certificado.
