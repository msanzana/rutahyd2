-- Ruta H&D V2 · SQL 22: verificación SOLO LECTURA posterior a 20 DDL y 21 semillas.
-- No modifica registros, no ejecuta CREATE/ALTER/DELETE.
SELECT DATABASE() AS base_activa;
SELECT
 (SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema=DATABASE() AND table_name IN (
  'tipos_codigos_barras','productos_proveedores',
  'productos_codigos_barra_venta','productos_codigos_barra_compra')) AS tablas_nuevas_esperadas_4,
 (SELECT COUNT(*) FROM tipos_codigos_barras
  WHERE codigo IN ('EAN13','EAN8','DUN14','ITF14','CODE39','CODE128',
                  'GS1128','UPCA','UPCE','QRCODE','DATAMATRIX')) AS tipos_sembrados_esperados_11,
 (SELECT COUNT(*) FROM permisos
  WHERE codigo IN ('productos.catalogos.tipos_codigos_barras.ver',
                   'productos.catalogos.tipos_codigos_barras.gestionar')) AS permisos_nuevos_esperados_2,
 (SELECT COUNT(*) FROM tipos_unidad WHERE nombre IN
  ('Unidad','kg','gr','Caja','Bolsa','Pack','Litro','ml')) AS unidades_basicas_esperadas_8,
 (SELECT COUNT(*) FROM tipos_envasado WHERE nombre IN
  ('Caja','Bolsa','Botella','Lata','Bandeja','Pallet')) AS envasados_basicos_esperados_6,
 (SELECT COUNT(*) FROM information_schema.key_column_usage
    WHERE table_schema=DATABASE() AND table_name IN
      ('productos_proveedores','productos_codigos_barra_venta',
       'productos_codigos_barra_compra')
    AND referenced_table_name IS NOT NULL) AS relaciones_fks_esperadas_6;

-- La columna heredada productos.gramaje_id y su tabla se conservan para
-- NO perder referencias históricas; dejan de aparecer y ser obligatorias en UI.
SELECT table_name,column_name
FROM information_schema.columns
WHERE table_schema=DATABASE()
  AND ((table_name='productos' AND column_name IN
        ('sku','proveedor_id','gramaje_id','tipo_unidad_id','tipo_envasado_id'))
       OR (table_name='productos_proveedores' AND column_name IN
        ('producto_id','proveedor_id','codigo_proveedor_producto',
         'costo_proveedor','ultima_recepcion_id','fecha_ultima_recepcion','activo')))
ORDER BY table_name,column_name;
