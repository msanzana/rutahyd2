-- Ruta H&D V2 · DDL26 correctivo · Guardado de productos HTTP 500 (2026-11-22)
-- Fuente: error REAL de Laravel SQLSTATE[42S22], MySQL 1054: Unknown column 'costo_neto'.
-- Ejecutar DIRECTAMENTE en phpMyAdmin con la base if0_43460400_rutahyd2_es seleccionada.
-- NO ejecutar mediante GitHub Actions, Laravel migrations, conectores ni procesos automáticos.
-- MariaDB: ADD COLUMN IF NOT EXISTS evita la duplicación cuando algún campo ya existe.
-- No contiene triggers, DROP, DELETE, UPDATE ni INSERT. Preserva registros existentes.
-- Realizar respaldo antes de alterar el esquema.

-- Reparación indispensable: precio por producto y lógica de listas (DDL24).
ALTER TABLE productos
  ADD COLUMN IF NOT EXISTS costo_neto DECIMAL(15,4) NOT NULL DEFAULT 0.0000;

-- Compatibilidad con el cuadro de margen mínimo de la Línea (DDL24).
-- Si el campo ya existe, MariaDB lo deja intacto.
ALTER TABLE lineas
  ADD COLUMN IF NOT EXISTS minimo_margen_precio_venta DECIMAL(9,4)
  NOT NULL DEFAULT 0.0000;

-- Comprobaciones de solo lectura: deben aparecer ambas columnas.
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((TABLE_NAME='productos' AND COLUMN_NAME='costo_neto')
    OR (TABLE_NAME='lineas' AND COLUMN_NAME='minimo_margen_precio_venta'))
ORDER BY TABLE_NAME, COLUMN_NAME;

-- Confirmar que el módulo de tarifas ya creado tiene las tablas requeridas.
-- Si alguna no existe, revisar/importar DDL24 completo antes de guardar.
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('listas_precios', 'listas_precios_detalle')
ORDER BY TABLE_NAME;
