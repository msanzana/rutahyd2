# Ruta H&D V2 · DDL23, árbol de permisos y tema por usuario

## Estado y alcance

- SQL aditivo: [23_preferencias_usuarios_permisos_submodulos_ddl.sql](../database/espanol/23_preferencias_usuarios_permisos_submodulos_ddl.sql)
- Tablas nuevas: `preferencias_usuarios`, `roles_submodulos`.
- DDL23 validado **dos veces** sobre MariaDB temporal: [CI 44979963068](https://github.com/msanzana/rutahyd2/actions/runs/44979963068).
- Integración de JWT, CRUD y backend con el esquema temporal validada en CI: [44980154387](https://github.com/msanzana/rutahyd2/actions/runs/44980154387).
- Ningún DDL de este documento se ejecutó en la base productiva.

## Secuencia segura para habilitar en producción

1. Respaldar completamente la base de producción mediante el procedimiento ya probado de phpMyAdmin/FTP; conservar un backup verificable y anotar la versión.
2. Validar la base de destino, actualmente `if0_43460400_rutahyd2_es`, sus tablas `usuarios`, `roles` y las claves FK.
3. **El titular** aplica el archivo DDL23 desde phpMyAdmin. No usar procesos de migración implícitos del frontend. Se puede repetir el DDL: `CREATE TABLE IF NOT EXISTS`.
4. Comprobar `tablas_v23=2`, la integridad de FK y que no se alteraron registros.
5. Solo entonces desplegar en Laravel `PermisosSubmodulos`, `AutorizarSubmodulo`, `PreferenciasUsuarioControlador` y los controladores/rutas asociados. Hacer backup del backend antes y verificar `/v1/perfil` y los roles con una cuenta autorizada, sin registrar credenciales en GitHub.
6. Probar que un perfil con `productos.gestionar` y `roles_submodulos.productos_listado.ver=0` obtiene 403 en listados y que su sesión puede usar otras rutas no restringidas. Verificar `proveedores_listado` y `proveedores_productos` de manera equivalente.
7. Probar el switch oscuro con dos cuentas diferentes y dos navegadores; comprobar `PUT /v1/perfil/preferencias` solo del usuario autenticado.

## Autorización

Los submódulos nuevos configurables son:

| Clave | Padre | Permiso base obligatorio |
|---|---|---|
| `productos_listado` | Productos | `productos.gestionar` |
| `proveedores_listado` | Proveedores | `compras.gestionar` |
| `proveedores_productos` | Proveedores | `compras.gestionar` |

Ninguna restricción de submódulo **concede** el permiso base faltante; solo lo limita.
La ausencia de fila en `roles_submodulos` preserva los permisos anteriores para no bloquear cuentas existentes, aunque el panel permite establecer reglas más restrictivas después.
Los diez catálogos ya tienen permisos `ver` y `gestionar` independientes en `roles_permisos`; las opciones Usuarios/Perfiles continúan reservadas a Administración.
Los ocho menús oficiales siguen siendo Administración, Compras, Ventas, Proveedores, Clientes, Productos, Bodega y Cuentas corrientes.

## Persistencia visual

Sin DDL23, el frontend v20 guarda solamente el valor `claro`/`oscuro` por usuario **en ese navegador** bajo una clave aislada. No almacena JWT, contraseña ni información comercial.
Con DDL23 y endpoints publicados se habilita también la persistencia por cuenta en MariaDB y el envío de `tema_interfaz` desde el login/perfil.

## Búsquedas avanzadas

Los filtros de pantalla de la v20 son de la **página actual**. Para filtros complejos sobre toda la base, cada endpoint necesitará validación explícita de criterios, índices y permisos de lectura, sin hacer peticiones que omitan autorización.

## Seguridad

No usar Firecrawl ni registrar secretos en controles de versión; no desplegar rutas que consulten tablas ausentes. No crear ni modificar registros productivos de ensayo. Mantener respaldo, CI y rollback.
