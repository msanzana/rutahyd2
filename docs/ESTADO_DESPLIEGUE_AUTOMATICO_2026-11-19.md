# RutaH&D2 V2 — estado del despliegue automatizado
**Auditoría:** 2026-11-19 (America/Santiago). Este archivo describe hechos verificados, no acciones supuestas.

## Backend validado
- Rama fuente: `feature/backend-v2-es`, commit de compilación comprobado `78d6828056d32bb961b2ab2d329f7ae3e39fcecc`.
- GitHub Actions: `https://github.com/msanzana/rutahyd2/actions/runs/44641026448`: resultado `success`, **6/6 pruebas Playwright Chromium aprobadas** y verificaciones PHP, Composer, MySQL 8 y modelos Eloquent correctas.
- ZIP con `vendor`: artefacto `rutahyd2-v2-backend-con-vendor` adjunto a esa ejecución.

## InfinityFree (no asumir despliegue completado)
- Base de datos comercial: `if0_43460400_rutahyd2_es`, **34 tablas en español**, instaladas y verificadas previamente mediante phpMyAdmin.
- Carpeta `api-v2`: intentos previos con navegador no confirmaron una ubicación única ni protección `.htaccess`; por eso no se cargaron los archivos de Laravel.
- **Ningún backend Laravel activado**, ningún DTE emitido, ningún nuevo dato comercial insertado.
- La base inglesa `if0_43460400_rutahyd2` se mantiene separada, sin cambios.

## Ejecución automática desde ChatGPT, sin TinyFish
- Workflow de GitHub: `.github/workflows/desplegar-staging-ftps.yml` en la rama `main`.
- ChatGPT puede iniciar ese workflow **mediante un commit a** `deployment/solicitudes/lanzar-backend-v2.txt` en `main`; no hace falta un navegador ni pulsar Run workflow.
- Primera ejecución automática: `https://github.com/msanzana/rutahyd2/actions/runs/44643000974`, resultado `failure`.
- Causa verificada por logs: **no existe el secreto GitHub Actions `INFINITYFREE_FTP_PASSWORD`**. Por diseño, el job paró antes de subir ningún archivo por FTPS.
- El flujo comprueba disponibilidad de secreto, compila y coloca primero una denegación HTTP `403` en `/htdocs/api-v2/.htaccess`, y solo entonces copia el backend con `vendor`. No modifica la base de datos ni activa Laravel.

## Una acción imprescindible del titular
Para autorizar el uso del FTP de InfinityFree desde GitHub sin exponer su contraseña:
1. Visitar `https://github.com/msanzana/rutahyd2/settings/secrets/actions`.
2. Crear el secreto `INFINITYFREE_FTP_PASSWORD` con la contraseña FTP que proporciona InfinityFree. **Nunca compartirla por chat ni incluirla en el repositorio.**
3. Una vez guardado, ChatGPT puede volver a activar el mismo workflow con otro commit al archivo de solicitud.
4. Confirmar `success` y protección de código antes de instalar `.env` o habilitar el sitio.

## Migraciones de MySQL, pendientes de acceso
- Para instalación inicial local: `database/espanol/01_esquema_completo_34_tablas_es.sql`.
- Para autenticación API aún falta verificar/crear `tokens_acceso`, SQL versionado `database/espanol/12_tokens_acceso_opcional.sql`.
- InfinityFree **no ofrece MySQL externo desde GitHub Actions** en hosting gratuito. Su administración remota necesita un canal autenticado PHP/phpMyAdmin; el FTP no ejecuta SQL y Playwright no obtiene acceso a una cuenta privada sin credenciales.
- No se ha realizado una nueva modificación de MySQL en este intento. Antes de actualizar se requiere backup, comprobar tablas y aplicar solamente migraciones versionadas no destructivas.
- Tampoco se dispone de `.env` de producción ni contraseña MySQL segura para conectar Laravel con esa BD. Preparar esos secretos por canal seguro, nunca en repositorios.

## Reglas
- No utilizar TinyFish.
- GitHub Actions para compilación, transferencia FTPS, validación y disparo de tareas; Playwright Chromium para pruebas automáticas.
- No subir código PHP sin confirmar protección y permisos; no abrir la API antes de configurar `.env`, autenticación y respaldos.
- Si faltan secretos, detenerse y registrar un error explícito sin alterar InfinityFree.
