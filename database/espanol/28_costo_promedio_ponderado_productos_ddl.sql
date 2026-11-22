-- Ruta H&D V2 · DDL28 · Costo promedio ponderado por producto
-- FECHA: 2026-11-22
-- REGLA VIGENTE: EJECUCIÓN EXCLUSIVAMENTE MANUAL POR TITULAR EN phpMyAdmin.
-- Base oficial: if0_43460400_rutahyd2_es.
-- NO ejecutar mediante Laravel migrations, conectores, CI/CD, navegador remoto.
-- Antes: respaldo íntegro comprobable; verificar que 'productos' y 'costo_neto' existen.
--
-- El costo neto comercial sigue siendo editable; el promedio es contable/informativo,
-- de SOLO LECTURA desde el CRUD y valor inicial cero. No se calcula aún en recepciones.
-- No se aplican cambios a tablas de listas de precios, ventas, compras ni inventario.
-- No hay INSERT, UPDATE, DELETE, DROP ni TRIGGERS.
--
-- Auditoría previa: consulta directa a la tabla, sin INFORMATION_SCHEMA.
SHOW COLUMNS FROM productos LIKE 'costo_neto';
SHOW COLUMNS FROM productos LIKE 'costo_promedio_ponderado';

-- DDL aditivo, reejecutable en MariaDB sin duplicar la columna.
ALTER TABLE productos
  ADD COLUMN IF NOT EXISTS costo_promedio_ponderado
  DECIMAL(15,4) NOT NULL DEFAULT 0.0000 AFTER costo_neto;

-- Verificación manual posterior (solo lectura).
SHOW COLUMNS FROM productos LIKE 'costo_promedio_ponderado';
SELECT id, sku, costo_neto, costo_promedio_ponderado
FROM productos
ORDER BY id DESC
LIMIT 25;

-- NOTAS:
-- 1. Los productos actuales reciben 0.0000 en la columna agregada.
-- 2. Los productos futuros reciben 0.0000 por DEFAULT sin necesitar escribirlo en API.
-- 3. No recalcular según 'costo_neto'. El promedio dependerá de recepción/stock.
-- 4. No editar este campo manualmente ni permitirlo en formularios de Producto.
-- 5. Informar la ejecución y resultado antes de habilitar el contrato completo en producción.
