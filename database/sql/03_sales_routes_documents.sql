-- RutaH&D2 - Fase 03: ventas, rutas, pagos y documentos
-- Ejecutar DESPUES de 01 y 02.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS delivery_routes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(40) NOT NULL UNIQUE,
 name VARCHAR(140) NOT NULL,
 driver_user_id BIGINT UNSIGNED NULL,
 vehicle_name VARCHAR(120) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 CONSTRAINT fk_route_driver FOREIGN KEY(driver_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS quotations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 number VARCHAR(50) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 seller_user_id BIGINT UNSIGNED NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'draft',
 valid_until DATE NULL,
 subtotal_net DECIMAL(15,2) NOT NULL DEFAULT 0,
 discount_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 tax_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 total_gross DECIMAL(15,2) NOT NULL DEFAULT 0,
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_quote_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_quote_seller FOREIGN KEY(seller_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS quotation_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 quotation_id BIGINT UNSIGNED NOT NULL,
 presentation_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(15,3) NOT NULL,
 unit_price_net DECIMAL(15,4) NOT NULL,
 discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
 subtotal_net DECIMAL(15,2) NOT NULL,
 CONSTRAINT fk_quote_item_header FOREIGN KEY(quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
 CONSTRAINT fk_quote_item_presentation FOREIGN KEY(presentation_id) REFERENCES product_presentations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sale_notes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 number VARCHAR(50) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 quotation_id BIGINT UNSIGNED NULL,
 seller_user_id BIGINT UNSIGNED NULL,
 delivery_route_id BIGINT UNSIGNED NULL,
 delivery_at DATETIME NULL,
 delivery_address VARCHAR(255) NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'received',
 payment_status VARCHAR(40) NOT NULL DEFAULT 'pending',
 payment_method VARCHAR(40) NULL,
 subtotal_net DECIMAL(15,2) NOT NULL DEFAULT 0,
 discount_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 tax_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 total_gross DECIMAL(15,2) NOT NULL DEFAULT 0,
 amount_delivered_gross DECIMAL(15,2) NOT NULL DEFAULT 0,
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_sale_notes_status(status,payment_status),
 KEY idx_sale_notes_delivery(delivery_at),
 CONSTRAINT fk_sn_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_sn_quote FOREIGN KEY(quotation_id) REFERENCES quotations(id) ON DELETE SET NULL,
 CONSTRAINT fk_sn_seller FOREIGN KEY(seller_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_sn_route FOREIGN KEY(delivery_route_id) REFERENCES delivery_routes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sale_note_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sale_note_id BIGINT UNSIGNED NOT NULL,
 presentation_id BIGINT UNSIGNED NOT NULL,
 quantity_ordered DECIMAL(15,3) NOT NULL,
 quantity_delivered DECIMAL(15,3) NOT NULL DEFAULT 0,
 unit_price_net DECIMAL(15,4) NOT NULL,
 discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
 subtotal_net DECIMAL(15,2) NOT NULL,
 CONSTRAINT fk_sn_item_header FOREIGN KEY(sale_note_id) REFERENCES sale_notes(id) ON DELETE CASCADE,
 CONSTRAINT fk_sn_item_presentation FOREIGN KEY(presentation_id) REFERENCES product_presentations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sale_note_status_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sale_note_id BIGINT UNSIGNED NOT NULL,
 previous_status VARCHAR(40) NULL,
 new_status VARCHAR(40) NOT NULL,
 changed_by BIGINT UNSIGNED NULL,
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_sn_history_note FOREIGN KEY(sale_note_id) REFERENCES sale_notes(id) ON DELETE CASCADE,
 CONSTRAINT fk_sn_history_user FOREIGN KEY(changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sale_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sale_note_id BIGINT UNSIGNED NOT NULL,
 amount_gross DECIMAL(15,2) NOT NULL,
 method VARCHAR(40) NOT NULL,
 payment_at DATETIME NOT NULL,
 reference VARCHAR(120) NULL,
 notes TEXT NULL,
 recorded_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_payment_date(payment_at),
 CONSTRAINT fk_payment_note FOREIGN KEY(sale_note_id) REFERENCES sale_notes(id) ON DELETE RESTRICT,
 CONSTRAINT fk_payment_user FOREIGN KEY(recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dispatch_guides (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 internal_folio VARCHAR(60) NOT NULL UNIQUE,
 from_warehouse_id BIGINT UNSIGNED NOT NULL,
 to_warehouse_id BIGINT UNSIGNED NOT NULL,
 sale_note_id BIGINT UNSIGNED NULL,
 total_net DECIMAL(15,2) NOT NULL DEFAULT 0,
 status VARCHAR(40) NOT NULL DEFAULT 'generated',
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_guide_from FOREIGN KEY(from_warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_guide_to FOREIGN KEY(to_warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
 CONSTRAINT fk_guide_sale FOREIGN KEY(sale_note_id) REFERENCES sale_notes(id) ON DELETE SET NULL,
 CONSTRAINT fk_guide_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dispatch_guide_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 dispatch_guide_id BIGINT UNSIGNED NOT NULL,
 presentation_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(15,3) NOT NULL,
 unit_price_net DECIMAL(15,4) NOT NULL,
 subtotal_net DECIMAL(15,2) NOT NULL,
 CONSTRAINT fk_guide_item_header FOREIGN KEY(dispatch_guide_id) REFERENCES dispatch_guides(id) ON DELETE CASCADE,
 CONSTRAINT fk_guide_item_presentation FOREIGN KEY(presentation_id) REFERENCES product_presentations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS issued_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sale_note_id BIGINT UNSIGNED NULL,
 document_type VARCHAR(40) NOT NULL,
 folio VARCHAR(70) NOT NULL,
 issuer_system VARCHAR(40) NOT NULL DEFAULT 'internal',
 issue_at DATETIME NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'prepared',
 pdf_path VARCHAR(500) NULL,
 sii_track_id VARCHAR(150) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_issued_doc(document_type,folio,issuer_system),
 CONSTRAINT fk_doc_sale_note FOREIGN KEY(sale_note_id) REFERENCES sale_notes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
