-- Ruta H&D V2 · DDL31 · ampliar identificación tributaria/comercial de Proveedores.
-- Solo ejecución MANUAL en phpMyAdmin después de DDL29 y DDL30 y respaldo.
-- El SII emplea RUT, razón social, giro, actividad y domicilio/comuna/ciudad en DTE.
-- Algunos metadatos son comerciales opcionales: NO son certificaciones automáticas SII.
ALTER TABLE proveedores
  ADD COLUMN IF NOT EXISTS razon_social VARCHAR(190) NULL AFTER nombre,
  ADD COLUMN IF NOT EXISTS nombre_fantasia VARCHAR(190) NULL AFTER razon_social,
  ADD COLUMN IF NOT EXISTS giro VARCHAR(255) NULL AFTER rut,
  ADD COLUMN IF NOT EXISTS actividad_economica_codigo VARCHAR(12) NULL AFTER giro,
  ADD COLUMN IF NOT EXISTS tipo_contribuyente VARCHAR(40) NULL AFTER actividad_economica_codigo,
  ADD COLUMN IF NOT EXISTS inicio_actividades DATE NULL AFTER tipo_contribuyente,
  ADD COLUMN IF NOT EXISTS situacion_tributaria VARCHAR(80) NULL AFTER inicio_actividades,
  ADD COLUMN IF NOT EXISTS correo_dte VARCHAR(190) NULL AFTER correo_electronico,
  ADD COLUMN IF NOT EXISTS telefono_secundario VARCHAR(50) NULL AFTER telefono,
  ADD COLUMN IF NOT EXISTS sitio_web VARCHAR(255) NULL AFTER telefono_secundario,
  ADD COLUMN IF NOT EXISTS representante_legal VARCHAR(190) NULL AFTER sitio_web,
  ADD COLUMN IF NOT EXISTS representante_rut VARCHAR(25) NULL AFTER representante_legal,
  ADD COLUMN IF NOT EXISTS contacto_nombre VARCHAR(190) NULL AFTER representante_rut,
  ADD COLUMN IF NOT EXISTS contacto_cargo VARCHAR(120) NULL AFTER contacto_nombre,
  ADD COLUMN IF NOT EXISTS contacto_telefono VARCHAR(50) NULL AFTER contacto_cargo,
  ADD COLUMN IF NOT EXISTS contacto_correo VARCHAR(190) NULL AFTER contacto_telefono,
  ADD COLUMN IF NOT EXISTS direccion_numero VARCHAR(30) NULL AFTER direccion,
  ADD COLUMN IF NOT EXISTS direccion_detalle VARCHAR(120) NULL AFTER direccion_numero,
  ADD COLUMN IF NOT EXISTS codigo_postal VARCHAR(16) NULL AFTER direccion_detalle,
  ADD COLUMN IF NOT EXISTS observaciones TEXT NULL AFTER codigo_postal,
  ADD COLUMN IF NOT EXISTS moneda_preferida CHAR(3) NULL AFTER observaciones,
  ADD COLUMN IF NOT EXISTS plazo_pago_dias SMALLINT UNSIGNED NULL AFTER moneda_preferida;
-- RUT duplicados históricos NO se eliminan ni se aplica UNIQUE automáticamente.
-- Validación y normalización de nuevos RUTs es responsabilidad de Laravel.
SHOW COLUMNS FROM proveedores;
