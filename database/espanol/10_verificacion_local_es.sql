-- RUTA H&D2 V2 / CONSULTAS DE VERIFICACION LOCAL EN ESPANOL
USE rutahyd2_es_local;
SELECT COUNT(*) AS numero_tablas FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE';
SELECT COUNT(*) AS numero_claves_foraneas FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND referenced_table_name IS NOT NULL;
SELECT table_name, column_name, referenced_table_name, referenced_column_name FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND referenced_table_name IS NOT NULL ORDER BY table_name, column_name;
SELECT COUNT(*) AS numero_roles FROM roles;
SELECT COUNT(*) AS numero_bodegas FROM bodegas;
SELECT COUNT(*) AS numero_permisos FROM permisos;
SHOW CREATE TABLE detalles_ventas;
