# RutaH&D2 — Base de datos local (DDL consolidado)

**Origen:** `msanzana/rutahyd2` en GitHub, recuperado el 2026-11-19. Los bloques 01–04 se tomaron de la rama `main`; el bloque 07 de la rama `feature/laravel-repository-map` (actualizada a `product_id → products.id`). Es un snapshot consolidado de scripts **versionados**, no un volcado de datos desde InfinityFree.

## Contenido

| Archivo | Para qué sirve |
| --- | --- |
| `01_RutaHD2_DDL_34_tablas.sql` | Crea la base local `rutahyd2_local` y las 34 tablas con PK, FK, índices y tipos. Sin datos. |
| `02_RutaHD2_semillas_opcionales.sql` | Inserta 5 roles, 2 bodegas y 12 permisos (19 filas en total). |
| `03_Sanctum_opcional_tabla_35.sql` | Agrega `personal_access_tokens` para futuras pruebas de Laravel Sanctum; no forma parte de las 34 tablas. |
| `04_Verificacion_local.sql` | Consultas de inspección para comprobar tablas, FK y semillas. |

## Importar con MySQL Workbench

1. Iniciar MySQL Server local. Abrir Workbench y entrar con tu conexión local.
2. **File > Open SQL Script…** y seleccionar `01_RutaHD2_DDL_34_tablas.sql`.
3. Ejecutar el script con el icono del rayo (ejecutar todo).
4. Actualizar **SCHEMAS**. Debe aparecer `rutahyd2_local` con 34 tablas.
5. Opcional: ejecutar el archivo 02 para roles/bodegas/permisos. Opcional: el 03 para Sanctum.
6. Para ver las relaciones: **Database > Reverse Engineer…**, elegir la conexión local y el esquema `rutahyd2_local`, y terminar el asistente. Workbench dibuja el ERD a partir de las claves foráneas.
7. Para inspeccionar registros: botón derecho en una tabla > **Select Rows - Limit 1000**.

## Importar desde consola

```bash
mysql -h 127.0.0.1 -P 3306 -u root -p < 01_RutaHD2_DDL_34_tablas.sql
mysql -h 127.0.0.1 -P 3306 -u root -p < 02_RutaHD2_semillas_opcionales.sql
# Opcional para Laravel Sanctum (tabla 35)
mysql -h 127.0.0.1 -P 3306 -u root -p < 03_Sanctum_opcional_tabla_35.sql
```

**phpMyAdmin/XAMPP:** abrir Importar y seleccionar el archivo 01. Si el usuario de MySQL no tiene permiso para `CREATE DATABASE`, crear primero el esquema vacío y usar una copia del script sin las líneas `CREATE DATABASE` / `USE` o solicitar permisos.

## Advertencias

- El DDL local representa **34 tablas planificadas**: 32 originales y 2 nuevas (`sales`, `sale_details`). Las nuevas no han sido verificadas como aplicadas en InfinityFree.
- `sale_details` contiene `product_id` FK a `products.id`; no guarda el nombre, SKU ni descripción del catálogo. Conserva datos económicos propios de cada venta para historia y DTE.
- Las tablas operativas se crean vacías. No se importan ventas ni clientes reales.
- Las columnas con `JSON` requieren MySQL o MariaDB compatibles.
- No ejecutar este archivo sobre una base con estructura anterior esperando que cambie las tablas: `CREATE TABLE IF NOT EXISTS` **no aplica ALTER**. Para pasar de `presentation_id` a `product_id` existe una migración independiente en GitHub.
- El DTE v2 definitivo aún requiere validar su contrato; este esquema refleja el contrato inspeccionado en `sii-dte-backend/develop`.
- Para recuperar el sistema real, usar migraciones y respaldos; **no** usar `migrate:fresh` en producción.

## Verificación de inventario de tablas

Tablas: `agenda_events`, `audit_logs`, `customer_prices`, `customers`, `delivery_routes`, `dispatch_guide_items`, `dispatch_guides`, `issued_documents`, `monthly_sales_targets`, `permissions`, `product_categories`, `product_images`, `product_presentations`, `products`, `promotion_items`, `promotions`, `purchase_order_items`, `purchase_orders`, `quotation_items`, `quotations`, `role_permissions`, `roles`, `sale_details`, `sale_note_items`, `sale_note_status_history`, `sale_notes`, `sale_payments`, `sales`, `scheduled_workdays`, `stock_balances`, `stock_movements`, `suppliers`, `users`, `warehouses`.
