-- Ruta H&D2 | PRE-FLIGHT DE CLASIFICACIONES (SOLO LECTURA)
-- Ejecutar sobre la base Ruta H&D2 seleccionada en phpMyAdmin.
-- NO es la base dte-sii. No cambia datos ni estructura.
-- Verificar nombre de base y número de tablas antes de cualquier ALTER TABLE.
SELECT DATABASE() AS base_seleccionada;
SELECT TABLE_NAME AS tabla, TABLE_ROWS AS filas_aproximadas, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN (
 'productos','categorias_productos','rubros','departamentos','familias','lineas',
 'marcas','modelos','tipos_unidad','gramajes','tipos_envasado','condiciones_tributarias',
 'tokens_acceso','usuarios','roles','permisos','roles_permisos'
)
ORDER BY TABLE_NAME;
SELECT COLUMN_NAME AS columna, COLUMN_TYPE AS tipo, IS_NULLABLE AS acepta_null,
 COLUMN_KEY AS tipo_clave
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'productos'
AND COLUMN_NAME IN (
 'id','categoria_id','proveedor_id','sku','nombre','descripcion','activo',
 'rubro_id','departamento_id','familia_id','linea_id','marca_id','modelo_id',
 'tipo_unidad_id','gramaje_id','tipo_envasado_id','condicion_tributaria_id'
)
ORDER BY ORDINAL_POSITION;
SELECT TABLE_NAME AS tabla, CONSTRAINT_NAME AS restriccion,
 COLUMN_NAME AS columna, REFERENCED_TABLE_NAME AS tabla_referenciada,
 REFERENCED_COLUMN_NAME AS columna_referenciada
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA=DATABASE()
AND TABLE_NAME IN ('productos','departamentos','familias','lineas','modelos')
AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME;
-- Este informe NO demuestra backup, datos conciliados ni autorización de despliegue.
