-- MIGRACION DE NOMBRES DEL ESQUEMA ACTUAL A ESPANOL - 32 TABLAS DE ORIGEN
-- SOLO para la base InfinityFree seleccionada, si existe copia de seguridad verificada.
-- SQL de conversion versionado, NO ejecutado en servidor remoto.
-- IMPORTANTE: NO es idempotente. Aplique una sola vez y NO sobre un esquema ya traducido.
-- CUIDADO: renombrar tablas/columnas rompe el Laravel anterior hasta adaptar modelos y consultas.
-- No se eliminan ni copian registros, solo RENAME TABLE/RENAME COLUMN.
-- En phpMyAdmin seleccionar manualmente la base correcta antes de ejecutar.
SELECT DATABASE() AS base_seleccionada;

-- FASE 1: nombres de tablas; el motor InnoDB actualiza referencias de FK.
RENAME TABLE
 `permissions` TO `permisos`,
 `role_permissions` TO `roles_permisos`,
 `users` TO `usuarios`,
 `customers` TO `clientes`,
 `suppliers` TO `proveedores`,
 `product_categories` TO `categorias_productos`,
 `products` TO `productos`,
 `product_presentations` TO `presentaciones_productos`,
 `product_images` TO `imagenes_productos`,
 `customer_prices` TO `precios_clientes`,
 `warehouses` TO `bodegas`,
 `stock_balances` TO `saldos_inventario`,
 `stock_movements` TO `movimientos_inventario`,
 `promotions` TO `promociones`,
 `promotion_items` TO `detalles_promociones`,
 `purchase_orders` TO `ordenes_compra`,
 `purchase_order_items` TO `detalles_ordenes_compra`,
 `delivery_routes` TO `rutas_reparto`,
 `quotations` TO `cotizaciones`,
 `quotation_items` TO `detalles_cotizaciones`,
 `sale_notes` TO `notas_venta`,
 `sale_note_items` TO `detalles_notas_venta`,
 `sale_note_status_history` TO `historial_estados_notas_venta`,
 `sale_payments` TO `pagos_ventas`,
 `dispatch_guides` TO `guias_despacho`,
 `dispatch_guide_items` TO `detalles_guias_despacho`,
 `issued_documents` TO `documentos_emitidos`,
 `agenda_events` TO `eventos_agenda`,
 `monthly_sales_targets` TO `metas_mensuales_ventas`,
 `scheduled_workdays` TO `dias_laborales_programados`,
 `audit_logs` TO `registros_auditoria`;

-- FASE 2: columnas por tabla (manteniendo tipos de datos).
ALTER TABLE `roles`
 RENAME COLUMN `code` TO `codigo`,
 RENAME COLUMN `name` TO `nombre`,
 RENAME COLUMN `description` TO `descripcion`,
 RENAME COLUMN `created_at` TO `creado_en`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `permisos`
 RENAME COLUMN `code` TO `codigo`,
 RENAME COLUMN `description` TO `descripcion`;

ALTER TABLE `roles_permisos`
 RENAME COLUMN `role_id` TO `rol_id`,
 RENAME COLUMN `permission_id` TO `permiso_id`;

ALTER TABLE `usuarios`
 RENAME COLUMN `role_id` TO `rol_id`,
 RENAME COLUMN `name` TO `nombre`,
 RENAME COLUMN `email` TO `correo_electronico`,
 RENAME COLUMN `password_hash` TO `hash_contrasena`,
 RENAME COLUMN `active` TO `activo`,
 RENAME COLUMN `last_login_at` TO `ultimo_acceso_en`,
 RENAME COLUMN `created_at` TO `creado_en`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `clientes`
 RENAME COLUMN `code` TO `codigo`,
 RENAME COLUMN `name` TO `nombre`,
 RENAME COLUMN `tax_id` TO `rut`,
 RENAME COLUMN `email` TO `correo_electronico`,
 RENAME COLUMN `phone` TO `telefono`,
 RENAME COLUMN `address` TO `direccion`,
 RENAME COLUMN `commune` TO `comuna`,
 RENAME COLUMN `notes` TO `observaciones`,
 RENAME COLUMN `active` TO `activo`,
 RENAME COLUMN `created_at` TO `creado_en`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `proveedores`
 RENAME COLUMN `name` TO `nombre`,
 RENAME COLUMN `tax_id` TO `rut`,
 RENAME COLUMN `email` TO `correo_electronico`,
 RENAME COLUMN `phone` TO `telefono`,
 RENAME COLUMN `address` TO `direccion`,
 RENAME COLUMN `active` TO `activo`,
 RENAME COLUMN `created_at` TO `creado_en`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `categorias_productos`
 RENAME COLUMN `parent_id` TO `padre_id`,
 RENAME COLUMN `name` TO `nombre`,
 RENAME COLUMN `active` TO `activo`;

ALTER TABLE `productos`
 RENAME COLUMN `category_id` TO `categoria_id`,
 RENAME COLUMN `supplier_id` TO `proveedor_id`,
 RENAME COLUMN `name` TO `nombre`,
 RENAME COLUMN `description` TO `descripcion`,
 RENAME COLUMN `active` TO `activo`,
 RENAME COLUMN `created_at` TO `creado_en`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `presentaciones_productos`
 RENAME COLUMN `product_id` TO `producto_id`,
 RENAME COLUMN `barcode` TO `codigo_barras`,
 RENAME COLUMN `name` TO `nombre`,
 RENAME COLUMN `units_per_package` TO `unidades_por_envase`,
 RENAME COLUMN `price_net` TO `precio_neto`,
 RENAME COLUMN `tax_rate` TO `tasa_iva`,
 RENAME COLUMN `active` TO `activo`,
 RENAME COLUMN `created_at` TO `creado_en`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `imagenes_productos`
 RENAME COLUMN `product_id` TO `producto_id`,
 RENAME COLUMN `file_path` TO `ruta_archivo`,
 RENAME COLUMN `sort_order` TO `orden_visualizacion`,
 RENAME COLUMN `is_primary` TO `es_principal`,
 RENAME COLUMN `created_at` TO `creado_en`;

ALTER TABLE `precios_clientes`
 RENAME COLUMN `customer_id` TO `cliente_id`,
 RENAME COLUMN `presentation_id` TO `presentacion_id`,
 RENAME COLUMN `price_net` TO `precio_neto`,
 RENAME COLUMN `valid_from` TO `vigente_desde`,
 RENAME COLUMN `valid_until` TO `vigente_hasta`,
 RENAME COLUMN `created_at` TO `creado_en`;

ALTER TABLE `bodegas`
 RENAME COLUMN `code` TO `codigo`,
 RENAME COLUMN `name` TO `nombre`,
 RENAME COLUMN `type` TO `tipo`,
 RENAME COLUMN `active` TO `activo`;

ALTER TABLE `saldos_inventario`
 RENAME COLUMN `warehouse_id` TO `bodega_id`,
 RENAME COLUMN `presentation_id` TO `presentacion_id`,
 RENAME COLUMN `quantity` TO `cantidad`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `movimientos_inventario`
 RENAME COLUMN `presentation_id` TO `presentacion_id`,
 RENAME COLUMN `from_warehouse_id` TO `bodega_origen_id`,
 RENAME COLUMN `to_warehouse_id` TO `bodega_destino_id`,
 RENAME COLUMN `quantity` TO `cantidad`,
 RENAME COLUMN `movement_type` TO `tipo_movimiento`,
 RENAME COLUMN `reference_type` TO `tipo_referencia`,
 RENAME COLUMN `reference_id` TO `referencia_id`,
 RENAME COLUMN `notes` TO `observaciones`,
 RENAME COLUMN `performed_by` TO `realizado_por`,
 RENAME COLUMN `created_at` TO `creado_en`;

ALTER TABLE `promociones`
 RENAME COLUMN `title` TO `titulo`,
 RENAME COLUMN `target_customer_id` TO `cliente_destino_id`,
 RENAME COLUMN `valid_from` TO `vigente_desde`,
 RENAME COLUMN `valid_until` TO `vigente_hasta`,
 RENAME COLUMN `active` TO `activo`,
 RENAME COLUMN `created_by` TO `creado_por`,
 RENAME COLUMN `created_at` TO `creado_en`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `detalles_promociones`
 RENAME COLUMN `promotion_id` TO `promocion_id`,
 RENAME COLUMN `presentation_id` TO `presentacion_id`,
 RENAME COLUMN `discount_percent` TO `porcentaje_descuento`,
 RENAME COLUMN `price_net` TO `precio_neto`;

ALTER TABLE `ordenes_compra`
 RENAME COLUMN `number` TO `numero`,
 RENAME COLUMN `supplier_id` TO `proveedor_id`,
 RENAME COLUMN `status` TO `estado`,
 RENAME COLUMN `ordered_at` TO `solicitada_en`,
 RENAME COLUMN `received_at` TO `recibida_en`,
 RENAME COLUMN `subtotal_net` TO `subtotal_neto`,
 RENAME COLUMN `tax_total` TO `total_impuestos`,
 RENAME COLUMN `total_gross` TO `total_bruto`,
 RENAME COLUMN `notes` TO `observaciones`,
 RENAME COLUMN `created_by` TO `creado_por`,
 RENAME COLUMN `created_at` TO `creado_en`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `detalles_ordenes_compra`
 RENAME COLUMN `purchase_order_id` TO `orden_compra_id`,
 RENAME COLUMN `presentation_id` TO `presentacion_id`,
 RENAME COLUMN `quantity` TO `cantidad`,
 RENAME COLUMN `unit_cost_net` TO `costo_unitario_neto`,
 RENAME COLUMN `subtotal_net` TO `subtotal_neto`;

ALTER TABLE `rutas_reparto`
 RENAME COLUMN `code` TO `codigo`,
 RENAME COLUMN `name` TO `nombre`,
 RENAME COLUMN `driver_user_id` TO `usuario_repartidor_id`,
 RENAME COLUMN `vehicle_name` TO `nombre_vehiculo`,
 RENAME COLUMN `active` TO `activo`;

ALTER TABLE `cotizaciones`
 RENAME COLUMN `number` TO `numero`,
 RENAME COLUMN `customer_id` TO `cliente_id`,
 RENAME COLUMN `seller_user_id` TO `usuario_vendedor_id`,
 RENAME COLUMN `status` TO `estado`,
 RENAME COLUMN `valid_until` TO `vigente_hasta`,
 RENAME COLUMN `subtotal_net` TO `subtotal_neto`,
 RENAME COLUMN `discount_total` TO `total_descuentos`,
 RENAME COLUMN `tax_total` TO `total_impuestos`,
 RENAME COLUMN `total_gross` TO `total_bruto`,
 RENAME COLUMN `notes` TO `observaciones`,
 RENAME COLUMN `created_at` TO `creado_en`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `detalles_cotizaciones`
 RENAME COLUMN `quotation_id` TO `cotizacion_id`,
 RENAME COLUMN `presentation_id` TO `presentacion_id`,
 RENAME COLUMN `quantity` TO `cantidad`,
 RENAME COLUMN `unit_price_net` TO `precio_unitario_neto`,
 RENAME COLUMN `discount_percent` TO `porcentaje_descuento`,
 RENAME COLUMN `subtotal_net` TO `subtotal_neto`;

ALTER TABLE `notas_venta`
 RENAME COLUMN `number` TO `numero`,
 RENAME COLUMN `customer_id` TO `cliente_id`,
 RENAME COLUMN `quotation_id` TO `cotizacion_id`,
 RENAME COLUMN `seller_user_id` TO `usuario_vendedor_id`,
 RENAME COLUMN `delivery_route_id` TO `ruta_reparto_id`,
 RENAME COLUMN `delivery_at` TO `entrega_programada_en`,
 RENAME COLUMN `delivery_address` TO `direccion_entrega`,
 RENAME COLUMN `status` TO `estado`,
 RENAME COLUMN `payment_status` TO `estado_pago`,
 RENAME COLUMN `payment_method` TO `medio_pago`,
 RENAME COLUMN `subtotal_net` TO `subtotal_neto`,
 RENAME COLUMN `discount_total` TO `total_descuentos`,
 RENAME COLUMN `tax_total` TO `total_impuestos`,
 RENAME COLUMN `total_gross` TO `total_bruto`,
 RENAME COLUMN `amount_delivered_gross` TO `monto_entregado_bruto`,
 RENAME COLUMN `notes` TO `observaciones`,
 RENAME COLUMN `created_at` TO `creado_en`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `detalles_notas_venta`
 RENAME COLUMN `sale_note_id` TO `nota_venta_id`,
 RENAME COLUMN `presentation_id` TO `presentacion_id`,
 RENAME COLUMN `quantity_ordered` TO `cantidad_solicitada`,
 RENAME COLUMN `quantity_delivered` TO `cantidad_entregada`,
 RENAME COLUMN `unit_price_net` TO `precio_unitario_neto`,
 RENAME COLUMN `discount_percent` TO `porcentaje_descuento`,
 RENAME COLUMN `subtotal_net` TO `subtotal_neto`;

ALTER TABLE `historial_estados_notas_venta`
 RENAME COLUMN `sale_note_id` TO `nota_venta_id`,
 RENAME COLUMN `previous_status` TO `estado_anterior`,
 RENAME COLUMN `new_status` TO `estado_nuevo`,
 RENAME COLUMN `changed_by` TO `modificado_por`,
 RENAME COLUMN `notes` TO `observaciones`,
 RENAME COLUMN `created_at` TO `creado_en`;

ALTER TABLE `pagos_ventas`
 RENAME COLUMN `sale_note_id` TO `nota_venta_id`,
 RENAME COLUMN `amount_gross` TO `monto_bruto`,
 RENAME COLUMN `method` TO `medio_pago`,
 RENAME COLUMN `payment_at` TO `pagado_en`,
 RENAME COLUMN `reference` TO `referencia`,
 RENAME COLUMN `notes` TO `observaciones`,
 RENAME COLUMN `recorded_by` TO `registrado_por`,
 RENAME COLUMN `created_at` TO `creado_en`;

ALTER TABLE `guias_despacho`
 RENAME COLUMN `internal_folio` TO `folio_interno`,
 RENAME COLUMN `from_warehouse_id` TO `bodega_origen_id`,
 RENAME COLUMN `to_warehouse_id` TO `bodega_destino_id`,
 RENAME COLUMN `sale_note_id` TO `nota_venta_id`,
 RENAME COLUMN `total_net` TO `total_neto`,
 RENAME COLUMN `status` TO `estado`,
 RENAME COLUMN `created_by` TO `creado_por`,
 RENAME COLUMN `created_at` TO `creado_en`;

ALTER TABLE `detalles_guias_despacho`
 RENAME COLUMN `dispatch_guide_id` TO `guia_despacho_id`,
 RENAME COLUMN `presentation_id` TO `presentacion_id`,
 RENAME COLUMN `quantity` TO `cantidad`,
 RENAME COLUMN `unit_price_net` TO `precio_unitario_neto`,
 RENAME COLUMN `subtotal_net` TO `subtotal_neto`;

ALTER TABLE `documentos_emitidos`
 RENAME COLUMN `sale_note_id` TO `nota_venta_id`,
 RENAME COLUMN `document_type` TO `tipo_documento`,
 RENAME COLUMN `issuer_system` TO `sistema_emisor`,
 RENAME COLUMN `issue_at` TO `emitido_en`,
 RENAME COLUMN `status` TO `estado`,
 RENAME COLUMN `pdf_path` TO `ruta_pdf`,
 RENAME COLUMN `sii_track_id` TO `identificador_seguimiento_sii`,
 RENAME COLUMN `created_at` TO `creado_en`;

ALTER TABLE `eventos_agenda`
 RENAME COLUMN `event_type` TO `tipo_evento`,
 RENAME COLUMN `title` TO `titulo`,
 RENAME COLUMN `description` TO `descripcion`,
 RENAME COLUMN `starts_at` TO `inicia_en`,
 RENAME COLUMN `ends_at` TO `finaliza_en`,
 RENAME COLUMN `customer_id` TO `cliente_id`,
 RENAME COLUMN `created_by` TO `creado_por`,
 RENAME COLUMN `created_at` TO `creado_en`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `metas_mensuales_ventas`
 RENAME COLUMN `target_month` TO `mes_objetivo`,
 RENAME COLUMN `target_gross` TO `meta_bruta`,
 RENAME COLUMN `updated_by` TO `actualizado_por`,
 RENAME COLUMN `created_at` TO `creado_en`,
 RENAME COLUMN `updated_at` TO `actualizado_en`;

ALTER TABLE `dias_laborales_programados`
 RENAME COLUMN `target_month` TO `mes_objetivo`,
 RENAME COLUMN `work_date` TO `fecha_laboral`,
 RENAME COLUMN `enabled` TO `habilitado`,
 RENAME COLUMN `notes` TO `observaciones`;

ALTER TABLE `registros_auditoria`
 RENAME COLUMN `user_id` TO `usuario_id`,
 RENAME COLUMN `action` TO `accion`,
 RENAME COLUMN `entity_type` TO `tipo_entidad`,
 RENAME COLUMN `entity_id` TO `entidad_id`,
 RENAME COLUMN `old_values` TO `valores_anteriores`,
 RENAME COLUMN `new_values` TO `valores_nuevos`,
 RENAME COLUMN `created_at` TO `creado_en`;

-- FASE 3: dos tablas nuevas si el servidor aun no tenia las tablas inglesas sales/sale_details.
CREATE TABLE IF NOT EXISTS ventas (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 nota_venta_id BIGINT UNSIGNED NULL,
 cliente_id BIGINT UNSIGNED NOT NULL,
 numero_venta VARCHAR(50) NOT NULL,
 estado VARCHAR(40) NOT NULL DEFAULT 'draft',
 codigo_moneda CHAR(3) NOT NULL DEFAULT 'CLP',
 fecha_emision DATE NOT NULL,
 tipo_dte SMALLINT UNSIGNED NOT NULL,
 dte_id_externo CHAR(36) NULL,
 dte_empresa_id BIGINT UNSIGNED NULL,
 dte_sistema_externo_id BIGINT UNSIGNED NULL,
 dte_folio_propuesto BIGINT UNSIGNED NULL,
 dte_tipo_documento_sii_propuesto SMALLINT UNSIGNED NULL,
 dte_numero_sucursal INT UNSIGNED NULL,
 dte_numero_establecimiento INT UNSIGNED NULL,
 dte_codigo_sucursal_externa VARCHAR(100) NULL,
 dte_documento_remoto_id BIGINT UNSIGNED NULL,
 dte_folio BIGINT UNSIGNED NULL,
 dte_estado VARCHAR(40) NOT NULL DEFAULT 'not_requested',
 dte_ambiente_sii VARCHAR(10) NULL,
 documento_receptor VARCHAR(20) NOT NULL,
 nombre_receptor VARCHAR(120) NOT NULL,
 giro_receptor VARCHAR(150) NULL,
 direccion_receptor VARCHAR(150) NULL,
 ciudad_receptor_id BIGINT UNSIGNED NULL,
 correo_receptor VARCHAR(150) NULL,
 monto_neto DECIMAL(18,2) NOT NULL DEFAULT 0,
 monto_exento DECIMAL(18,2) NOT NULL DEFAULT 0,
 monto_impuestos DECIMAL(18,2) NOT NULL DEFAULT 0,
 monto_descuento DECIMAL(18,2) NOT NULL DEFAULT 0,
 monto_total DECIMAL(18,2) NOT NULL DEFAULT 0,
 datos_encabezado_json JSON NULL,
 datos_referencias_json JSON NULL,
 dte_solicitud_json JSON NULL,
 dte_respuesta_json JSON NULL,
 dte_codigo_ultimo_error VARCHAR(50) NULL,
 dte_mensaje_ultimo_error TEXT NULL,
 dte_solicitado_en DATETIME NULL,
 dte_creado_en DATETIME NULL,
 dte_aceptado_en DATETIME NULL,
 dte_rechazado_en DATETIME NULL,
 observaciones TEXT NULL,
 creado_por BIGINT UNSIGNED NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY unico_ventas_1(numero_venta),
 UNIQUE KEY unico_ventas_2(nota_venta_id),
 UNIQUE KEY unico_ventas_3(dte_id_externo),
 KEY indice_ventas_4 (cliente_id,fecha_emision),
 KEY indice_ventas_5 (dte_estado,tipo_dte),
 KEY indice_ventas_6 (dte_documento_remoto_id),
 CONSTRAINT fk_ventas_1 FOREIGN KEY(cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
 CONSTRAINT fk_ventas_2 FOREIGN KEY(nota_venta_id) REFERENCES notas_venta(id) ON DELETE SET NULL,
 CONSTRAINT fk_ventas_3 FOREIGN KEY(creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detalles_ventas (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 venta_id BIGINT UNSIGNED NOT NULL,
 detalle_nota_venta_id BIGINT UNSIGNED NULL,
 producto_id BIGINT UNSIGNED NOT NULL,
 numero_linea INT UNSIGNED NOT NULL,
 cantidad DECIMAL(18,4) NOT NULL DEFAULT 1,
 precio_unitario DECIMAL(18,4) NOT NULL DEFAULT 0,
 porcentaje_descuento DECIMAL(8,4) NOT NULL DEFAULT 0,
 monto_descuento DECIMAL(18,2) NOT NULL DEFAULT 0,
 exento_impuestos TINYINT(1) NOT NULL DEFAULT 0,
 monto_linea DECIMAL(18,2) NOT NULL DEFAULT 0,
 datos_adicionales_json JSON NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY unico_detalles_ventas_1(venta_id,numero_linea),
 KEY indice_detalles_ventas_2 (producto_id),
 CONSTRAINT fk_detalles_ventas_1 FOREIGN KEY(venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
 CONSTRAINT fk_detalles_ventas_2 FOREIGN KEY(detalle_nota_venta_id) REFERENCES detalles_notas_venta(id) ON DELETE SET NULL,
 CONSTRAINT fk_detalles_ventas_3 FOREIGN KEY(producto_id) REFERENCES productos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FASE FINAL: controlar cantidades y claves foraneas.
SELECT COUNT(*) AS numero_tablas FROM information_schema.tables
 WHERE table_schema=DATABASE() AND table_type='BASE TABLE';
SELECT table_name,column_name,referenced_table_name
 FROM information_schema.key_column_usage
 WHERE table_schema=DATABASE() AND referenced_table_name IS NOT NULL
 ORDER BY table_name,column_name;
