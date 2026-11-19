-- RUTA H&D2 V2 base nueva en InfinityFree: bloque 4/4
-- SQL solo de tablas en espanol; ejecutar en la nueva base seleccionada.
SET NAMES utf8mb4;

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
