# Automatización gratuita de RutaH&D2: GitHub Actions + Playwright

**Decisión del proyecto (19 de noviembre de 2026):** TinyFish queda excluido del flujo de desarrollo y despliegue de RutaH&D2. Se ha desconectado el plugin de ChatGPT por solicitud del usuario. Las referencias históricas a operaciones anteriores se mantienen solo como documentación de auditoría, no como dependencia activa.

## Herramientas principales
- **GitHub (conectado a ChatGPT):** repositorio fuente, revisión de código, commits, ramas, PR, ejecución de GitHub Actions.
- **GitHub Actions:** validación PHP/Laravel, MySQL de pruebas, generación del ZIP y despliegue manual por FTPS (secreto de GitHub).
- **Playwright (open source):** automatización de Chromium y pruebas de seguridad HTTP/API desde el runner de GitHub Actions o desde VS Code.
- **VS Code local:** edición de código y pruebas, con Git y extensiones opcionales.

**Política de costos:** no crear servicios de pago ni suscripciones. Los workflows de ejecutores estándar en repositorios públicos tienen uso gratuito según la política actual de GitHub. Si se hace privado el repositorio o se usan ejecutores/servicios especiales, comprobar límites y condiciones antes de ejecutarlos. Playwright es software de código abierto y se ejecuta en nuestro equipo o runner, sin suscripción a un agente web.

## Rutas y pruebas
- `.github/workflows/backend-v2-es.yml`: compila y prueba Laravel con MySQL 8; añade pruebas reales en Chromium con Playwright; genera ZIP con `vendor`.
- `tests/e2e/api-seguridad.spec.cjs`: confirma disponibilidad de API, versión, autenticación 401 y protección de archivos internos (en entorno local de pruebas).
- `playwright.config.cjs`: configuración sin cuentas externas.
- `.github/workflows/desplegar-staging-ftps.yml`: flujo **manual** para subida de backend a /htdocs/api-v2, sin TinyFish y con .htaccess de protección, antes de transferencia del código.
- Archivo de instalación: `deployment/infinityfree/DESPLIEGUE_FTPS_GITHUB_ACTIONS.md`.

## Probar desde VS Code

Requisitos: PHP 8.3+, Composer 2, Node.js 22+, MySQL local y las 34 tablas V2 en español. En una terminal abierta en la carpeta raíz del backend:

```bash
composer install
npm install
npx playwright install chromium
# Configurar primero .env desde .env.example y cargar el DDL local.
php artisan migrate
npm run test:e2e
```

También se puede ejecutar `npm run test:e2e:ui` para abrir la interfaz interactiva de Playwright. Playwright inicia `php artisan serve` automáticamente cuando no hay un servidor escuchando en el puerto 8000.

## Publicar en InfinityFree

1. Guardar en **GitHub Settings > Secrets and variables > Actions** el secreto `INFINITYFREE_FTP_PASSWORD`. Nunca colocarlo en archivos, mensajes ni commits.
2. Ejecutar manualmente desde `main` el workflow **Preparar backend RutaHD2 V2 por FTPS (staging bloqueado)**; ese workflow obtiene código de `feature/backend-v2-es`.
3. El flujo coloca un archivo `.htaccess` de denegación total y exige HTTP 403 antes de copiar fuentes con FTPS cifrado. No sube `.env` ni activa la API.
4. La activación requiere configuración privada, tabla `tokens_acceso`, administrador seguro, comprobación de PHP y pruebas de seguridad.

**Estado a fecha de esta decisión:** base V2 española creada (34 tablas). Backend construido y probado localmente en GitHub Actions; publicación FTPS aún NO confirmada. No confundir preparación del workflow con un despliegue ya realizado.

## Política explícita

- No volver a utilizar TinyFish para este proyecto.
- No usar servicios que cobren por navegador/agente web.
- Probar el backend antes de activar rutas públicas.
- No cambiar la base de datos V1 ni sobrescribir archivos del sitio existente.
- Mantener datos personales, contraseñas y claves fuera de GitHub y de los reportes Playwright.
