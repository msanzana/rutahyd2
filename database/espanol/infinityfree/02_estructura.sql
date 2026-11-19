-- RUTA H&D2 V2 base nueva en InfinityFree: bloque 2/4
-- SQL solo de tablas en espanol; ejecutar en la nueva base seleccionada.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS bodegas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(30) NOT NULL UNIQUE,
 nombre VARCHAR(130) NOT NULL,
 tipo VARCHAR(40) NOT NULL DEFAULT 'general',
 activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saldos_inventario (
 bodega_id BIGINT UNSIGNED NOT NULL,
 presentacion_id BIGINT UNSIGNED NOT NULL,
 cantidad DECIMAL(15,3) NOT NULL DEFAULT 0,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(bodega_id,presentacion_id),
 CONSTRAINT fk_saldos_inventario_1 FOREIGN KEY(bodega_id) REFERENCES bodegas(id) ON DELETE RESTRICT,
 CONSTRAINT fk_saldos_inventario_2 FOREIGN KEY(presentacion_id) REFERENCES presentaciones_productos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimientos_inventario (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 presentacion_id BIGINT UNSIGNED NOT NULL,
 bodega_origen_id BIGINT UNSIGNED NULL,
 bodega_destino_id BIGINT UNSIGNED NULL,
 cantidad DECIMAL(15,3) NOT NULL,
 tipo_movimiento VARCHAR(40) NOT NULL,
 tipo_referencia VARCHAR(60) NULL,
 referencia_id BIGINT UNSIGNED NULL,
 observaciones TEXT NULL,
 realizado_por BIGINT UNSIGNED NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY indice_movimientos_inventario_1 (tipo_referencia,referencia_id),
 KEY indice_movimientos_inventario_2 (creado_en),
 CONSTRAINT fk_movimientos_inventario_1 FOREIGN KEY(presentacion_id) REFERENCES presentaciones_productos(id) ON DELETE RESTRICT,
 CONSTRAINT fk_movimientos_inventario_2 FOREIGN KEY(bodega_origen_id) REFERENCES bodegas(id) ON DELETE RESTRICT,
 CONSTRAINT fk_movimientos_inventario_3 FOREIGN KEY(bodega_destino_id) REFERENCES bodegas(id) ON DELETE RESTRICT,
 CONSTRAINT fk_movimientos_inventario_4 FOREIGN KEY(realizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promociones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 titulo VARCHAR(190) NOT NULL,
 cliente_destino_id BIGINT UNSIGNED NULL,
 vigente_desde DATETIME NOT NULL,
 vigente_hasta DATETIME NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_por BIGINT UNSIGNED NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY indice_promociones_1 (vigente_desde,vigente_hasta),
 CONSTRAINT fk_promociones_1 FOREIGN KEY(cliente_destino_id) REFERENCES clientes(id) ON DELETE CASCADE,
 CONSTRAINT fk_promociones_2 FOREIGN KEY(creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detalles_promociones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 promocion_id BIGINT UNSIGNED NOT NULL,
 presentacion_id BIGINT UNSIGNED NOT NULL,
 porcentaje_descuento DECIMAL(5,2) NULL,
 precio_neto DECIMAL(15,4) NULL,
 UNIQUE KEY unico_detalles_promociones_1(promocion_id,presentacion_id),
 CONSTRAINT fk_detalles_promociones_1 FOREIGN KEY(promocion_id) REFERENCES promociones(id) ON DELETE CASCADE,
 CONSTRAINT fk_detalles_promociones_2 FOREIGN KEY(presentacion_id) REFERENCES presentaciones_productos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordenes_compra (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 numero VARCHAR(50) NOT NULL UNIQUE,
 proveedor_id BIGINT UNSIGNED NOT NULL,
 estado VARCHAR(40) NOT NULL DEFAULT 'borrador',
 solicitada_en DATETIME NULL,
 recibida_en DATETIME NULL,
 subtotal_neto DECIMAL(15,2) NOT NULL DEFAULT 0,
 total_impuestos DECIMAL(15,2) NOT NULL DEFAULT 0,
 total_bruto DECIMAL(15,2) NOT NULL DEFAULT 0,
 observaciones TEXT NULL,
 creado_por BIGINT UNSIGNED NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_ordenes_compra_1 FOREIGN KEY(proveedor_id) REFERENCES proveedores(id) ON DELETE RESTRICT,
 CONSTRAINT fk_ordenes_compra_2 FOREIGN KEY(creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detalles_ordenes_compra (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 orden_compra_id BIGINT UNSIGNED NOT NULL,
 presentacion_id BIGINT UNSIGNED NOT NULL,
 cantidad DECIMAL(15,3) NOT NULL,
 costo_unitario_neto DECIMAL(15,4) NOT NULL,
 subtotal_neto DECIMAL(15,2) NOT NULL,
 CONSTRAINT fk_detalles_ordenes_compra_1 FOREIGN KEY(orden_compra_id) REFERENCES ordenes_compra(id) ON DELETE CASCADE,
 CONSTRAINT fk_detalles_ordenes_compra_2 FOREIGN KEY(presentacion_id) REFERENCES presentaciones_productos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rutas_reparto (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(40) NOT NULL UNIQUE,
 nombre VARCHAR(140) NOT NULL,
 usuario_repartidor_id BIGINT UNSIGNED NULL,
 nombre_vehiculo VARCHAR(120) NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 CONSTRAINT fk_rutas_reparto_1 FOREIGN KEY(usuario_repartidor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
