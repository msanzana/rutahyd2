-- Ruta H&D V2 · DDL 37 / V50 · Tipos jurídicos de clientes y campos de cliente.
-- EJECUCIÓN MANUAL ÚNICA en phpMyAdmin, después de respaldo completo.
-- Base: if0_43460400_rutahyd2_es. Requiere DDL24 (listas), DDL32
-- (ciudad_id) y DDL33/34 (giros SII) previamente ejecutados.
-- NO consulta information_schema. NO usa triggers ni procedimientos.
-- No repetir ALTER ni DROP si ya ejecutaste este archivo.
-- Antes del paso de DROP se conserva copia de comuna y sector.
SET NAMES utf8mb4;

-- 1. Catálogo independiente, administrable por código y estado.
CREATE TABLE IF NOT EXISTS tipos_juridicos_clientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(32) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipos_juridicos_clientes_codigo (codigo),
    KEY idx_tipos_juridicos_clientes_activo (activo),
    CONSTRAINT chk_tipos_juridicos_clientes_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tipos_juridicos_clientes (codigo,nombre,activo)
SELECT 'EMPRESA','Empresa',1
WHERE NOT EXISTS (
    SELECT 1 FROM tipos_juridicos_clientes WHERE codigo='EMPRESA'
);
INSERT INTO tipos_juridicos_clientes (codigo,nombre,activo)
SELECT 'PERSONA_NATURAL','Persona natural',1
WHERE NOT EXISTS (
    SELECT 1 FROM tipos_juridicos_clientes WHERE codigo='PERSONA_NATURAL'
);

-- 2. Respaldo previo de las columnas históricas que dejan de usarse.
-- La tabla es conservada; no participa en el nuevo CRUD de Clientes.
CREATE TABLE IF NOT EXISTS respaldo_clientes_comuna_sector_v50 (
    cliente_id BIGINT UNSIGNED NOT NULL,
    comuna VARCHAR(100) NULL,
    sector VARCHAR(120) NULL,
    respaldado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO respaldo_clientes_comuna_sector_v50 (cliente_id,comuna,sector)
SELECT c.id,c.comuna,c.sector
FROM clientes c
LEFT JOIN respaldo_clientes_comuna_sector_v50 b ON b.cliente_id=c.id
WHERE b.cliente_id IS NULL;

-- 3. Ampliar clientes SIN clasificar automáticamente los datos antiguos.
-- tipo_juridico_id queda NULL en registros existentes hasta su revisión,
-- para no afirmar que son empresas o personas naturales por inferencia.
ALTER TABLE clientes
    ADD COLUMN giro VARCHAR(255) NULL AFTER nombre,
    ADD COLUMN actividad_economica_codigo VARCHAR(6) NULL AFTER giro,
    ADD COLUMN tipo_juridico_id BIGINT UNSIGNED NULL AFTER actividad_economica_codigo,
    ADD INDEX idx_hd_clientes_tipo_juridico (tipo_juridico_id),
    ADD INDEX idx_hd_clientes_giro (giro(120)),
    ADD CONSTRAINT fk_hd_clientes_tipo_juridico FOREIGN KEY (tipo_juridico_id)
        REFERENCES tipos_juridicos_clientes(id) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- 4. Retirar las dos columnas antiguas. La localización de la ficha
-- ya proviene de clientes.ciudad_id -> ciudades (DDL32).
ALTER TABLE clientes
    DROP COLUMN comuna,
    DROP COLUMN sector;

-- 5. Verificaciones legibles, SIN metadatos restringidos.
SHOW CREATE TABLE tipos_juridicos_clientes;
SHOW CREATE TABLE clientes;
SHOW TABLES LIKE 'respaldo_clientes_comuna_sector_v50';
SELECT id,codigo,nombre,activo
FROM tipos_juridicos_clientes ORDER BY id;
SELECT COUNT(*) AS clientes_sin_tipo_juridico
FROM clientes WHERE tipo_juridico_id IS NULL;
SELECT COUNT(*) AS comunas_y_sectores_respaldados
FROM respaldo_clientes_comuna_sector_v50;
SELECT c.id,c.rut,c.nombre,c.giro,c.ciudad_id,
       tj.nombre AS tipo_juridico,
       lp.nombre AS lista_precios_vigente
FROM clientes c
LEFT JOIN tipos_juridicos_clientes tj ON tj.id=c.tipo_juridico_id
LEFT JOIN clientes_listas_precios clp
    ON clp.cliente_id=c.id AND clp.activo=1
LEFT JOIN listas_precios lp
    ON lp.id=clp.lista_precio_id AND lp.activo=1
ORDER BY c.id DESC LIMIT 25;

-- NOTAS:
-- - No existe tipo_cliente en el esquema oficial anterior; no se hace
--   DROP COLUMN tipo_cliente sin evidencia de que esté presente.
-- - El campo giro es una glosa; cuando se selecciona en el buscador SII,
--   la API copia la descripción oficial y persiste el código de seis dígitos.
-- - No se crea ninguna tabla nueva de listas de precios: DDL24 ya lo resolvió.
