-- RUTA H&D2 | Esquema completo para instalacion LOCAL
-- Snapshot de GitHub: msanzana/rutahyd2
-- 32 tablas originales (SQL 01..04 de main) + 2 tablas Ventas/DTE (SQL 07 de feature/laravel-repository-map)
-- Modelo de sale_details: product_id -> products.id; sin presentation_id ni duplicacion de atributos del producto.
-- Fecha de consolidacion: 2026-11-19
-- Motor: MySQL 8.x / MariaDB 10.5+ orientativo. Probado estructuralmente, NO ejecutado en un servidor MySQL local por esta sesion.
-- Seguro: NO contiene DROP/TRUNCATE/DELETE, usuarios reales, credenciales ni datos comerciales.
-- Importante: ejecutar en una base de datos nueva. CREATE TABLE IF NOT EXISTS no reconcilia tablas previas.
-- BASE LOCAL: rutahyd2_local. Cambiar nombre antes de ejecutar si hace falta.
CREATE DATABASE IF NOT EXISTS rutahyd2_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rutahyd2_local;
SET NAMES utf8mb4;

-- ============================================================
-- BLOQUE 1: CORE / CATALOGO
-- ============================================================
-- RutaH&D2 - Fase 01: acceso, clientes y catalogo
-- MariaDB 11.4 / MySQL 8 compatible. Ejecutar dentro de la BBDD seleccionada.
-- Sin DROP/DELETE y sin credenciales ni datos operativos.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS roles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(40) NOT NULL UNIQUE,
 name VARCHAR(100) NOT NULL,
 description VARCHAR(255) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS permissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(100) NOT NULL UNIQUE,
 description VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS role_permissions (
 role_id BIGINT UNSIGNED NOT NULL,
 permission_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(role_id,permission_id),
 CONSTRAINT fk_rp_role FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE,
 CONSTRAINT fk_rp_perm FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 role_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(160) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 last_login_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_users_role(role_id),
 CONSTRAINT fk_users_role FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(50) NULL UNIQUE,
 name VARCHAR(190) NOT NULL,
 tax_id VARCHAR(25) NULL,
 email VARCHAR(190) NULL,
 phone VARCHAR(50) NULL,
 address VARCHAR(255) NULL,
 commune VARCHAR(100) NULL,
 sector VARCHAR(120) NULL,
 notes TEXT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_customers_name(name), KEY idx_customers_tax(tax_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS suppliers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(190) NOT NULL,
 tax_id VARCHAR(25) NULL,
 email VARCHAR(190) NULL,
 phone VARCHAR(50) NULL,
 address VARCHAR(255) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS product_categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 parent_id BIGINT UNSIGNED NULL,
 name VARCHAR(150) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 CONSTRAINT fk_category_parent FOREIGN KEY(parent_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_id BIGINT UNSIGNED NULL,
 supplier_id BIGINT UNSIGNED NULL,
 sku VARCHAR(80) NULL UNIQUE,
 name VARCHAR(190) NOT NULL,
 description TEXT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_products_name(name),
 CONSTRAINT fk_products_category FOREIGN KEY(category_id) REFERENCES product_categories(id) ON DELETE SET NULL,
 CONSTRAINT fk_products_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS product_presentations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id BIGINT UNSIGNED NOT NULL,
 barcode VARCHAR(80) NULL UNIQUE,
 name VARCHAR(150) NOT NULL,
 units_per_package DECIMAL(15,3) NOT NULL DEFAULT 1,
 price_net DECIMAL(15,4) NOT NULL DEFAULT 0,
 tax_rate DECIMAL(5,2) NOT NULL DEFAULT 19.00,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_presentation_name(product_id,name),
 CONSTRAINT fk_presentation_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS product_images (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id BIGINT UNSIGNED NOT NULL,
 file_path VARCHAR(500) NOT NULL,
 sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
 is_primary TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_product_image_order(product_id,sort_order),
 CONSTRAINT fk_product_images_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS customer_prices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 presentation_id BIGINT UNSIGNED NOT NULL,
 price_net DECIMAL(15,4) NOT NULL,
 valid_from DATE NULL,
 valid_until DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_customer_presentation(customer_id,presentation_id),
 CONSTRAINT fk_customer_price_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 CONSTRAINT fk_customer_price_presentation FOREIGN KEY(presentation_id) REFERENCES product_presentations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BLOQUE 2: INVENTARIO / OFERTAS / COMPRAS
-- ============================================================
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


-- ============================================================
-- BLOQUE 3: VENTAS / RUTAS / DOCUMENTOS
-- ============================================================
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


-- ============================================================
-- BLOQUE 4: AGENDA / AUDITORIA
-- ============================================================
-- RutaH&D2 - Fase 04: agenda, metas y auditoria
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS agenda_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_type VARCHAR(60) NOT NULL DEFAULT 'new_event',
 title VARCHAR(190) NOT NULL,
 description TEXT NULL,
 starts_at DATETIME NOT NULL,
 ends_at DATETIME NULL,
 customer_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_agenda_start(starts_at),
 CONSTRAINT fk_agenda_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 CONSTRAINT fk_agenda_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS monthly_sales_targets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 target_month DATE NOT NULL,
 target_gross DECIMAL(15,2) NOT NULL DEFAULT 0,
 updated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_target_month(target_month),
 CONSTRAINT fk_target_user FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS scheduled_workdays (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 target_month DATE NOT NULL,
 work_date DATE NOT NULL UNIQUE,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 notes VARCHAR(255) NULL,
 KEY idx_workday_month(target_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 action VARCHAR(80) NOT NULL,
 entity_type VARCHAR(80) NOT NULL,
 entity_id BIGINT UNSIGNED NULL,
 old_values LONGTEXT NULL,
 new_values LONGTEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_audit_entity(entity_type,entity_id),
 CONSTRAINT fk_audit_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BLOQUE 5: NUEVAS VENTAS / DETALLE DTE
-- ============================================================
-- RutaH&D2: cabecera de venta DTE + detalle de venta DTE.
-- Basado en msanzana/sii-dte-backend @ develop, StoreDteDocumentRequest (2026-11-19).
-- SOLO dos tablas nuevas; no modifica las 32 existentes. Ejecutar en DB existente.
-- sale_details usa product_id como UNICA referencia al catalogo.
-- Nombre, SKU y descripcion se obtienen de products al preparar el DTE.
-- Conservar el JSON enviado en sales.dte_request_payload para auditoria.
-- IMPORTANTE: El servidor DTE recibe company_id desde JWT técnico,
-- no desde el JSON de /api/internal/dte/document.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sales (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 sale_note_id BIGINT UNSIGNED NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 sale_number VARCHAR(50) NOT NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'draft',
 currency_code CHAR(3) NOT NULL DEFAULT 'CLP',
 issue_date DATE NOT NULL,
 dte_type SMALLINT UNSIGNED NOT NULL,
 dte_external_id CHAR(36) NULL,
 dte_company_id BIGINT UNSIGNED NULL,
 dte_external_system_id BIGINT UNSIGNED NULL,
 dte_proposed_folio BIGINT UNSIGNED NULL,
 dte_proposed_sii_document_type SMALLINT UNSIGNED NULL,
 dte_branch_office_number INT UNSIGNED NULL,
 dte_facility_number INT UNSIGNED NULL,
 dte_external_branch_code VARCHAR(100) NULL,
 dte_remote_document_id BIGINT UNSIGNED NULL,
 dte_folio BIGINT UNSIGNED NULL,
 dte_status VARCHAR(40) NOT NULL DEFAULT 'not_requested',
 dte_sii_environment VARCHAR(10) NULL,
 receiver_document VARCHAR(20) NOT NULL,
 receiver_name VARCHAR(120) NOT NULL,
 receiver_giro VARCHAR(150) NULL,
 receiver_address VARCHAR(150) NULL,
 receiver_city_id BIGINT UNSIGNED NULL,
 receiver_email VARCHAR(150) NULL,
 net_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 exempt_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 header_payload JSON NULL,
 references_payload JSON NULL,
 dte_request_payload JSON NULL,
 dte_response_payload JSON NULL,
 dte_last_error_code VARCHAR(50) NULL,
 dte_last_error_message TEXT NULL,
 dte_requested_at DATETIME NULL,
 dte_created_at DATETIME NULL,
 dte_accepted_at DATETIME NULL,
 dte_rejected_at DATETIME NULL,
 notes TEXT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_sales_number(sale_number),
 UNIQUE KEY uq_sales_note(sale_note_id),
 UNIQUE KEY uq_sales_external_id(dte_external_id),
 KEY idx_sales_customer_date(customer_id,issue_date),
 KEY idx_sales_dte_status(dte_status,dte_type),
 KEY idx_sales_dte_remote(dte_remote_document_id),
 CONSTRAINT fk_sales_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_sales_note FOREIGN KEY(sale_note_id) REFERENCES sale_notes(id) ON DELETE SET NULL,
 CONSTRAINT fk_sales_created_by FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_details (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 sale_id BIGINT UNSIGNED NOT NULL,
 sale_note_item_id BIGINT UNSIGNED NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 line_number INT UNSIGNED NOT NULL,
 quantity DECIMAL(18,4) NOT NULL DEFAULT 1,
 unit_price DECIMAL(18,4) NOT NULL DEFAULT 0,
 discount_percent DECIMAL(8,4) NOT NULL DEFAULT 0,
 discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 tax_exempt TINYINT(1) NOT NULL DEFAULT 0,
 line_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
 extra_payload JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_sale_details_line(sale_id,line_number),
 KEY idx_sale_detail_product(product_id),
 CONSTRAINT fk_sale_detail_sale FOREIGN KEY(sale_id) REFERENCES sales(id) ON DELETE CASCADE,
 CONSTRAINT fk_sale_detail_note_item FOREIGN KEY(sale_note_item_id) REFERENCES sale_note_items(id) ON DELETE SET NULL,
 CONSTRAINT fk_sale_detail_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FIN: 34 TABLAS (34 esperadas). NO inserta registros.
