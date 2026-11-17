-- RutaH&D2 - Fase 02: inventario, ofertas y compras
-- Ejecutar DESPUES de 01_core_catalog.sql.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS warehouses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) NOT NULL UNIQUE,
 name VARCHAR(130) NOT NULL,
 type VARCHAR(40) NOT NULL DEFAULT 'general',
 active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS stock_balances (
 warehouse_id BIGINT UNSIGNED NOT NULL,
 presentation_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(warehouse_id,presentation_id),
 CONSTRAINT fk_stock_warehouse FOREIGN KEY(warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_stock_presentation FOREIGN KEY(presentation_id) REFERENCES product_presentations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS stock_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 presentation_id BIGINT UNSIGNED NOT NULL,
 from_warehouse_id BIGINT UNSIGNED NULL,
 to_warehouse_id BIGINT UNSIGNED NULL,
 quantity DECIMAL(15,3) NOT NULL,
 movement_type VARCHAR(40) NOT NULL,
 reference_type VARCHAR(60) NULL,
 reference_id BIGINT UNSIGNED NULL,
 notes TEXT NULL,
 performed_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_stock_movements_reference(reference_type,reference_id),
 KEY idx_stock_movements_created(created_at),
 CONSTRAINT fk_move_presentation FOREIGN KEY(presentation_id) REFERENCES product_presentations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_move_from FOREIGN KEY(from_warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_move_to FOREIGN KEY(to_warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_move_user FOREIGN KEY(performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS promotions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(190) NOT NULL,
 target_customer_id BIGINT UNSIGNED NULL,
 valid_from DATETIME NOT NULL,
 valid_until DATETIME NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_promotions_window(valid_from,valid_until),
 CONSTRAINT fk_promo_customer FOREIGN KEY(target_customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 CONSTRAINT fk_promo_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS promotion_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 promotion_id BIGINT UNSIGNED NOT NULL,
 presentation_id BIGINT UNSIGNED NOT NULL,
 discount_percent DECIMAL(5,2) NULL,
 price_net DECIMAL(15,4) NULL,
 UNIQUE KEY uq_promo_presentation(promotion_id,presentation_id),
 CONSTRAINT fk_promotion_item_parent FOREIGN KEY(promotion_id) REFERENCES promotions(id) ON DELETE CASCADE,
 CONSTRAINT fk_promotion_item_presentation FOREIGN KEY(presentation_id) REFERENCES product_presentations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS purchase_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 number VARCHAR(50) NOT NULL UNIQUE,
 supplier_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'draft',
 ordered_at DATETIME NULL,
 received_at DATETIME NULL,
 subtotal_net DECIMAL(15,2) NOT NULL DEFAULT 0,
 tax_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 total_gross DECIMAL(15,2) NOT NULL DEFAULT 0,
 notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_po_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_po_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS purchase_order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 purchase_order_id BIGINT UNSIGNED NOT NULL,
 presentation_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(15,3) NOT NULL,
 unit_cost_net DECIMAL(15,4) NOT NULL,
 subtotal_net DECIMAL(15,2) NOT NULL,
 CONSTRAINT fk_po_item_header FOREIGN KEY(purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_po_item_presentation FOREIGN KEY(presentation_id) REFERENCES product_presentations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
