-- PREVIA OBLIGATORIA: identificar la situacion REAL en InfinityFree.
-- NO ejecutar ninguna migracion hasta respaldar con Exportar > SQL y validar el respaldo.
SELECT DATABASE() AS base_actual;
SELECT VERSION() AS version_servidor;
SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' ORDER BY table_name;
SELECT COUNT(*) AS tablas_ingles FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('users','customers','sale_notes','products');
SELECT COUNT(*) AS tablas_es FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('usuarios','clientes','notas_venta','productos');
SELECT COUNT(*) AS ventas_ingles FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('sales','sale_details');
SELECT COUNT(*) AS ventas_espanol FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('ventas','detalles_ventas');
-- Verificar filas de todas las tablas y descargar un backup de esquema + datos antes de continuar.
