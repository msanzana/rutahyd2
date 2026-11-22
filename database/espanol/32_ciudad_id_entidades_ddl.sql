-- Ruta H&D V2 · DDL32 · única referencia geográfica ciudad_id por entidad.
-- Ejecutar MANUALMENTE después de DDL29/30/31. Copia previa de las tablas.
-- NO elimina columnas texto históricas como clientes.comuna: evitar pérdida de domicilios.
-- Los nuevos formularios y contratos solo aceptan ciudad_id para georreferencia.
-- Campo NULL para permitir homologación posterior de registros históricos.
-- FK ON DELETE RESTRICT protege operaciones de venta e historial.
ALTER TABLE proveedores ADD COLUMN IF NOT EXISTS ciudad_id BIGINT UNSIGNED NULL AFTER direccion_detalle;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS ciudad_id BIGINT UNSIGNED NULL AFTER direccion;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS ciudad_id BIGINT UNSIGNED NULL AFTER correo_electronico;
ALTER TABLE bodegas ADD COLUMN IF NOT EXISTS ciudad_id BIGINT UNSIGNED NULL AFTER nombre;
-- Si la entidad 'empresas' ya existe en la instalación (no existe en el esquema base V2),
-- agregar también el campo ciudad_id sin crear artificialmente una empresa.
SET @hay_empresas := (
  SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema=DATABASE() AND table_name='empresas');
SET @sql_empresas := IF(@hay_empresas > 0,
  'ALTER TABLE empresas ADD COLUMN IF NOT EXISTS ciudad_id BIGINT UNSIGNED NULL',
  'SELECT ''La tabla empresas no existe: no se creó ni modificó'' AS diagnostico');
PREPARE q FROM @sql_empresas;
EXECUTE q;
DEALLOCATE PREPARE q;
-- Única ejecución para las claves foráneas: antes de repetir, comprobar SHOW CREATE TABLE.
ALTER TABLE proveedores
  ADD INDEX ix_hd_proveedores_ciudad(ciudad_id),
  ADD CONSTRAINT fk_hd_proveedores_ciudad FOREIGN KEY(ciudad_id)
    REFERENCES ciudades(id) ON DELETE RESTRICT;
ALTER TABLE clientes
  ADD INDEX ix_hd_clientes_ciudad(ciudad_id),
  ADD CONSTRAINT fk_hd_clientes_ciudad FOREIGN KEY(ciudad_id)
    REFERENCES ciudades(id) ON DELETE RESTRICT;
ALTER TABLE usuarios
  ADD INDEX ix_hd_usuarios_ciudad(ciudad_id),
  ADD CONSTRAINT fk_hd_usuarios_ciudad FOREIGN KEY(ciudad_id)
    REFERENCES ciudades(id) ON DELETE RESTRICT;
ALTER TABLE bodegas
  ADD INDEX ix_hd_bodegas_ciudad(ciudad_id),
  ADD CONSTRAINT fk_hd_bodegas_ciudad FOREIGN KEY(ciudad_id)
    REFERENCES ciudades(id) ON DELETE RESTRICT;
-- Vincular el campo de empresas al catálogo si la tabla existe, solo una ejecución.
SET @sql_emp_fk := IF(@hay_empresas > 0,
  'ALTER TABLE empresas ADD INDEX ix_hd_empresas_ciudad(ciudad_id), ADD CONSTRAINT fk_hd_empresas_ciudad FOREIGN KEY(ciudad_id) REFERENCES ciudades(id) ON DELETE RESTRICT',
  'SELECT ''empresas no aplicable'' AS diagnostico');
PREPARE q FROM @sql_emp_fk;
EXECUTE q;
DEALLOCATE PREPARE q;
-- Auditoría de legacy: NO llenar ciudad_id por inferencia, una comuna no es una ciudad.
SELECT COUNT(*) AS proveedores_pendientes_ubicacion FROM proveedores WHERE ciudad_id IS NULL;
SELECT COUNT(*) AS clientes_pendientes_ubicacion FROM clientes WHERE ciudad_id IS NULL;
SELECT COUNT(*) AS usuarios_pendientes_ubicacion FROM usuarios WHERE ciudad_id IS NULL;
