# RutaH&D2 V2 — Instalación real de esquema español en InfinityFree

**Fecha:** 2026-11-19  
**Estado:** COMPLETADO (estructura SQL + semillas básicas); backend Laravel NO desplegado ni adaptado al nuevo esquema.

## Base de datos nueva
- Proveedor: InfinityFree, alojamiento `if0_43460400`.
- Host MariaDB: `sql307.infinityfree.com`; puerto `3306` (conexión MySQL remota desde PC bloqueada por InfinityFree).
- Nueva base: `if0_43460400_rutahyd2_es`.
- Base original preservada: `if0_43460400_rutahyd2` (32 tablas inglés, 19 filas de configuración; no se modificó).
- Acceso probado: mediante TinyFish + navegador phpMyAdmin autenticado, no conector TCP directo.

## Acciones verificadas en phpMyAdmin
1. Nueva base `if0_43460400_rutahyd2_es` creada vacía en el panel.
2. Ejecutado `database/espanol/infinityfree/01_estructura.sql`: 11 tablas, sin errores.
3. Ejecutado `02_estructura.sql`: 19 tablas acumuladas, sin errores.
4. Ejecutado `03_estructura.sql`: 27 tablas acumuladas, sin errores.
5. Ejecutado `04_estructura.sql`: 34 tablas acumuladas, sin errores; `ventas` y `detalles_ventas` confirmadas.
6. Confirmada FK `detalles_ventas.producto_id -> productos.id`.
7. Ejecutado `05_semillas.sql`: 5 roles, 2 bodegas y 12 permisos, todos en español; sin errores.

## Validación
- 34 tablas de la V2 confirmadas por phpMyAdmin.
- El DDL versionado tiene 313 columnas y 56 relaciones FK definidas, validado estructuralmente en GitHub. **No se auditó individualmente cada FK en el servidor**.
- No se cargaron clientes, productos, ventas ni usuarios reales.
- La base original inglesa permanece disponible e intacta.

## Reglas y pendientes
- Los identificadores SQL y códigos propios de V2 en español; el contrato HTTP del Servidor DTE Laravel permanece con claves inglesas según su API.
- Modelos, repositorios, servicios, controladores, validaciones, permisos y consultas de Laravel de la rama anterior aún referencian tablas/columnas inglesas. **No dirigir ese código actual a la base española hasta refactorizar y probar**.
- La nueva base no se ha conectado a una aplicación ni publicado por FTP.
- Mantener la base inglesa hasta completar pruebas y respaldos. No eliminar ni renombrarla sin decisión explícita y copia verificada.
- El usuario puede usar SQL local en `database/espanol/01_esquema_completo_34_tablas_es.sql` para generar `rutahyd2_es_local` en MySQL Workbench.
- La conexión remota TCP de InfinityFree no está habilitada en el hosting gratuito. Administrar por phpMyAdmin o PHP alojado allí.

## Documentación
- `database/espanol/README.md`: instalación local y scripts.
- `database/espanol/02_diccionario_tablas.csv`, `03_diccionario_columnas.csv`, `09_diccionario_codigos.csv`.
- `docs/CONVENCIONES_V2_ES.md`.

*Fuente: operaciones efectivamente verificadas en el panel InfinityFree y phpMyAdmin por TinyFish.*
