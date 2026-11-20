-- RutaH&D2 · NUEVOS catálogos comerciales (migración aditiva, MySQL 8).
-- NO ejecutado en InfinityFree. Respaldar y revisar antes de aplicar sobre base real.
-- Orden: ejecutar este script antes de 14_productos_clasificacion_campos.sql.
-- Se conserva categorias_productos y productos.categoria_id para migración futura.
CREATE TABLE IF NOT EXISTS rubros (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(150) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_rubros_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departamentos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 rubro_id BIGINT UNSIGNED NOT NULL,
 nombre VARCHAR(150) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_departamentos_rubro_nombre (rubro_id,nombre),
 CONSTRAINT fk_hd_departamentos_rubro FOREIGN KEY (rubro_id) REFERENCES rubros(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS familias (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 departamento_id BIGINT UNSIGNED NOT NULL,
 nombre VARCHAR(150) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_familias_departamento_nombre (departamento_id,nombre),
 CONSTRAINT fk_hd_familias_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lineas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 familia_id BIGINT UNSIGNED NOT NULL,
 nombre VARCHAR(150) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_lineas_familia_nombre (familia_id,nombre),
 CONSTRAINT fk_hd_lineas_familia FOREIGN KEY (familia_id) REFERENCES familias(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marcas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(150) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_marcas_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modelos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 marca_id BIGINT UNSIGNED NOT NULL,
 nombre VARCHAR(150) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_modelos_marca_nombre (marca_id,nombre),
 CONSTRAINT fk_hd_modelos_marca FOREIGN KEY (marca_id) REFERENCES marcas(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipos_unidad (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_tipos_unidad_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gramajes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 gramos DECIMAL(12,3) NOT NULL,
 nombre VARCHAR(100) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_gramajes_gramos (gramos),
 CONSTRAINT chk_gramajes_gramos CHECK (gramos >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipos_envasado (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_tipos_envasado_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS condiciones_tributarias (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(10) NOT NULL,
 nombre VARCHAR(100) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_condiciones_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO condiciones_tributarias (codigo,nombre,activo)
VALUES ('AFECTO','Afecto',1),('EXENTO','Exento',1)
ON DUPLICATE KEY UPDATE codigo=VALUES(codigo);

-- Nota: 'Exento' representa un atributo tributario de producto, no una
-- garantía de exención legal. Validar normativa e integración DTE antes de facturar.
