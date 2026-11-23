-- Ruta H&D V2 · DDL38 · Orden de compra / catálogo global de estados
-- EJECUCIÓN EXCLUSIVAMENTE MANUAL en phpMyAdmin, tras respaldo confirmado.
-- No ejecutar desde CI, Laravel, navegador automatizado ni conectores.
-- Fuente vigente: OC con proveedor_id, estado texto histórico, detalle presentacion_id.
-- Importar SOLO UNA VEZ. Las restricciones impiden descartar referencias desconocidas.
-- 1. Antes: SHOW COLUMNS FROM ordenes_compra; SHOW COLUMNS FROM detalles_ordenes_compra;
--    SELECT estado, COUNT(*) FROM ordenes_compra GROUP BY estado;
--    Confirmar que la tabla estados y la tabla modulos aún NO existen.
--    Si existen por un módulo previo, ABORTAR y conciliar su estructura.

CREATE TABLE IF NOT EXISTS estados (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 codigo VARCHAR(60) NOT NULL,
 nombre VARCHAR(120) NOT NULL,
 descripcion VARCHAR(255) NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_hd_estados_codigo (codigo),
 UNIQUE KEY uq_hd_estados_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modulos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 codigo VARCHAR(80) NOT NULL,
 nombre VARCHAR(120) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_hd_modulos_codigo (codigo),
 UNIQUE KEY uq_hd_modulos_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modulos_estados (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 modulo_id BIGINT UNSIGNED NOT NULL,
 estado_id BIGINT UNSIGNED NOT NULL,
 orden INT UNSIGNED NOT NULL DEFAULT 0,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 PRIMARY KEY (id),
 UNIQUE KEY uq_hd_modulo_estado (modulo_id,estado_id),
 KEY ix_hd_me_estado (estado_id),
 CONSTRAINT fk_hd_me_modulo FOREIGN KEY(modulo_id)
  REFERENCES modulos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_hd_me_estado FOREIGN KEY(estado_id)
  REFERENCES estados(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO estados (codigo,nombre,descripcion) VALUES
 ('ACTIVA','Activa','Orden emitida, pendiente de recepción'),
 ('RECEPCIONADA','Recepcionada','Recepciones completadas; reservado a Recepciones'),
 ('NULA','Nula','Orden anulada, sin alteración de inventario')
ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);

INSERT INTO modulos (codigo,nombre) VALUES
 ('ORDEN_COMPRA','Orden de Compra')
ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);

INSERT INTO modulos_estados (modulo_id,estado_id,orden)
SELECT m.id,e.id,CASE e.codigo WHEN 'ACTIVA' THEN 1
 WHEN 'RECEPCIONADA' THEN 2 ELSE 3 END
FROM modulos m CROSS JOIN estados e
WHERE m.codigo='ORDEN_COMPRA' AND e.codigo IN ('ACTIVA','RECEPCIONADA','NULA')
ON DUPLICATE KEY UPDATE orden=VALUES(orden);

-- Nueva FK sin destruir estado histórico de texto.
ALTER TABLE ordenes_compra
 ADD COLUMN estado_id BIGINT UNSIGNED NULL AFTER estado,
 ADD KEY ix_hd_oc_estado_id (estado_id),
 ADD CONSTRAINT fk_hd_oc_estado FOREIGN KEY (estado_id)
  REFERENCES estados(id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Homologación deliberadamente conservadora: los estados desconocidos
-- permanecen NULL y bloquean la siguiente restricción NOT NULL.
UPDATE ordenes_compra oc
JOIN estados e ON e.codigo=CASE
 WHEN UPPER(TRIM(oc.estado)) IN ('BORRADOR','PENDIENTE','ACTIVA','ENVIADA','EMITIDA') THEN 'ACTIVA'
 WHEN UPPER(TRIM(oc.estado)) IN ('RECEPCIONADA','RECIBIDA','COMPLETADA') THEN 'RECEPCIONADA'
 WHEN UPPER(TRIM(oc.estado)) IN ('NULA','ANULADA','CANCELADA') THEN 'NULA'
 ELSE '__SIN_HOMOLOGAR__' END
SET oc.estado_id=e.id
WHERE oc.estado_id IS NULL;

-- Si existiesen estados históricos desconocidos, esta instrucción fallará
-- y NO se modificará/eliminará el valor de estado. Revisarlos primero.
ALTER TABLE ordenes_compra
 MODIFY COLUMN estado_id BIGINT UNSIGNED NOT NULL;

-- Sustitución de presentación por producto conservando todas las filas.
ALTER TABLE detalles_ordenes_compra
 ADD COLUMN producto_id BIGINT UNSIGNED NULL AFTER orden_compra_id;

UPDATE detalles_ordenes_compra d
JOIN presentaciones_productos pp ON pp.id=d.presentacion_id
SET d.producto_id=pp.producto_id
WHERE d.producto_id IS NULL;

-- Impide descartar referencias históricas sin resolver.
ALTER TABLE detalles_ordenes_compra
 MODIFY COLUMN producto_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE detalles_ordenes_compra
 ADD KEY ix_hd_doc_producto (producto_id),
 ADD CONSTRAINT fk_hd_doc_producto FOREIGN KEY(producto_id)
  REFERENCES productos(id) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Solo después de validar la nueva FK queda permitido retirar la anterior.
ALTER TABLE detalles_ordenes_compra
 DROP FOREIGN KEY fk_detalles_ordenes_compra_2,
 DROP COLUMN presentacion_id;

-- Controles posteriores permitidos (NO usar information_schema):
SHOW COLUMNS FROM estados;
SHOW COLUMNS FROM modulos;
SHOW COLUMNS FROM modulos_estados;
SHOW COLUMNS FROM ordenes_compra LIKE 'estado_id';
SHOW COLUMNS FROM detalles_ordenes_compra LIKE 'producto_id';
SELECT m.nombre AS modulo,e.nombre AS estado,me.id AS pivote_id
FROM modulos_estados me
JOIN modulos m ON m.id=me.modulo_id
JOIN estados e ON e.id=me.estado_id
WHERE m.codigo='ORDEN_COMPRA'
ORDER BY me.orden;
SELECT COUNT(*) AS oc_sin_estado FROM ordenes_compra WHERE estado_id IS NULL;
SELECT COUNT(*) AS detalles_sin_producto FROM detalles_ordenes_compra WHERE producto_id IS NULL;
