-- TABLA TECNICA 35 PARA AUTENTICACION API PROPIA (NO EN LAS 34 TABLAS COMERCIALES)
-- Ejecutar en rutahyd2_es_local o seleccionar manualmente
-- if0_43460400_rutahyd2_es desde phpMyAdmin InfinityFree.
-- NO contiene usuarios, contrasenas ni tokens de ejemplo.
CREATE TABLE IF NOT EXISTS tokens_acceso (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 usuario_id BIGINT UNSIGNED NOT NULL,
 hash_token CHAR(64) NOT NULL UNIQUE,
 nombre VARCHAR(80) NOT NULL DEFAULT 'sesion_api',
 expira_en DATETIME NOT NULL,
 ultimo_uso_en DATETIME NULL,
 creado_en DATETIME NOT NULL,
 KEY indice_tokens_usuario (usuario_id),
 KEY indice_tokens_expira (expira_en),
 CONSTRAINT fk_tokens_usuario FOREIGN KEY (usuario_id)
   REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
