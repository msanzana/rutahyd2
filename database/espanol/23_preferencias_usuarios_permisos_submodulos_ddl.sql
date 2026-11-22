-- Ruta H&D V2 · DDL 23 · Preferencias personales y autorización de submódulos
-- Extensión estrictamente ADITIVA. NO cambia permisos actuales ni registros.
-- REQUISITO: respaldar previamente la base de producción; importar una sola vez
-- mediante phpMyAdmin y comprobar las tablas tras importar.
-- No publicar las rutas que consultan estas tablas antes de validar el DDL.
CREATE TABLE IF NOT EXISTS preferencias_usuarios (
 usuario_id BIGINT UNSIGNED NOT NULL,
 tema_interfaz ENUM('claro','oscuro') NOT NULL DEFAULT 'claro',
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (usuario_id),
 CONSTRAINT fk_preferencias_usuarios_usuario
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles_submodulos (
 rol_id BIGINT UNSIGNED NOT NULL,
 submodulo VARCHAR(80) NOT NULL,
 ver TINYINT(1) NOT NULL DEFAULT 0,
 gestionar TINYINT(1) NOT NULL DEFAULT 0,
 actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (rol_id, submodulo),
 CONSTRAINT fk_roles_submodulos_rol FOREIGN KEY (rol_id)
  REFERENCES roles(id) ON DELETE CASCADE,
 CONSTRAINT ck_roles_submodulos_gestion_ver CHECK (gestionar=0 OR ver=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pruebas de solo lectura posteriores
SELECT COUNT(*) AS tablas_v23
FROM information_schema.tables
WHERE table_schema=DATABASE()
 AND table_name IN ('preferencias_usuarios','roles_submodulos');
SELECT COUNT(*) AS preferencias_existentes FROM preferencias_usuarios;
SELECT COUNT(*) AS reglas_explicitas_existentes FROM roles_submodulos;
