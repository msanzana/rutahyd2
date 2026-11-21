-- Ruta H&D V2 | 19 | RUT único de usuarios (NO EJECUTAR SIN RESPALDO EXTERNO)
-- Precondiciones: SELECT COUNT(*) FROM usuarios; SHOW COLUMNS FROM usuarios LIKE 'rut';
-- Aplicar SOLO en if0_43460400_rutahyd2_es si no existe la columna.
-- Mantener NULL para usuarios antiguos. MariaDB permite múltiples NULL en UNIQUE.
ALTER TABLE usuarios
  ADD COLUMN rut VARCHAR(12) NULL AFTER nombre,
  ADD UNIQUE KEY uq_usuarios_rut (rut);
-- Confirmar: SHOW INDEX FROM usuarios WHERE Key_name = 'uq_usuarios_rut';
-- Importante: no crea cuentas, no contiene datos personales ni contraseñas.
