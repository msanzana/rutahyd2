-- RUTA H&D2 V2 base nueva en InfinityFree: bloque 3/4
-- SQL solo de tablas en espanol; ejecutar en la nueva base seleccionada.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cotizaciones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 numero VARCHAR(50) NOT NULL UNIQUE,
 cliente_id BIGINT UNSIGNED NOT NULL,
 usuario_vendedor_id BIGINT UNSIGNED NULL,
 estado VARCHAR(40) NOT NULL DEFAULT 'borrador',
 vigente_hasta DATE NULL,
 subtotal_neto DECIMAL(15,2) NOT NULL DEFAULT 0,
 total_descuentos DECIMAL(15,2) NOT NULL DEFAULT 0,
 total_impuestos DECIMAL(15,2) NOT NULL DEFAULT 0,
 total_bruto DECIMAL(15,2) NOT NULL DEFAULT 0,
 observaciones TEXT NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_cotizaciones_1 FOREIGN KEY(cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
 CONSTRAINT fk_cotizaciones_2 FOREIGN KEY(usuario_vendedor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detalles_cotizaciones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 cotizacion_id BIGINT UNSIGNED NOT NULL,
 presentacion_id BIGINT UNSIGNED NOT NULL,
 cantidad DECIMAL(15,3) NOT NULL,
 precio_unitario_neto DECIMAL(15,4) NOT NULL,
 porcentaje_descuento DECIMAL(5,2) NOT NULL DEFAULT 0,
 subtotal_neto DECIMAL(15,2) NOT NULL,
 CONSTRAINT fk_detalles_cotizaciones_1 FOREIGN KEY(cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
 CONSTRAINT fk_detalles_cotizaciones_2 FOREIGN KEY(presentacion_id) REFERENCES presentaciones_productos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notas_venta (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 numero VARCHAR(50) NOT NULL UNIQUE,
 cliente_id BIGINT UNSIGNED NOT NULL,
 cotizacion_id BIGINT UNSIGNED NULL,
 usuario_vendedor_id BIGINT UNSIGNED NULL,
 ruta_reparto_id BIGINT UNSIGNED NULL,
 entrega_programada_en DATETIME NULL,
 direccion_entrega VARCHAR(255) NULL,
 estado VARCHAR(40) NOT NULL DEFAULT 'recibida',
 estado_pago VARCHAR(40) NOT NULL DEFAULT 'pendiente',
 medio_pago VARCHAR(40) NULL,
 subtotal_neto DECIMAL(15,2) NOT NULL DEFAULT 0,
 total_descuentos DECIMAL(15,2) NOT NULL DEFAULT 0,
 total_impuestos DECIMAL(15,2) NOT NULL DEFAULT 0,
 total_bruto DECIMAL(15,2) NOT NULL DEFAULT 0,
 monto_entregado_bruto DECIMAL(15,2) NOT NULL DEFAULT 0,
 observaciones TEXT NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY indice_notas_venta_1 (estado,estado_pago),
 KEY indice_notas_venta_2 (entrega_programada_en),
 CONSTRAINT fk_notas_venta_1 FOREIGN KEY(cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
 CONSTRAINT fk_notas_venta_2 FOREIGN KEY(cotizacion_id) REFERENCES cotizaciones(id) ON DELETE SET NULL,
 CONSTRAINT fk_notas_venta_3 FOREIGN KEY(usuario_vendedor_id) REFERENCES usuarios(id) ON DELETE SET NULL,
 CONSTRAINT fk_notas_venta_4 FOREIGN KEY(ruta_reparto_id) REFERENCES rutas_reparto(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detalles_notas_venta (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nota_venta_id BIGINT UNSIGNED NOT NULL,
 presentacion_id BIGINT UNSIGNED NOT NULL,
 cantidad_solicitada DECIMAL(15,3) NOT NULL,
 cantidad_entregada DECIMAL(15,3) NOT NULL DEFAULT 0,
 precio_unitario_neto DECIMAL(15,4) NOT NULL,
 porcentaje_descuento DECIMAL(5,2) NOT NULL DEFAULT 0,
 subtotal_neto DECIMAL(15,2) NOT NULL,
 CONSTRAINT fk_detalles_notas_venta_1 FOREIGN KEY(nota_venta_id) REFERENCES notas_venta(id) ON DELETE CASCADE,
 CONSTRAINT fk_detalles_notas_venta_2 FOREIGN KEY(presentacion_id) REFERENCES presentaciones_productos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historial_estados_notas_venta (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nota_venta_id BIGINT UNSIGNED NOT NULL,
 estado_anterior VARCHAR(40) NULL,
 estado_nuevo VARCHAR(40) NOT NULL,
 modificado_por BIGINT UNSIGNED NULL,
 observaciones TEXT NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_historial_estados_notas_venta_1 FOREIGN KEY(nota_venta_id) REFERENCES notas_venta(id) ON DELETE CASCADE,
 CONSTRAINT fk_historial_estados_notas_venta_2 FOREIGN KEY(modificado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagos_ventas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nota_venta_id BIGINT UNSIGNED NOT NULL,
 monto_bruto DECIMAL(15,2) NOT NULL,
 medio_pago VARCHAR(40) NOT NULL,
 pagado_en DATETIME NOT NULL,
 referencia VARCHAR(120) NULL,
 observaciones TEXT NULL,
 registrado_por BIGINT UNSIGNED NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY indice_pagos_ventas_1 (pagado_en),
 CONSTRAINT fk_pagos_ventas_1 FOREIGN KEY(nota_venta_id) REFERENCES notas_venta(id) ON DELETE RESTRICT,
 CONSTRAINT fk_pagos_ventas_2 FOREIGN KEY(registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guias_despacho (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 folio_interno VARCHAR(60) NOT NULL UNIQUE,
 bodega_origen_id BIGINT UNSIGNED NOT NULL,
 bodega_destino_id BIGINT UNSIGNED NOT NULL,
 nota_venta_id BIGINT UNSIGNED NULL,
 total_neto DECIMAL(15,2) NOT NULL DEFAULT 0,
 estado VARCHAR(40) NOT NULL DEFAULT 'generada',
 creado_por BIGINT UNSIGNED NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_guias_despacho_1 FOREIGN KEY(bodega_origen_id) REFERENCES bodegas(id) ON DELETE RESTRICT,
 CONSTRAINT fk_guias_despacho_2 FOREIGN KEY(bodega_destino_id) REFERENCES bodegas(id) ON DELETE RESTRICT,
 CONSTRAINT fk_guias_despacho_3 FOREIGN KEY(nota_venta_id) REFERENCES notas_venta(id) ON DELETE SET NULL,
 CONSTRAINT fk_guias_despacho_4 FOREIGN KEY(creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detalles_guias_despacho (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 guia_despacho_id BIGINT UNSIGNED NOT NULL,
 presentacion_id BIGINT UNSIGNED NOT NULL,
 cantidad DECIMAL(15,3) NOT NULL,
 precio_unitario_neto DECIMAL(15,4) NOT NULL,
 subtotal_neto DECIMAL(15,2) NOT NULL,
 CONSTRAINT fk_detalles_guias_despacho_1 FOREIGN KEY(guia_despacho_id) REFERENCES guias_despacho(id) ON DELETE CASCADE,
 CONSTRAINT fk_detalles_guias_despacho_2 FOREIGN KEY(presentacion_id) REFERENCES presentaciones_productos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
