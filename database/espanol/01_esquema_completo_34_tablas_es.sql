-- RUTA H&D2 V2 - ESQUEMA COMPLETO EN ESPANOL (34 TABLAS)
-- DDL nuevo para MySQL 8 / MariaDB 11.x. Origen: schema V1 versionado.
-- Todas las tablas y columnas en espanol; solo siglas tecnicas id, sku, dte, iva, sii.
-- NO ejecutar sobre InfinityFree mientras no exista respaldo validado y backend compatible.
-- La sintaxis SQL (CREATE, FOREIGN KEY, etc.) es estandar y permanece en ingles.
-- Esta base local NO es la base real de InfinityFree.

CREATE DATABASE IF NOT EXISTS rutahyd2_es_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rutahyd2_es_local;
SET NAMES utf8mb4;

SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS roles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(40) NOT NULL UNIQUE,
 nombre VARCHAR(100) NOT NULL,
 descripcion VARCHAR(255) NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS permisos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(100) NOT NULL UNIQUE,
 descripcion VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS roles_permisos (
 rol_id BIGINT UNSIGNED NOT NULL,
 permiso_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(rol_id,permiso_id),
 CONSTRAINT fk_roles_permisos_1 FOREIGN KEY(rol_id) REFERENCES roles(id) ON DELETE CASCADE,
 CONSTRAINT fk_roles_permisos_2 FOREIGN KEY(permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS usuarios (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 rol_id BIGINT UNSIGNED NOT NULL,
 nombre VARCHAR(160) NOT NULL,
 correo_electronico VARCHAR(190) NOT NULL UNIQUE,
 hash_contrasena VARCHAR(255) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 ultimo_acceso_en DATETIME NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY indice_usuarios_1 (rol_id),
 CONSTRAINT fk_usuarios_1 FOREIGN KEY(rol_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS clientes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(50) NULL UNIQUE,
 nombre VARCHAR(190) NOT NULL,
 rut VARCHAR(25) NULL,
 correo_electronico VARCHAR(190) NULL,
 telefono VARCHAR(50) NULL,
 direccion VARCHAR(255) NULL,
 comuna VARCHAR(100) NULL,
 sector VARCHAR(120) NULL,
 observaciones TEXT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY indice_clientes_1 (nombre), KEY indice_clientes_2 (rut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS proveedores (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(190) NOT NULL,
 rut VARCHAR(25) NULL,
 correo_electronico VARCHAR(190) NULL,
 telefono VARCHAR(50) NULL,
 direccion VARCHAR(255) NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS categorias_productos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 padre_id BIGINT UNSIGNED NULL,
 nombre VARCHAR(150) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 CONSTRAINT fk_categorias_productos_1 FOREIGN KEY(padre_id) REFERENCES categorias_productos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS productos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 categoria_id BIGINT UNSIGNED NULL,
 proveedor_id BIGINT UNSIGNED NULL,
 sku VARCHAR(80) NULL UNIQUE,
 nombre VARCHAR(190) NOT NULL,
 descripcion TEXT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY indice_productos_1 (nombre),
 CONSTRAINT fk_productos_1 FOREIGN KEY(categoria_id) REFERENCES categorias_productos(id) ON DELETE SET NULL,
 CONSTRAINT fk_productos_2 FOREIGN KEY(proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS presentaciones_productos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 producto_id BIGINT UNSIGNED NOT NULL,
 codigo_barras VARCHAR(80) NULL UNIQUE,
 nombre VARCHAR(150) NOT NULL,
 unidades_por_envase DECIMAL(15,3) NOT NULL DEFAULT 1,
 precio_neto DECIMAL(15,4) NOT NULL DEFAULT 0,
 tasa_iva DECIMAL(5,2) NOT NULL DEFAULT 19.00,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY unico_presentaciones_productos_1(producto_id,nombre),
 CONSTRAINT fk_presentaciones_productos_1 FOREIGN KEY(producto_id) REFERENCES productos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS imagenes_productos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 producto_id BIGINT UNSIGNED NOT NULL,
 ruta_archivo VARCHAR(500) NOT NULL,
 orden_visualizacion TINYINT UNSIGNED NOT NULL DEFAULT 1,
 es_principal TINYINT(1) NOT NULL DEFAULT 0,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY indice_imagenes_productos_1 (producto_id,orden_visualizacion),
 CONSTRAINT fk_imagenes_productos_1 FOREIGN KEY(producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS precios_clientes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 cliente_id BIGINT UNSIGNED NOT NULL,
 presentacion_id BIGINT UNSIGNED NOT NULL,
 precio_neto DECIMAL(15,4) NOT NULL,
 vigente_desde DATE NULL,
 vigente_hasta DATE NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unico_precios_clientes_1(cliente_id,presentacion_id),
 CONSTRAINT fk_precios_clientes_1 FOREIGN KEY(cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
 CONSTRAINT fk_precios_clientes_2 FOREIGN KEY(presentacion_id) REFERENCES presentaciones_productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS rutas_reparto (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(40) NOT NULL UNIQUE,
 nombre VARCHAR(140) NOT NULL,
 usuario_repartidor_id BIGINT UNSIGNED NULL,
 nombre_vehiculo VARCHAR(120) NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 CONSTRAINT fk_rutas_reparto_1 FOREIGN KEY(usuario_repartidor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
CREATE TABLE IF NOT EXISTS documentos_emitidos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nota_venta_id BIGINT UNSIGNED NULL,
 tipo_documento VARCHAR(40) NOT NULL,
 folio VARCHAR(70) NOT NULL,
 sistema_emisor VARCHAR(40) NOT NULL DEFAULT 'interno',
 emitido_en DATETIME NULL,
 estado VARCHAR(40) NOT NULL DEFAULT 'preparado',
 ruta_pdf VARCHAR(500) NULL,
 identificador_seguimiento_sii VARCHAR(150) NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unico_documentos_emitidos_1(tipo_documento,folio,sistema_emisor),
 CONSTRAINT fk_documentos_emitidos_1 FOREIGN KEY(nota_venta_id) REFERENCES notas_venta(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS eventos_agenda (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tipo_evento VARCHAR(60) NOT NULL DEFAULT 'evento_nuevo',
 titulo VARCHAR(190) NOT NULL,
 descripcion TEXT NULL,
 inicia_en DATETIME NOT NULL,
 finaliza_en DATETIME NULL,
 cliente_id BIGINT UNSIGNED NULL,
 creado_por BIGINT UNSIGNED NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY indice_eventos_agenda_1 (inicia_en),
 CONSTRAINT fk_eventos_agenda_1 FOREIGN KEY(cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
 CONSTRAINT fk_eventos_agenda_2 FOREIGN KEY(creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS metas_mensuales_ventas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 mes_objetivo DATE NOT NULL,
 meta_bruta DECIMAL(15,2) NOT NULL DEFAULT 0,
 actualizado_por BIGINT UNSIGNED NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY unico_metas_mensuales_ventas_1(mes_objetivo),
 CONSTRAINT fk_metas_mensuales_ventas_1 FOREIGN KEY(actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dias_laborales_programados (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 mes_objetivo DATE NOT NULL,
 fecha_laboral DATE NOT NULL UNIQUE,
 habilitado TINYINT(1) NOT NULL DEFAULT 1,
 observaciones VARCHAR(255) NULL,
 KEY indice_dias_laborales_programados_1 (mes_objetivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS registros_auditoria (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 usuario_id BIGINT UNSIGNED NULL,
 accion VARCHAR(80) NOT NULL,
 tipo_entidad VARCHAR(80) NOT NULL,
 entidad_id BIGINT UNSIGNED NULL,
 valores_anteriores LONGTEXT NULL,
 valores_nuevos LONGTEXT NULL,
 creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY indice_registros_auditoria_1 (tipo_entidad,entidad_id),
 CONSTRAINT fk_registros_auditoria_1 FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ventas (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 nota_venta_id BIGINT UNSIGNED NULL,
 cliente_id BIGINT UNSIGNED NOT NULL,
 numero_venta VARCHAR(50) NOT NULL,
 estado VARCHAR(40) NOT NULL DEFAULT 'borrador',
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
 dte_estado VARCHAR(40) NOT NULL DEFAULT 'no_solicitado',
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
