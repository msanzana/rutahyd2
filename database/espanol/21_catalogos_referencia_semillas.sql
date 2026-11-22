-- Ruta H&D V2: DATOS DE REFERENCIA (DML), separados del DDL estructural 20.
-- Requiere validar que los catálogos y permisos existen. No borra, renombra ni
-- desactiva datos existentes. Se propone para importación SQL controlada por el titular.
-- Nunca ejecutar este archivo en lugar de DDL20; aplicar DESPUÉS de DDL20.

INSERT IGNORE INTO tipos_unidad (nombre,activo) VALUES
 ('Unidad',1),('kg',1),('gr',1),('mg',1),('Litro',1),('ml',1),
 ('Caja',1),('Bolsa',1),('Paquete',1),('Pack',1),('Pallet',1),
 ('Botella',1),('Lata',1),('Frasco',1),('Bandeja',1),('Saco',1),
 ('Bidón',1),('Balde',1),('Tambor',1),('Rollo',1),('Metro',1),
 ('Metro cuadrado',1),('Metro cúbico',1),('Docena',1),('Par',1),
 ('Sobre',1),('Display',1),('Tubo',1),('Estuche',1);

INSERT IGNORE INTO tipos_envasado (nombre,activo) VALUES
 ('Caja',1),('Bolsa',1),('Botella',1),('Frasco',1),
 ('Lata',1),('Pack',1),('Paquete',1),('Bandeja',1),
 ('Saco',1),('Bidón',1),('Balde',1),('Tambor',1),
 ('Pallet',1),('Film',1),('Estuche',1),('Blíster',1),
 ('Tubo',1),('Envase Tetra Pak',1),('Sobre',1),
 ('Display',1),('Doypack',1),('Fardo',1),('Contenedor',1);

INSERT IGNORE INTO tipos_codigos_barras (codigo,nombre,activo) VALUES
 ('EAN13','EAN-13 (GTIN-13)',1),
 ('EAN8','EAN-8 (GTIN-8)',1),
 ('DUN14','DUN-14',1),
 ('ITF14','ITF-14 / GTIN-14',1),
 ('CODE39','Code 39',1),
 ('CODE128','Code 128',1),
 ('GS1128','GS1-128',1),
 ('UPCA','UPC-A (GTIN-12)',1),
 ('UPCE','UPC-E',1),
 ('QRCODE','Código QR',1),
 ('DATAMATRIX','GS1 DataMatrix',1);

-- Permisos para administrar el nuevo catálogo; el administrador
-- dispone de acceso por su rol. No se concede masivamente a otros roles.
INSERT INTO permisos (codigo,descripcion)
SELECT 'productos.catalogos.tipos_codigos_barras.ver','Consultar tipos de códigos de barras'
WHERE NOT EXISTS(SELECT 1 FROM permisos WHERE codigo='productos.catalogos.tipos_codigos_barras.ver');
INSERT INTO permisos (codigo,descripcion)
SELECT 'productos.catalogos.tipos_codigos_barras.gestionar','Crear y editar tipos de códigos de barras'
WHERE NOT EXISTS(SELECT 1 FROM permisos WHERE codigo='productos.catalogos.tipos_codigos_barras.gestionar');
