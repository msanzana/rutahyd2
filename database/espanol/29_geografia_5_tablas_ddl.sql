-- Ruta H&D V2 · DDL29 · Geografía normalizada país → región → provincia → comuna → ciudad
-- Ejecutar MANUALMENTE en phpMyAdmin, base if0_43460400_rutahyd2_es, previo respaldo.
-- DDL29 debe ejecutarse antes de DDL30 (semillas) y DDL31/32 (entidades).
-- Datos Chile: CUT 16 regiones / 56 provincias / 346 comunas; fuente datos abiertos Gobierno Digital.
-- Ciudad es una localidad dentro de comuna; NO se debe confundir con comuna.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS paises (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo_iso2 CHAR(2) NOT NULL,
  codigo_iso3 CHAR(3) NULL,
  nombre VARCHAR(120) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY(id), UNIQUE KEY uq_hd_pais_iso2(codigo_iso2),
  CONSTRAINT chk_hd_pais_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS regiones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pais_id BIGINT UNSIGNED NOT NULL,
  codigo_cut VARCHAR(3) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY(id), UNIQUE KEY uq_hd_region_cut(codigo_cut),
  KEY ix_hd_region_pais(pais_id),
  CONSTRAINT fk_hd_region_pais FOREIGN KEY(pais_id) REFERENCES paises(id) ON DELETE RESTRICT,
  CONSTRAINT chk_hd_region_activo CHECK(activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provincias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  region_id BIGINT UNSIGNED NOT NULL,
  codigo_cut VARCHAR(4) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY(id), UNIQUE KEY uq_hd_provincia_cut(codigo_cut),
  KEY ix_hd_provincia_region(region_id),
  CONSTRAINT fk_hd_provincia_region FOREIGN KEY(region_id) REFERENCES regiones(id) ON DELETE RESTRICT,
  CONSTRAINT chk_hd_provincia_activo CHECK(activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comunas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provincia_id BIGINT UNSIGNED NOT NULL,
  codigo_cut VARCHAR(6) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY(id), UNIQUE KEY uq_hd_comuna_cut(codigo_cut),
  KEY ix_hd_comuna_provincia(provincia_id),
  CONSTRAINT fk_hd_comuna_provincia FOREIGN KEY(provincia_id) REFERENCES provincias(id) ON DELETE RESTRICT,
  CONSTRAINT chk_hd_comuna_activo CHECK(activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ciudades (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  comuna_id BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  tipo_localidad VARCHAR(36) NOT NULL DEFAULT 'CIUDAD',
  fuente VARCHAR(160) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY(id),
  UNIQUE KEY uq_hd_ciudad_comuna_nombre(comuna_id,nombre),
  KEY ix_hd_ciudad_comuna_activa(comuna_id,activo),
  CONSTRAINT fk_hd_ciudad_comuna FOREIGN KEY(comuna_id) REFERENCES comunas(id) ON DELETE RESTRICT,
  CONSTRAINT chk_hd_ciudad_activo CHECK(activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación previa a importar datos:
SHOW TABLES LIKE 'paises';
SHOW TABLES LIKE 'regiones';
SHOW TABLES LIKE 'provincias';
SHOW TABLES LIKE 'comunas';
SHOW TABLES LIKE 'ciudades';
