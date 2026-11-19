-- Datos de configuracion inicial en espanol. No crea clientes ni ventas.
-- Roles, permisos y tipos de bodega de la V2 traducidos a espanol.
-- REQUIERE un backend Laravel adaptado a estos codigos; no aplicar sobre la V1.
-- Semillas OPCIONALES para base local RutaH&D2.
-- Ejecutar DESPUES de 01_esquema_completo_34_tablas_es.sql.
USE rutahyd2_es_local;
SET NAMES utf8mb4;

-- RutaH&D2 - Fase 05: datos iniciales de configuracion solamente
-- No crea usuarios ni registra operaciones comerciales ficticias.
INSERT IGNORE INTO roles (codigo,nombre,descripcion) VALUES
 ('administrador','Administrador','Control integral del sistema'),
 ('supervisor','Supervisor','Supervision operacional'),
 ('vendedor','Vendedor','Gestion comercial y clientes'),
 ('bodega','Bodega','Inventario y despacho'),
 ('repartidor','Repartidor','Entrega y rutas');
INSERT IGNORE INTO bodegas (codigo,nombre,tipo) VALUES
 ('CASA','Bodega 2 · Casa','almacen'),
 ('VEHICULO','Bodega 1 · Vehículo','vehiculo');
INSERT IGNORE INTO permisos (codigo,descripcion) VALUES
 ('ventas.ver','Consultar notas de venta'),
 ('ventas.gestionar','Crear y actualizar notas de venta'),
 ('cotizaciones.gestionar','Gestionar cotizaciones'),
 ('inventario.ver','Consultar existencias'),
 ('inventario.gestionar','Gestionar stock y guias'),
 ('clientes.gestionar','Gestionar clientes'),
 ('productos.gestionar','Gestionar catalogo'),
 ('compras.gestionar','Gestionar compras'),
 ('ofertas.gestionar','Gestionar promociones'),
 ('rutas.gestionar','Gestionar rutas'),
 ('informes.ver','Consultar informes'),
 ('usuarios.gestionar','Administrar usuarios y roles');
-- Las asignaciones de permisos requieren una matriz de roles aprobada.
