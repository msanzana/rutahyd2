-- Ruta H&D V2 · DDL27 aditivo · Corregir HTTP 500 al crear listas de precios
-- Evidencia productiva 22-11-2026: SQLSTATE[42S22], columna 'codigo' desconocida
-- en GestionListasPrecios::crearLista (insertGetId).
--
-- EJECUCIÓN: SOLO el titular, manualmente en phpMyAdmin, base de datos oficial.
-- REQUISITO: respaldo verificado y tabla listas_precios existente.
-- No utilizar Laravel migrations, triggers, API, CI/CD ni SQL automatizado.
-- No realiza INSERT/UPDATE/DELETE/DROP y no borra históricos.
-- Todos los SELECT usan tablas directas; no hay consultas a information_schema.
--
-- Diagnóstico previo (solo lectura). Consulta DIRECTA a tu tabla en phpMyAdmin:
-- Como el campo codigo aún puede faltar, usamos SELECT * sin nombrarlo.
SELECT * FROM listas_precios ORDER BY id DESC LIMIT 25;
--
-- REPARACIÓN NO DESTRUCTIVA:
-- 'codigo' se deja NULL para preservar filas antiguas mientras no se conozca
-- su código comercial original. Laravel lo exige para cada lista nueva.
-- No se inventan identificadores comerciales ni se reescriben listas existentes.
ALTER TABLE listas_precios
  ADD COLUMN IF NOT EXISTS codigo VARCHAR(40) NULL AFTER id,
  ADD COLUMN IF NOT EXISTS observacion VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS es_base TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS actualizado_en DATETIME NOT NULL
    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
--
-- Compatibilidad con el DDL24. Una clave UNIQUE permite múltiples NULL
-- antiguos, pero impide duplicar códigos informados a partir de ahora.
CREATE UNIQUE INDEX IF NOT EXISTS uq_lista_codigo
  ON listas_precios (codigo);
--
-- COMPROBACIONES SOLO LECTURA después de ejecutar:
-- Consulta directa: comprobar las columnas reparadas y sus valores.
SELECT id, codigo, nombre, observacion, activo, es_base,
       creado_en, actualizado_en
FROM listas_precios
ORDER BY id DESC
LIMIT 25;

SELECT COUNT(*) AS listas_existentes,
       SUM(CASE WHEN codigo IS NULL OR TRIM(codigo)='' THEN 1 ELSE 0 END)
         AS listas_anteriores_sin_codigo,
       SUM(CASE WHEN es_base=1 THEN 1 ELSE 0 END) AS listas_base
FROM listas_precios;

SELECT codigo, COUNT(*) AS cantidad
FROM listas_precios
WHERE codigo IS NOT NULL AND TRIM(codigo)<>''
GROUP BY codigo
HAVING COUNT(*)>1;
--
-- IMPORTANTES:
-- 1) Si la auditoría muestra listas antiguas sin codigo, esas filas se conservan
--    y deberán tener un código comercial confirmado antes de editar sus códigos.
-- 2) Verificar que exista una única LISTA BASE; si no, no inventar ni sustituir
--    datos: gestionar la normalización mediante una propuesta separada.
-- 3) Si ya existe un índice único equivalente con otro nombre, conservarlo.
-- 4) Este DDL no modifica precios ni las tablas de detalle de precios.
