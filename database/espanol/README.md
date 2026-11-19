# Esquema de datos V2 en espanol

## Estado
- **Base InfinityFree:** NO migrada. Su ultima estructura comprobada remotamente fue de 32 tablas; las 2 nuevas no pudieron verificarse por sesion vencida.
- **GitHub:** rama `feature/esquema-espanol`, con version local y diccionario de traduccion.
- **Contrato DTE:** claves JSON de la API externa deben permanecer con los nombres definidos en `sii-dte-backend/develop` (ingles); la traduccion es SOLO de identificadores internos SQL.
- **Advertencia:** No renombrar columnas productivas sin respaldo y sin adaptar todos los modelos/repositorios/servicios/consultas SQL Laravel al nuevo diccionario.

## Archivos
- `01_esquema_completo_34_tablas_es.sql`: CREATE DATABASE rutahyd2_es_local, 34 tablas, FKs e indices, sin datos.
- `02_diccionario_tablas.csv`: equivalencias de 34 nombres de tablas.
- `03_diccionario_columnas.csv`: equivalencias para cada columna, incluso nombres ya en espanol/universales.
- `04_relaciones.csv`: claves foraneas del nuevo esquema.
- `05_semillas_es.sql`: configuracion minima traducida, opcional.

## Criterios
- Ejemplos: `users -> usuarios`, `customers -> clientes`, `product_id -> producto_id`, `created_at -> creado_en`.
- `id`, `sku` y `folio` permanecen como terminos tecnicos universales o espanoles.
- PK, tipos de datos, cardinalidades y relaciones FK se preservan.
- `detalles_ventas.producto_id` referencia `productos.id`, no la presentacion.
- Esta version cambia nombres estructurales; los valores de estados/codigos deben tener una estrategia de traduccion coordinada con Laravel.

## Visualizar en Workbench
1. Abrir archivo 01 en MySQL Workbench conectado a MySQL local; ejecutar todo.
2. Actualizar panel SCHEMAS; seleccionar `rutahyd2_es_local`.
3. Abrir **Database > Reverse Engineer**; seleccionar este esquema para generar un diagrama EER con claves foraneas.
4. Para roles/bodegas/permisos ejecutar el archivo 05.

## InfinityFree
Para modificar `if0_43460400_rutahyd2`: recuperar el inicio de sesion, obtener un respaldo SQL comprobable, medir registros y restricciones existentes, preparar migracion que conserve datos y adaptar previamente backend. NO ejecutar el DDL local directamente en esa base, pues `CREATE TABLE IF NOT EXISTS` no renombra tablas preexistentes.

## Conversion del servidor existente (sin datos borrados)

Se añaden scripts **PREPARADOS, NO EJECUTADOS**:
- `06_previa_infinityfree.sql`: inspeccion de tablas y estado.
- `07_migrar_infinityfree_desde_32_tablas.sql`: parte de las 32 tablas originales y crea `ventas`, `detalles_ventas` faltantes en español.
- `08_migrar_infinityfree_desde_34_tablas.sql`: solo si existen también `sales` y `sale_details` previamente.

**Importante**: son cambios estructurales NO reversibles automáticamente y NO idempotentes. Revisar backup y compatibilidad antes de usarlos. Una ejecución fallida puede dejar parte del esquema renombrada. Estos SQL de migración cambian nombres de columnas y tablas; no traducen por sí solos los valores de estados, permisos y textos guardados. La adaptación Laravel queda pendiente.
