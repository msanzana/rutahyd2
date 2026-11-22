-- Ruta H&D V2 · DDL 20 / Catálogo de códigos, asociación proveedor-producto
-- y códigos de barras de venta/compra.
-- Aplicar SOLO después de respaldar la base de producción y verificar estructura.
-- No elimina campos históricos (gramaje_id, presentaciones.codigo_barras,
-- productos.proveedor_id). Se abandonarán funcionalmente y no se escribirán
-- desde los CRUD nuevos. No utiliza Laravel migrations.
-- Reejecutable: CREATE TABLE IF NOT EXISTS. No realizar cambios DML aquí.

CREATE TABLE IF NOT EXISTS tipos_codigos_barras (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 codigo VARCHAR(40) NOT NULL,
 nombre VARCHAR(100) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 PRIMARY KEY (id),
 UNIQUE KEY uq_hd_tipos_cb_codigo (codigo),
 UNIQUE KEY uq_hd_tipos_cb_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos_proveedores (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 producto_id BIGINT UNSIGNED NOT NULL,
 proveedor_id BIGINT UNSIGNED NOT NULL,
 codigo_proveedor_producto VARCHAR(80) NULL COMMENT 'Código interno asignado por el proveedor para este producto',
 costo_proveedor DECIMAL(15,4) NULL COMMENT 'Costo neto por la unidad base acordada con el proveedor',
 ultima_recepcion_id BIGINT UNSIGNED NULL COMMENT 'Referencia a recepción futura; FK pendiente de módulo Recepciones',
 fecha_ultima_recepcion DATETIME NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_hd_pp_prod_prov (producto_id,proveedor_id),
 UNIQUE KEY uq_hd_pp_prov_codigo (proveedor_id,codigo_proveedor_producto),
 KEY ix_hd_pp_proveedor_activo (proveedor_id,activo),
 KEY ix_hd_pp_producto_activo (producto_id,activo),
 CONSTRAINT fk_hd_pp_producto FOREIGN KEY(producto_id)
   REFERENCES productos(id) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT fk_hd_pp_proveedor FOREIGN KEY(proveedor_id)
   REFERENCES proveedores(id) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT chk_hd_pp_costo CHECK (costo_proveedor IS NULL OR costo_proveedor >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos_codigos_barra_venta (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 producto_id BIGINT UNSIGNED NOT NULL,
 tipo_codigo_barra_id BIGINT UNSIGNED NOT NULL,
 codigo_barras VARCHAR(100) NOT NULL,
 unidades_por_codigo DECIMAL(15,3) NOT NULL DEFAULT 1,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_hd_codigo_barras_venta_global (codigo_barras),
 KEY ix_hd_cbv_producto (producto_id,activo),
 KEY ix_hd_cbv_tipo (tipo_codigo_barra_id),
 CONSTRAINT fk_hd_cbv_producto FOREIGN KEY(producto_id)
   REFERENCES productos(id) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT fk_hd_cbv_tipo FOREIGN KEY(tipo_codigo_barra_id)
   REFERENCES tipos_codigos_barras(id) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT chk_hd_cbv_unidades CHECK (unidades_por_codigo > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos_codigos_barra_compra (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 producto_proveedor_id BIGINT UNSIGNED NOT NULL
   COMMENT 'Determina tanto el SKU del producto como el código del proveedor',
 tipo_codigo_barra_id BIGINT UNSIGNED NOT NULL,
 codigo_barras VARCHAR(100) NOT NULL,
 unidades_por_codigo DECIMAL(15,3) NOT NULL DEFAULT 1,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_hd_codigo_barras_compra_vinculo (producto_proveedor_id,codigo_barras),
 KEY ix_hd_cbc_barra (codigo_barras),
 KEY ix_hd_cbc_tipo (tipo_codigo_barra_id),
 CONSTRAINT fk_hd_cbc_producto_proveedor FOREIGN KEY(producto_proveedor_id)
   REFERENCES productos_proveedores(id) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT fk_hd_cbc_tipo FOREIGN KEY(tipo_codigo_barra_id)
   REFERENCES tipos_codigos_barras(id) ON DELETE RESTRICT ON UPDATE CASCADE,
 CONSTRAINT chk_hd_cbc_unidades CHECK (unidades_por_codigo > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DISEÑO NORMALIZADO: los códigos de producto (productos.sku) y proveedor
-- (productos_proveedores.codigo_proveedor_producto) se consultan por JOIN;
-- no se duplican en cada código de barras de compra para evitar inconsistencias.
-- Un mismo código de compra puede pertenecer a varios proveedores, cuando
-- es el mismo artículo comercial. En venta es único globalmente.
-- Recepciones: NO inventar un FK a tabla inexistente. Mantener
-- ultima_recepcion_id NULL hasta implementar el CRUD transaccional correspondiente.
