# RutaH&D2 — Backend Laravel 13
**Estado: mapeo de backend en rama de desarrollo; no desplegado.**
Sistema independiente del H&D original; diseño React separado en `rutahyd2front`.
## Arquitectura
- Laravel ^13 (PHP >= 8.3), API REST
- 32 modelos Eloquent, 32 interfaces y 32 repositorios: 30 CRUD por llave ID + 2 por llave compuesta.
- Inyección de interfaces en `AppServiceProvider`.
- Servicios de dominio: `SaleNoteService` (creación, estados, pagos), `StockTransferService` (guía/traspaso con transacción).
- Autorización con tokens Sanctum + permisos por rol (denegar por defecto).
- La BD existente en InfinityFree tiene 32 tablas; **no** correr migraciones de la estructura DDL original sin plan de migración.
- Los **5 scripts SQL** existentes son la fuente de verdad del esquema inicial; `06_sanctum_tokens_pending.sql` es una propuesta sin ejecutar.
## Uso local
1. Instalar PHP 8.3+, Composer 2, extensiones PHP y MySQL.
2. `composer install`; copiar `.env.example` a `.env` y asignar credenciales de una base local.
3. `php artisan key:generate`.
4. Importar `database/sql/01..` a `05..` en una BBDD propia, limpia, **en ese orden**. No usar `migrate:fresh` sobre InfinityFree.
5. Para usar tokens, ejecutar la migración Sanctum pendiente; se añadirá una tabla fuera de las 32. Crear el primer administrador mediante un flujo seguro que aún está pendiente.
6. `php artisan serve`; comprobar `/api/health`.
## Endpoints implementados (requieren validación e integración)
- `GET /api/health`: monitor básico sin datos sensibles.
- `POST /api/v1/auth/login`: login/token (inactivo hasta crear usuario y tabla Sanctum).
- `POST /api/v1/auth/logout`: invalidar token.
- `GET /api/v1/customers`, `GET /api/v1/products`: listados protegidos.
- `GET/POST /api/v1/sale-notes`, transición y registro de pagos.
- `POST /api/v1/stock-transfers`: mueve stock entre bodegas de forma transaccional y registra guía.
## Estado y límites
- El mapeo de TODAS las tablas está incluido, pero NO todas las entidades exponen endpoints HTTP: permisos, cotizaciones, ofertas, informes, agenda, SII y otros flujos requieren especificación aprobada.
- Los cálculos monetarios actuales son prototipo: antes de producción, cambiar a aritmética decimal exacta, probar IVA, y aplicar restricciones/refuerzos de idempotencia.
- La API no puede autenticarse aún en la base InfinityFree mientras no se cree `personal_access_tokens` y un usuario administrado de forma segura.
- No se ha configurado FTP: consulta `DEPLOY_FTP_PENDIENTE.md`.
- El alojamiento InfinityFree gratuito no ofrece acceso SSH, cron ni workers; verificación de PHP pendiente.
- Nunca subir contraseñas a GitHub ni dejar `.env` accesible públicamente.
