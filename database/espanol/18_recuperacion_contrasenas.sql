-- Ruta H&D V2: recuperación con token de un solo uso (NO ejecutar sin respaldo).
-- Compatible MySQL/MariaDB. No contiene contraseñas, tokens ni cuentas reales.
CREATE TABLE IF NOT EXISTS restablecimientos_contrasena (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    hash_token CHAR(64) NOT NULL UNIQUE,
    expira_en DATETIME NOT NULL,
    creado_en DATETIME NOT NULL,
    UNIQUE KEY uq_restablecimiento_usuario (usuario_id),
    KEY idx_restablecimiento_expira (expira_en),
    CONSTRAINT fk_restablecimiento_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
