-- RUTA H&D2 | Diagnóstico posterior a migraciones (SOLO LECTURA)
-- Seleccionar exclusivamente la base if0_43460400_rutahyd2_es en phpMyAdmin.
-- Nunca pegar claves, usuarios, hashes ni datos comerciales en los resultados compartidos.
-- No ejecutar ALTER/INSERT/DELETE; este archivo contiene solo SELECT.

SELECT DATABASE() AS base_actual,
  CASE WHEN DATABASE() = 'if0_43460400_rutahyd2_es'
       THEN 'BASE_V2_CORRECTA'
       ELSE 'DETENER_NO_ES_BASE_V2' END AS control_base;

SELECT
  (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema=DATABASE() AND table_type='BASE TABLE') AS tablas_actuales,
  (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema=DATABASE() AND table_name='tokens_acceso') AS tabla_tokens,
  (SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema=DATABASE() AND table_name IN
      ('rubros','departamentos','familias','lineas','marcas','modelos',
       'tipos_unidad','gramajes','tipos_envasado','condiciones_tributarias')) AS tablas_catalogos,
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema=DATABASE() AND table_name='productos'
      AND column_name IN ('rubro_id','departamento_id','familia_id','linea_id','marca_id',
      'modelo_id','tipo_unidad_id','gramaje_id','tipo_envasado_id','condicion_tributaria_id')) AS columnas_producto,
  (SELECT COUNT(*) FROM information_schema.key_column_usage
    WHERE table_schema=DATABASE() AND table_name='productos'
      AND column_name IN ('rubro_id','departamento_id','familia_id','linea_id','marca_id',
      'modelo_id','tipo_unidad_id','gramaje_id','tipo_envasado_id','condicion_tributaria_id')
      AND referenced_table_name IS NOT NULL) AS fk_producto,
  (SELECT COUNT(*) FROM permisos
    WHERE codigo LIKE 'productos.catalogos.%'
      AND (codigo LIKE '%.ver' OR codigo LIKE '%.gestionar')) AS permisos_producto,
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema=DATABASE() AND table_name='productos'
      AND column_name='categoria_id') AS categoria_legada_preservada;

-- Objetivo SOLO si se instalaron las migraciones 12,13,14,16:
-- 45 tablas, tokens=1, catálogos=10, columnas=10, FK=10, permisos=20, legado=1.
-- Para una instalación parcial, DETENER y revisar por qué faltan tablas o columnas.
-- Nunca intentar aplicar SQL 14 por segunda vez sin conocer su estado.
SELECT codigo, descripcion
FROM permisos
WHERE codigo LIKE 'productos.catalogos.%'
ORDER BY codigo;

SELECT r.codigo AS perfil, COUNT(rp.permiso_id) AS permisos_nuevos_asignados
FROM roles r
LEFT JOIN roles_permisos rp ON rp.rol_id=r.id
  AND rp.permiso_id IN (
    SELECT id FROM permisos WHERE codigo LIKE 'productos.catalogos.%'
  )
GROUP BY r.id,r.codigo
ORDER BY r.codigo;
-- No incluye datos personales de usuarios ni muestra valores de credenciales.
