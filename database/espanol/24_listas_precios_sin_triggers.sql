-- Ruta H&D V2 · DDL 24 · Listas de precios SIN triggers
-- Fuente: modelo actual de clientes, productos, presentaciones_productos y lineas.
-- Requiere migraciones anteriores (01, 13 y 14). Ejecutar tras respaldar.
-- NO ejecutar en producción sin verificar esquema y respaldo.
-- IMPORTANTE: la asignación automática y sincronización se realizan
-- EXCLUSIVAMENTE en App\Services\GestionListasPrecios y controladores Laravel.
-- Este script NO contiene CREATE TRIGGER ni DROP TRIGGER.

-- 1. Coste maestro por producto y margen mínimo de su línea.
-- ALTER de una sola ejecución; verificar information_schema antes de repetir.
ALTER TABLE productos
    ADD COLUMN costo_neto DECIMAL(15,4) NOT NULL DEFAULT 0.0000;
ALTER TABLE lineas
    ADD COLUMN minimo_margen_precio_venta DECIMAL(9,4) NOT NULL DEFAULT 0.0000;

-- 2. Cabecera de listas. Una clave generada + UNIQUE garantiza que
-- como máximo una lista tenga es_base=1, incluso con solicitudes simultáneas.
-- La función Laravel comprueba además que exista y permanezca activa.
CREATE TABLE IF NOT EXISTS listas_precios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(40) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    observacion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    es_base TINYINT(1) NOT NULL DEFAULT 0,
    base_unica VARCHAR(8) AS (
        CASE WHEN es_base = 1 THEN 'BASE' ELSE NULL END
    ) STORED,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lista_codigo (codigo),
    UNIQUE KEY uq_lista_base_unica (base_unica),
    KEY ix_listas_precios_estado (activo),
    CONSTRAINT chk_lista_precios_activo CHECK (activo IN (0,1)),
    CONSTRAINT chk_lista_precios_base CHECK (es_base IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Precios de cada producto en cada lista.
-- SKU se guarda por necesidad del mantenedor; Laravel lo sincroniza.
-- Margen NULL cuando costo=0: NO se calcula división entre cero.
CREATE TABLE IF NOT EXISTS listas_precios_detalle (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lista_precio_id BIGINT UNSIGNED NOT NULL,
    producto_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(80) NULL,
    costo DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    margen_porcentaje DECIMAL(10,4) NULL,
    precio_venta DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_detalle_lista_producto (lista_precio_id, producto_id),
    KEY ix_detalle_producto (producto_id),
    KEY ix_detalle_sku (sku),
    CONSTRAINT fk_detalle_lista FOREIGN KEY (lista_precio_id)
        REFERENCES listas_precios(id) ON DELETE RESTRICT,
    CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id)
        REFERENCES productos(id) ON DELETE RESTRICT,
    CONSTRAINT chk_detalle_no_negativo CHECK (costo >= 0 AND precio_venta >= 0),
    CONSTRAINT chk_detalle_margen CHECK (margen_porcentaje IS NULL OR margen_porcentaje >= 0),
    CONSTRAINT chk_detalle_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Una sola lista asignada por cliente: la PK impide duplicados.
-- Cambiar a una especial reemplaza la relación, NO agrega otra.
CREATE TABLE IF NOT EXISTS clientes_listas_precios (
    cliente_id BIGINT UNSIGNED NOT NULL,
    lista_precio_id BIGINT UNSIGNED NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (cliente_id),
    KEY ix_cliente_lista (lista_precio_id),
    CONSTRAINT fk_cliente_lista_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cliente_lista_precio FOREIGN KEY (lista_precio_id)
        REFERENCES listas_precios(id) ON DELETE RESTRICT,
    CONSTRAINT chk_cliente_lista_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Semilla base. Esta sentencia se puede ejecutar nuevamente sin duplicar.
INSERT INTO listas_precios (codigo, nombre, observacion, activo, es_base)
SELECT 'BASE', 'LISTA BASE', 'Tarifa general predeterminada', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM listas_precios WHERE codigo='BASE' OR es_base=1);

-- 6. Migración segura para clientes existentes: no sustituir asignaciones.
INSERT INTO clientes_listas_precios (cliente_id, lista_precio_id, activo)
SELECT c.id, b.id, 1
FROM clientes c
JOIN listas_precios b ON b.es_base=1 AND b.activo=1
LEFT JOIN clientes_listas_precios rel ON rel.cliente_id=c.id
WHERE rel.cliente_id IS NULL;

-- 7. Migración segura para productos existentes: tomar precio actual de
-- la primera presentación. Nunca poner cero si ya existe precio antiguo.
-- El costo histórico no existe, por lo que se deja en 0 y margen NULL.
INSERT INTO listas_precios_detalle
  (lista_precio_id,producto_id,sku,costo,margen_porcentaje,precio_venta,activo)
SELECT b.id,p.id,p.sku,p.costo_neto,
       CASE WHEN p.costo_neto > 0 AND COALESCE(pr.precio_neto,0) >= p.costo_neto
            THEN ROUND(((COALESCE(pr.precio_neto,0) - p.costo_neto) / p.costo_neto) * 100,4)
            ELSE NULL END,
       COALESCE(pr.precio_neto,0),p.activo
FROM productos p
JOIN listas_precios b ON b.es_base=1 AND b.activo=1
LEFT JOIN presentaciones_productos pr ON pr.id=(
    SELECT MIN(pp.id) FROM presentaciones_productos pp WHERE pp.producto_id=p.id
)
LEFT JOIN listas_precios_detalle d
    ON d.lista_precio_id=b.id AND d.producto_id=p.id
WHERE d.id IS NULL;

-- 8. Auditoría de solo lectura post-DDL; no debe haber asignaciones dobles.
SELECT id,codigo,nombre,activo,es_base FROM listas_precios ORDER BY id;
SELECT COUNT(*) AS cantidad_listas_base FROM listas_precios WHERE es_base=1;
SELECT COUNT(*) AS clientes_sin_lista FROM clientes c
LEFT JOIN clientes_listas_precios clp ON clp.cliente_id=c.id
WHERE clp.cliente_id IS NULL;
SELECT COUNT(*) AS productos_sin_precio_base FROM productos p
LEFT JOIN listas_precios b ON b.es_base=1
LEFT JOIN listas_precios_detalle d ON d.producto_id=p.id AND d.lista_precio_id=b.id
WHERE d.id IS NULL;
