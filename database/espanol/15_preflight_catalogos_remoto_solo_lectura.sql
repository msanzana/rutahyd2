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

-- DIAGNÓSTICO RESUMIDO SIN CONSULTAR DATOS COMERCIALES
-- 34 tablas: estructura V2 original; +1 tokens; +10 catálogos; =45.
-- No ejecutar migraciones si base_seleccionada no coincide con la V2 española.
SELECT CASE WHEN DATABASE()='if0_43460400_rutahyd2_es'
  THEN 'OK_BASE_V2_ES' ELSE 'DETENER_BASE_INCORRECTA' END AS control_base,
 (SELECT COUNT(*) FROM information_schema.tables
   WHERE table_schema=DATABASE() AND table_type='BASE TABLE') AS total_tablas,
 (SELECT COUNT(*) FROM information_schema.tables
   WHERE table_schema=DATABASE() AND table_name='tokens_acceso') AS tokens_instalados,
 (SELECT COUNT(*) FROM information_schema.tables
   WHERE table_schema=DATABASE() AND table_name IN
    ('rubros','departamentos','familias','lineas','marcas','modelos',
     'tipos_unidad','gramajes','tipos_envasado','condiciones_tributarias')) AS catalogos_instalados,
 (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema=DATABASE() AND table_name='productos'
     AND column_name IN
      ('rubro_id','departamento_id','familia_id','linea_id','marca_id',
       'modelo_id','tipo_unidad_id','gramaje_id','tipo_envasado_id',
       'condicion_tributaria_id')) AS columnas_nuevas_producto;
-- 0/10 columnas: falta SQL 14. 10/10: SQL 14 probablemente aplicado;
-- nunca ejecutarlo de nuevo solo por encontrar parte de la estructura.
-- Si aparecen entre 1 y 9 columnas, PARAR y auditar cambios parciales.
