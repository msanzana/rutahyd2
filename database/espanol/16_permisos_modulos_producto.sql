-- Ruta H&D2 · permisos específicos de submódulos de Productos
-- Aditivo e idempotente. Ejecutar SOLO tras respaldo en la base española.
-- No asigna permisos a roles distintos del superadministrador (bypass servidor).
-- Deben estar presentes las tablas 'roles', 'permisos', 'roles_permisos'.
INSERT IGNORE INTO permisos (codigo,descripcion) VALUES
 ('productos.catalogos.rubros.ver','Ver rubros'),
 ('productos.catalogos.rubros.gestionar','Gestionar rubros'),
 ('productos.catalogos.departamentos.ver','Ver departamentos'),
 ('productos.catalogos.departamentos.gestionar','Gestionar departamentos'),
 ('productos.catalogos.familias.ver','Ver familias'),
 ('productos.catalogos.familias.gestionar','Gestionar familias'),
 ('productos.catalogos.lineas.ver','Ver líneas'),
 ('productos.catalogos.lineas.gestionar','Gestionar líneas'),
 ('productos.catalogos.marcas.ver','Ver marcas'),
 ('productos.catalogos.marcas.gestionar','Gestionar marcas'),
 ('productos.catalogos.modelos.ver','Ver modelos'),
 ('productos.catalogos.modelos.gestionar','Gestionar modelos'),
 ('productos.catalogos.tipos_unidad.ver','Ver tipos de unidad'),
 ('productos.catalogos.tipos_unidad.gestionar','Gestionar tipos de unidad'),
 ('productos.catalogos.gramajes.ver','Ver gramajes'),
 ('productos.catalogos.gramajes.gestionar','Gestionar gramajes'),
 ('productos.catalogos.tipos_envasado.ver','Ver tipos de envasado'),
 ('productos.catalogos.tipos_envasado.gestionar','Gestionar tipos de envasado'),
 ('productos.catalogos.condiciones_tributarias.ver','Ver condiciones tributarias'),
 ('productos.catalogos.condiciones_tributarias.gestionar','Gestionar condiciones tributarias');
-- La matriz de roles se configura luego mediante la API administrativa.
-- No añadir permisos masivos ni cambiar la tabla legacy categorias_productos.
