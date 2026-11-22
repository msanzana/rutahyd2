-- Ruta H&D V2 · SQL 22 corregido (22-11-2026).
-- Verificación SOLO LECTURA posterior a DDL20 y DML21.
-- CORRECCIÓN: indicar explícitamente la base en las referencias de las
-- subconsultas, evitando la resolución accidental contra information_schema.
-- Ninguna consulta modifica estructura ni registros.
-- Confirmar que la base elegida en phpMyAdmin sea if0_43460400_rutahyd2_es.

SELECT DATABASE() AS base_activa;

-- Primero confirmar la existencia de las 4 nuevas tablas (no requiere que
-- estén presentes para mostrar el resultado).
SELECT
    TABLE_NAME AS tabla_encontrada
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'if0_43460400_rutahyd2_es'
  AND TABLE_NAME IN (
    'tipos_codigos_barras',
    'productos_proveedores',
    'productos_codigos_barra_venta',
    'productos_codigos_barra_compra'
  )
ORDER BY TABLE_NAME;

-- Resumen: se usan nombres COMPLETAMENTE CALIFICADOS, no implícitos.
SELECT
  (SELECT COUNT(*)
   FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = 'if0_43460400_rutahyd2_es'
     AND TABLE_NAME IN (
       'tipos_codigos_barras',
       'productos_proveedores',
       'productos_codigos_barra_venta',
       'productos_codigos_barra_compra'
     )) AS tablas_nuevas_esperadas_4,
  (SELECT COUNT(*)
   FROM `if0_43460400_rutahyd2_es`.`tipos_codigos_barras`
   WHERE codigo IN ('EAN13','EAN8','DUN14','ITF14','CODE39','CODE128',
                    'GS1128','UPCA','UPCE','QRCODE','DATAMATRIX')
  ) AS tipos_sembrados_esperados_11,
  (SELECT COUNT(*)
   FROM `if0_43460400_rutahyd2_es`.`permisos`
   WHERE codigo IN (
     'productos.catalogos.tipos_codigos_barras.ver',
     'productos.catalogos.tipos_codigos_barras.gestionar'
   )) AS permisos_nuevos_esperados_2,
  (SELECT COUNT(*)
   FROM `if0_43460400_rutahyd2_es`.`tipos_unidad`
   WHERE nombre IN ('Unidad','kg','gr','Caja','Bolsa','Pack','Litro','ml')
  ) AS unidades_basicas_esperadas_8,
  (SELECT COUNT(*)
   FROM `if0_43460400_rutahyd2_es`.`tipos_envasado`
   WHERE nombre IN ('Caja','Bolsa','Botella','Lata','Bandeja','Pallet')
  ) AS envasados_basicos_esperados_6,
  (SELECT COUNT(*)
   FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = 'if0_43460400_rutahyd2_es'
     AND TABLE_NAME IN (
       'productos_proveedores',
       'productos_codigos_barra_venta',
       'productos_codigos_barra_compra'
     )
     AND REFERENCED_TABLE_NAME IS NOT NULL
  ) AS relaciones_fks_esperadas_6;

-- La tabla gramajes y productos.gramaje_id permanecen intactas para
-- preservar relaciones históricas, aunque dejarán de usarse en la UI.
SELECT TABLE_NAME, COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'if0_43460400_rutahyd2_es'
  AND (
    (TABLE_NAME='productos' AND COLUMN_NAME IN (
      'sku','proveedor_id','gramaje_id','tipo_unidad_id','tipo_envasado_id'
    ))
    OR
    (TABLE_NAME='productos_proveedores' AND COLUMN_NAME IN (
      'producto_id','proveedor_id','codigo_proveedor_producto','costo_proveedor',
      'ultima_recepcion_id','fecha_ultima_recepcion','activo'
    ))
  )
ORDER BY TABLE_NAME, COLUMN_NAME;
