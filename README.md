# RutaH&D2 V2 — Backend Laravel en español

Backend independiente de Ruta H&D V1. Código preparado para la base **`if0_43460400_rutahyd2_es`** de InfinityFree (34 tablas comerciales en español). Usa Laravel 13, patrón Repository y PHP >=8.3.

**Estado:** código versionado y compilable; no se presupone despliegue en Internet. La aplicación de producción V1 sigue separada. No se incluyen contraseñas ni `.env` real.

## Automatización gratuita sin agentes web de pago

RutaH&D2 usa **GitHub Actions + Playwright** como canal principal de compilación, pruebas en Chromium y despliegue FTP/FTPS aislado. No depende de TinyFish ni de sus créditos. Guía: [docs/AUTOMATIZACION_GRATUITA.md](docs/AUTOMATIZACION_GRATUITA.md).

Desde VS Code: `npm install`, `npx playwright install chromium`, `npm run test:e2e`. Es necesario configurar el backend Laravel local (Composer, `.env` y, para pruebas completas, MySQL).

## Arquitectura

- 34 modelos Eloquent con tablas, atributos, relaciones y marcas de tiempo **en español**.
- 34 interfaces + repositorios (32 repositorios CRUD por `id`; 2 claves compuestas: `roles_permisos` y `saldos_inventario`).
- Inyección de dependencias: `App\Providers\AppServiceProvider`.
- API JSON autenticada con token aleatorio de 256 bits (hash SHA-256 en `tokens_acceso`); tokens de 12 horas, cierre de sesión, control de permisos.
- Notas de venta, pagos, inventario casa ↔ vehículo, ventas y preparación DTE.
- Sin emisión real al SII aún. `PreparadorDte` traduce desde el esquema SQL español al JSON en inglés del servidor DTE inspeccionado.

## Descarga

1. En GitHub seleccionar la rama `feature/backend-v2-es` y **Code > Download ZIP**.
2. Descomprimir y abrir esa carpeta en VS Code.
3. También se puede descargar el ZIP de producción (con dependencias) desde los **Artifacts** de GitHub Actions, cuando el workflow de esta rama termine satisfactoriamente.

## Ejecución en Windows / Linux (local)

Requisitos: PHP 8.3 o 8.4, extensiones pdo_mysql / mbstring / openssl / fileinfo, Composer 2, servidor MySQL o MariaDB.

```bash
composer install
cp .env.example .env
# Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

Editar `.env` para la BD local:
```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_HOST=127.0.0.1
DB_DATABASE=rutahyd2_es_local
DB_USERNAME=root
DB_PASSWORD=REEMPLAZAR_SOLO_LOCALMENTE
```

Antes de migrar, instalar las 34 tablas con `database/espanol/01_esquema_completo_34_tablas_es.sql` y, opcionalmente, semillas `database/espanol/05_semillas_es.sql`. El archivo DDL ya trae `CREATE DATABASE rutahyd2_es_local`.

Crear la **35ª tabla técnica de autenticación**, `tokens_acceso`:
```bash
php artisan migrate
```

Luego, con las semillas ya aplicadas, generar una cuenta local:
```bash
php artisan rutahd:crear-administrador tu-correo@ejemplo.cl
```
Se solicitará el nombre y la contraseña mediante la terminal, sin incluirla en GitHub.

Iniciar servidor:
```bash
php artisan serve
```

Comprobar `http://127.0.0.1:8000/api/estado`. Inicio de sesión:
```http
POST /api/v1/sesion
Content-Type: application/json

{"correo_electronico":"tu-correo@ejemplo.cl","contrasena":"TU_CONTRASENA"}
```
El token retornado se usa como `Authorization: Bearer <token>`. Para cerrarlo `DELETE /api/v1/sesion`.

## Endpoints disponibles

| Método y ruta | Permiso | Función |
| --- | --- | --- |
| GET /api/estado | Público | Confirmación de proceso Laravel (no verifica DB) |
| POST /api/v1/sesion | Público, limitado | Obtener token |
| GET /api/v1/perfil | Token | Perfil básico |
| DELETE /api/v1/sesion | Token | Cerrar sesión |
| GET /api/v1/recursos/{recurso} | Token + permiso | Catálogos y tablas permitidas, lectura paginada |
| GET /api/v1/recursos/{recurso}/{id} | Token + permiso | Detalle por PK |
| POST /api/v1/ventas | ventas.gestionar | Guardar venta con producto_id |
| GET /api/v1/ventas/{id}/detalles | ventas.ver | Venta con productos |
| POST /api/v1/ventas/{id}/preparar-dte | ventas.gestionar | Guardar y mostrar JSON de DTE sin emitir |
| POST /api/v1/notas-venta | ventas.gestionar | Registrar nota |
| POST /api/v1/notas-venta/{id}/estado | ventas.gestionar | Actualizar estado con historial |
| POST /api/v1/notas-venta/{id}/pagos | ventas.gestionar | Abonar |
| POST /api/v1/inventario/traslados | inventario.gestionar | Trasladar stock entre bodegas |

Los permisos no-administrador requieren asignación real en `roles_permisos`; el rol `administrador` permite todo.

## Configuración InfinityFree

Consulta **`deployment/infinityfree/README.md`**. La base nueva de InfinityFree se llama `if0_43460400_rutahyd2_es`; MySQL remoto desde PC está bloqueado. Acceder a través de phpMyAdmin desde el dashboard. No subir `.env` a GitHub.

**No activar Laravel apuntando a la base antigua inglesa.** Códigos, tablas, campos y permisos son de la V2 en español.

## Riesgos y trabajo pendiente

- El servidor DTE no está integrado para envío real: faltan pruebas del contrato, autenticación técnica JWT y conciliación de estados SII.
- Los cálculos monetarios de ejemplo usan `round()`/float; se requiere validación fiscal de redondeo/IVA y pruebas de contratos antes de producción.
- Faltan endpoints CRUD de escritura para varios catálogos y pruebas de integración MySQL reales.
- La base nueva arranca sin clientes, productos ni usuarios operativos, excepto las semillas de 5 roles, 2 bodegas y 12 permisos.
- La tabla de tokens es independiente, opcional en la instalación comercial pero obligatoria para poder iniciar sesión en la API.
- No están soportados procesos cron, workers persistentes o WebSockets en InfinityFree gratuito.
