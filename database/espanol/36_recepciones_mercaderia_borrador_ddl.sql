-- Ruta H&D V2 · PROPUESTA DDL36 · Cabecera y detalle de recepciones de mercaderías
-- ESTADO: BORRADOR DE DISEÑO, NO APTO PARA IMPORTAR A PRODUCCIÓN TODAVÍA.
-- Solo para revisión y pruebas con bases efímeras. Cuando se apruebe, el titular
-- podrá ejecutarlo MANUALMENTE en phpMyAdmin con respaldo previo y auditoría.
-- No altera productos, saldos, movimientos ni productos_proveedores; no hay DML.
-- SIN TRIGGERS, SIN MIGRACIONES AUTOMÁTICAS, SIN DROP.
-- Requisitos verificados por revisión: esquema español 01 y DDL20 proveedor-producto.

CREATE TABLE IF NOT EXISTS recepciones_mercaderia (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 numero_interno VARCHAR(50) NOT NULL,
 proveedor_id BIGINT UNSIGNED NOT NULL,
 bodega_id BIGINT UNSIGNED NOT NULL,
 orden_compra_id BIGINT UNSIGNED NULL,
 tipo_documento VARCHAR(30) NULL,
 numero_documento VARCHAR(80) NULL,
 fecha_documento DATE NULL,
 estado VARCHAR(20) NOT NULL DEFAULT 'borrador',
 fecha_recepcion DATETIME NULL,
 creado_por BIGINT UNSIGNED NULL,
 confirmado_por BIGINT UNSIGNED NULL,
 confirmado_en DATETIME NULL,
 token_idempotencia CHAR(64) NULL,
 observaciones TEXT NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_hd_rx_numero_interno (numero_interno),
 UNIQUE KEY uq_hd_rx_idempotencia (token_idempotencia),
 KEY ix_hd_rx_proveedor_estado (proveedor_id,estado),
 KEY ix_hd_rx_bodega_fecha (bodega_id,fecha_recepcion),
 KEY ix_hd_rx_orden_compra (orden_compra_id),
 CONSTRAINT fk_hd_rx_proveedor FOREIGN KEY(proveedor_id)
   REFERENCES proveedores(id) ON DELETE RESTRICT,
 CONSTRAINT fk_hd_rx_bodega FOREIGN KEY(bodega_id)
   REFERENCES bodegas(id) ON DELETE RESTRICT,
 CONSTRAINT fk_hd_rx_orden_compra FOREIGN KEY(orden_compra_id)
   REFERENCES ordenes_compra(id) ON DELETE RESTRICT,
 CONSTRAINT fk_hd_rx_creador FOREIGN KEY(creado_por)
   REFERENCES usuarios(id) ON DELETE SET NULL,
 CONSTRAINT fk_hd_rx_confirmador FOREIGN KEY(confirmado_por)
   REFERENCES usuarios(id) ON DELETE SET NULL,
 CONSTRAINT chk_hd_rx_estado CHECK (estado IN ('borrador','confirmada')),
 CONSTRAINT chk_hd_rx_confirmacion CHECK (
   (estado='borrador' AND confirmado_en IS NULL AND confirmado_por IS NULL)
   OR (estado='confirmada' AND confirmado_en IS NOT NULL AND token_idempotencia IS NOT NULL)
 )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recepciones_mercaderia_detalle (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 recepcion_id BIGINT UNSIGNED NOT NULL,
 numero_linea SMALLINT UNSIGNED NOT NULL,
 producto_proveedor_id BIGINT UNSIGNED NOT NULL,
 presentacion_id BIGINT UNSIGNED NOT NULL,
 cantidad_presentaciones DECIMAL(15,3) NOT NULL,
 unidades_por_envase_snapshot DECIMAL(15,3) NOT NULL,
 cantidad_unidades_base DECIMAL(18,3) NOT NULL,
 costo_neto_unitario_base DECIMAL(15,4) NOT NULL,
 subtotal_neto DECIMAL(19,4) NOT NULL,
 movimiento_inventario_id BIGINT UNSIGNED NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_hd_rx_det_linea (recepcion_id,numero_linea),
 UNIQUE KEY uq_hd_rx_det_movimiento (movimiento_inventario_id),
 KEY ix_hd_rx_det_vinculo (producto_proveedor_id),
 KEY ix_hd_rx_det_presentacion (presentacion_id),
 CONSTRAINT fk_hd_rx_det_cabecera FOREIGN KEY(recepcion_id)
   REFERENCES recepciones_mercaderia(id) ON DELETE RESTRICT,
 CONSTRAINT fk_hd_rx_det_vinculo FOREIGN KEY(producto_proveedor_id)
   REFERENCES productos_proveedores(id) ON DELETE RESTRICT,
 CONSTRAINT fk_hd_rx_det_presentacion FOREIGN KEY(presentacion_id)
   REFERENCES presentaciones_productos(id) ON DELETE RESTRICT,
 CONSTRAINT fk_hd_rx_det_movimiento FOREIGN KEY(movimiento_inventario_id)
   REFERENCES movimientos_inventario(id) ON DELETE RESTRICT,
 CONSTRAINT chk_hd_rx_det_cantidad CHECK (
   cantidad_presentaciones>0 AND unidades_por_envase_snapshot>0
   AND cantidad_unidades_base>0
 ),
 CONSTRAINT chk_hd_rx_det_costo CHECK (
   costo_neto_unitario_base>=0 AND subtotal_neto>=0
 )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preflight de metadatos para ejecutar solamente cuando el diseño quede aprobado:
-- SHOW CREATE TABLE recepciones_mercaderia;
-- SHOW CREATE TABLE recepciones_mercaderia_detalle;
-- SELECT COUNT(*) FROM information_schema.TABLES
--  WHERE TABLE_SCHEMA=DATABASE()
--    AND TABLE_NAME IN ('recepciones_mercaderia','recepciones_mercaderia_detalle');
