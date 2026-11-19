-- Datos de configuracion inicial en espanol. No crea clientes ni ventas.
-- La traduccion de codigos de permisos y roles se hará junto con el backend Laravel.
-- Semillas OPCIONALES para base local RutaH&D2.
-- Ejecutar DESPUES de 01_RutaHD2_DDL_34_tablas.sql.
USE rutahyd2_es_local;
SET NAMES utf8mb4;

-- RutaH&D2 - Fase 05: datos iniciales de configuracion solamente
-- No crea usuarios ni registra operaciones comerciales ficticias.
INSERT IGNORE INTO roles (codigo,nombre,descripcion) VALUES
 ('admin','Administrador','Control integral del sistema'),
 ('supervisor','Supervisor','Supervision operacional'),
 ('seller','Vendedor','Gestion comercial y clientes'),
 ('warehouse','Bodega','Inventario y despacho'),
 ('driver','Repartidor','Entrega y rutas');
INSERT IGNORE INTO bodegas (codigo,nombre,tipo) VALUES
 ('CASA','Bodega 2 · Casa','storage'),
 ('VEHICULO','Bodega 1 · Vehículo','vehicle');
INSERT IGNORE INTO permisos (codigo,descripcion) VALUES
 ('ventas.view','Consultar notas de venta'),
 ('ventas.manage','Crear y actualizar notas de venta'),
 ('quotes.manage','Gestionar cotizaciones'),
 ('inventory.view','Consultar existencias'),
 ('inventory.manage','Gestionar stock y guias'),
 ('clients.manage','Gestionar clientes'),
 ('productos.manage','Gestionar catalogo'),
 ('purchases.manage','Gestionar compras'),
 ('offers.manage','Gestionar promociones'),
 ('routes.manage','Gestionar rutas'),
 ('reports.view','Consultar informes'),
 ('usuarios.manage','Administrar usuarios y roles');
-- Las asignaciones de permisos requieren una matriz de roles aprobada.
