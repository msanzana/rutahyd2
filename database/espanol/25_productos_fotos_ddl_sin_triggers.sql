-- Ruta H&D V2 · DDL 25 · Galería de fotografías de productos.
-- MariaDB / MySQL - SIN triggers, SIN almacenamiento público de imágenes.
-- Ejecutar una sola vez tras respaldo y verificar motor / FK de productos.
-- Los BLOB se consultan SOLO por endpoint autenticado; nunca en SELECT de listados.
-- Cada producto puede tener N fotos; no hay límite en cantidad de filas.

CREATE TABLE IF NOT EXISTS productos_fotos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    producto_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(80) NOT NULL,
    foto LONGBLOB NOT NULL,
    mime_tipo VARCHAR(32) NOT NULL,
    nombre_archivo VARCHAR(180) NOT NULL,
    tamano_bytes INT UNSIGNED NOT NULL,
    posicion INT UNSIGNED NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_productos_fotos_producto_orden (producto_id, posicion, id),
    KEY ix_productos_fotos_sku (sku),
    CONSTRAINT fk_productos_fotos_producto
        FOREIGN KEY (producto_id) REFERENCES productos(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_productos_fotos_tamano CHECK (tamano_bytes > 0),
    CONSTRAINT chk_productos_fotos_mime
        CHECK (mime_tipo IN ('image/jpeg','image/png','image/webp'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La aplicación sincroniza SKU desde ProductoComercialControlador/
-- GestionListasPrecios dentro de la transacción del cambio de producto.
-- La eliminación de fotos se realiza mediante DELETE autenticado de Laravel;
-- NO hay triggers ni procedimientos almacenados.

-- Comprobaciones de solo lectura después de importar:
SHOW TABLES LIKE 'productos_fotos';
SHOW CREATE TABLE productos_fotos;
SELECT COUNT(*) AS cantidad_fotos FROM productos_fotos;
