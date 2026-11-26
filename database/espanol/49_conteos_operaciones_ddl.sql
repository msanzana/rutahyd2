CREATE TABLE IF NOT EXISTS inventario_conteos_operaciones (
  conteo_id BIGINT UNSIGNED NOT NULL,
  operacion_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (conteo_id, operacion_id),
  UNIQUE KEY uq_conteos_operacion (operacion_id),
  CONSTRAINT fk_conteos_op_conteo FOREIGN KEY (conteo_id) REFERENCES inventario_conteos(id),
  CONSTRAINT fk_conteos_op_operacion FOREIGN KEY (operacion_id) REFERENCES inventario_operaciones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
