-- Ruta H&D V39 · DDL35 · Simplificar ficha de proveedores, eliminación de columnas solicitadas.
-- **DESTRUCTIVO**: realiza respaldo previo de proveedores (exportar tabla completa).
-- Importar SOLO MANUALMENTE tras DDL33 y DDL34 y tras revisar que ya se guardó
-- fuera de producción cualquier información anterior que debas conservar.
-- MariaDB InfinityFree admite DROP COLUMN IF EXISTS. El resto de entidades intacto.
-- Columnas que SE CONSERVAN: id, nombre, razon_social, nombre_fantasia, rut,
-- giro, actividad_economica_codigo, tipo_contribuyente, direccion,
-- direccion_numero, ciudad_id, correo_electronico, telefono,
-- plazo_pago_dias, activo, campos técnicos existentes.
SET NAMES utf8mb4;
-- Comprobación previa de valores con datos antes de borrar (ejecutar y revisar):
SELECT COUNT(*) AS proveedores_existentes FROM proveedores;
SELECT COUNT(*) AS con_inicio_actividades FROM proveedores WHERE inicio_actividades IS NOT NULL;
SELECT COUNT(*) AS con_contacto_comercial FROM proveedores
 WHERE contacto_nombre IS NOT NULL OR contacto_correo IS NOT NULL;
-- Después del respaldo:
ALTER TABLE proveedores
  DROP COLUMN IF EXISTS inicio_actividades,
  DROP COLUMN IF EXISTS direccion_detalle,
  DROP COLUMN IF EXISTS codigo_postal,
  DROP COLUMN IF EXISTS situacion_tributaria,
  DROP COLUMN IF EXISTS correo_dte,
  DROP COLUMN IF EXISTS telefono_secundario,
  DROP COLUMN IF EXISTS sitio_web,
  DROP COLUMN IF EXISTS representante_legal,
  DROP COLUMN IF EXISTS representante_rut,
  DROP COLUMN IF EXISTS contacto_nombre,
  DROP COLUMN IF EXISTS contacto_cargo,
  DROP COLUMN IF EXISTS contacto_telefono,
  DROP COLUMN IF EXISTS contacto_correo,
  DROP COLUMN IF EXISTS moneda_preferida,
  DROP COLUMN IF EXISTS observaciones;
-- No eliminar correo_electronico, telefono, giro ni plazo_pago_dias.
SHOW COLUMNS FROM proveedores;
