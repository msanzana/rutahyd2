-- Ruta H&D V39 · DDL33 · Catálogo nacional de actividades económicas (giros SII)
-- SOLO EJECUCIÓN MANUAL EN phpMyAdmin, base oficial if0_43460400_rutahyd2_es.
-- Luego importar DDL34 de 674 códigos validado frente al SII; DDL35 elimina columnas.
-- La referencia tributaria es código de 6 dígitos, incluyendo ceros iniciales.
-- Fuentes verificables: https://www.sii.cl/catastro/codigos.htm
-- https://www.sii.cl/ayudas/ayudas_por_servicios/1956-codigos-1959.html
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS giros_sii (
  codigo CHAR(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  descripcion VARCHAR(400) NOT NULL,
  afecta_iva VARCHAR(3) NOT NULL DEFAULT 'G',
  categoria_tributaria VARCHAR(3) NOT NULL DEFAULT 'G',
  disponible_internet VARCHAR(3) NOT NULL DEFAULT 'SI',
  rubro_sii VARCHAR(190) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fuente VARCHAR(255) NOT NULL DEFAULT 'https://www.sii.cl/catastro/codigos.htm',
  PRIMARY KEY(codigo),
  INDEX idx_hd_giros_sii_descripcion(descripcion(120)),
  INDEX idx_hd_giros_sii_rubro(rubro_sii(70)),
  CONSTRAINT chk_hd_giros_sii_activo CHECK(activo IN(0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- El código actual del proveedor sigue siendo VARCHAR(12) por compatibilidad histórica.
-- No se añade FK hasta homologar y auditar códigos anteriores; el backend valida
-- que los NUEVOS códigos seleccionados existan en giros_sii.
SHOW TABLES LIKE 'giros_sii';
