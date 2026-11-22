# Ruta H&D V2 — Productos, proveedores y códigos de barras (22/11/2026)

## Alcance decidido a partir de la solicitud del titular

- **Popup Producto**: ancho máximo 1120 px, centrado, formulario con secciones, dos columnas en escritorio/una en móvil, controles sin desbordamiento horizontal y scroll vertical interno. Se retira el ingreso manual del ID de proveedor.
- **Clasificación**: Rubro → Departamento → Familia → Línea; Marca → Modelo; Tipo de unidad; Tipo de envasado; Condición tributaria. Gramaje deja de ser obligatorio y desaparece del menú. Se conserva `productos.gramaje_id` y `gramajes` existentes por compatibilidad histórica, SIN DROP.
- **Administración**: solo Tipos de unidad, Tipos de envasado, Condición tributaria, Tipos de códigos de barras, Usuarios, Perfiles y permisos. Los catálogos de jerarquía se administran bajo Productos.
- **Proveedores**: dos subopciones, Listado de proveedores (búsqueda paginada por nombre/RUT/correo y CRUD existente) y Proveedores productos (asociar producto con proveedor, editar código/costo, desactivar/consultar y administrar códigos de compra).
- **Producto**: sección de múltiples códigos de barras de venta dentro del formulario del producto, con tipo de simbología, código, unidades representadas, estado y baja lógica. No tiene campo de proveedor.
- **Compra**: cada código pertenece a una relación `productos_proveedores` que identifica producto y proveedor; contiene simbología, código, unidades por código y activo.
- **Última recepción**: `ultima_recepcion_id` y `fecha_ultima_recepcion` se agregan como columnas **NULL** de solo lectura para el usuario; su enlace FK y actualización transaccional se harán en la futura implementación del módulo Recepciones, que aún no tiene tabla confirmada. No inventar recepciones ni permitir modificar ese dato manualmente desde el CRUD.

## Estructura SQL y mapeo

La tabla `proveedores` ya existía en el esquema productivo español y su CRUD Laravel se publicó en una etapa anterior. **No se crea otra tabla de proveedores**, evitando duplicados.

Nuevas tablas en DDL SQL20:

| Tabla | Llaves y campos clave | Finalidad |
|---|---|---|
| `tipos_codigos_barras` | id, codigo único, nombre único, activo | EAN-13, DUN-14, Code 39/128, etc. |
| `productos_proveedores` | id, producto_id FK, proveedor_id FK, codigo_proveedor_producto, costo_proveedor, ultima_recepcion_id, fecha_ultima_recepcion, activo | Contrato de abastecimiento por pareja producto/proveedor |
| `productos_codigos_barra_venta` | id, producto_id FK, tipo_codigo_barra_id FK, codigo_barras único, unidades_por_codigo, activo | Identificadores de venta del producto, sin proveedor |
| `productos_codigos_barra_compra` | id, producto_proveedor_id FK, tipo_codigo_barra_id FK, codigo_barras, unidades_por_codigo, activo | Identificación logística y unidad de compra por proveedor |

Los códigos **SKU de producto** y **código propio del proveedor** se almacenan una sola vez, en `productos.sku` y `productos_proveedores.codigo_proveedor_producto`; la API de códigos de compra entrega ambos mediante JOIN. Evita valores divergentes por duplicación. El mismo código de compra puede aparecer en dos proveedores diferentes, pero no dos veces en una misma relación. Los códigos de venta son globalmente únicos.

### Contratos Laravel

- `GET/POST /api/v1/proveedores-productos`, `GET/PUT/DELETE /api/v1/proveedores-productos/{id}`: CRUD con bajas lógicas.
- `GET/POST /api/v1/proveedores-productos/{vinculoId}/codigos-compra`, `PUT/DELETE /.../{id}`: códigos de compra de cada relación.
- `GET/POST /api/v1/productos/{productoId}/codigos-venta`, `PUT/DELETE /.../{id}`: códigos de venta de un producto.
- `GET/POST/PUT /api/v1/productos`: DTO opcional `codigosVenta[]` que permite guardar la lista completa de venta **en la misma transacción** de creación/edición.
- `GET /api/v1/recursos/proveedores?buscar=...`: búsqueda paginada, respaldada por datos reales.
- `GET/POST/PUT/DELETE /api/v1/catalogos/tipos_codigos_barras`: mantenedor administrativo. Código de tipo generado una sola vez y no renombrado silenciosamente.

Los controladores usan Bearer JWT y permisos `productos.gestionar` y `compras.gestionar` para escrituras respectivas; los perfiles siguen restringiendo visibilidad por rol. No se exponen rutas de escritura genérica para Ventas ni inventario.

### Tipos y datos de referencia

El archivo SQL21 contiene únicamente **DML de semillas**, separado explícitamente de DDL20:

- Unidades: Unidad, kg, gr, mg, Litro, ml, Caja, Bolsa, Paquete, Pack, Pallet, Botella, Lata, Frasco, Bandeja, Saco, Bidón, Balde, Tambor, Rollo, Metro, Metro cuadrado/cúbico, Docena, Par, Sobre, Display, Tubo y Estuche.
- Envasados: Caja, Bolsa, Botella, Frasco, Lata, Pack, Paquete, Bandeja, Saco, Bidón, Balde, Tambor, Pallet, Film, Estuche, Blíster, Tubo, Envase Tetra Pak, Sobre, Display, Doypack, Fardo y Contenedor.
- Simbologías: EAN-13 (GTIN-13), EAN-8, DUN-14, ITF-14/GTIN-14, Code 39, Code 128, GS1-128, UPC-A, UPC-E, QR y DataMatrix.
- Dos permisos nuevos para `tipos_codigos_barras`: ver/gestionar, sin asignaciones masivas a otros perfiles.

El nombre solicitado «Code 129» se interpreta como **Code 128**, una simbología comercial conocida. Se preserva «DUN-14» además de «ITF-14» porque son denominaciones frecuentes para logística, pero no se los trata como códigos equivalentes sin revisar la simbología del proveedor.

## Orden de implementación seguro

1. Crear respaldo nuevo de la base `if0_43460400_rutahyd2_es` (estructura y datos) desde phpMyAdmin y guardarlo fuera del sitio. **No sobrescribir ni reimportar SQL antiguos 12–19**.
2. Inspeccionar contra respaldo que la base sigue con las tablas españolas y no contiene ya una versión parcial de SQL20.
3. Importar `database/espanol/20_proveedores_productos_codigos_barras_ddl.sql` en la base correcta, desde la pestaña SQL de phpMyAdmin. Es exclusivamente **DDL**, crea cuatro tablas aditivas; no toca contraseñas ni registros.
4. Importar el archivo **21** `21_catalogos_referencia_semillas.sql`. **Es DML** porque llenar catálogos no es una operación DDL. Usa `INSERT IGNORE` y permisos `INSERT ... WHERE NOT EXISTS`; no sobreescribe registros existentes.
5. Ejecutar el archivo **22** de comprobación read-only y registrar los recuentos: cuatro tablas nuevas, once tipos, dos permisos, seis claves foráneas, unidades y envasados.
6. Solo después de la importación verificada, publicar backend Laravel desde la rama de funcionalidades con respaldo/rollback, y después frontend. Mantener la API existente operativa durante la transición y no inventar permisos.
7. Hacer smoke sin JWT para garantizar 401/403; luego realizar una prueba funcional real y supervisada de creación/edición con el administrador. No crear productos o códigos de prueba en producción automáticamente.

## Estado de verificación

- CI en MySQL temporal: https://github.com/msanzana/rutahyd2/actions/runs/44972888964 — **aprobado**, incluidos permisos, listas, precios, códigos, normalización y baja lógica.
- Frontend Playwright en rama de integración: https://github.com/msanzana/rutahyd2front/actions/runs/44973003447 — **aprobado**, 25 pruebas, popup escritorio 1440/820 y móvil 390, menús y asociación de proveedor.
- Cambios de la rama **NO publicados en MariaDB productiva**, hasta ejecutar DDL20 y semillas21 con respaldo.

## Política permanente

Producción sin demo; cambios de estructura únicamente en SQL **DDL**, datos de referencia en archivo DML separado y explícito; **NO Firecrawl**, no contraseñas en código ni chat. Los componentes visuales y pruebas de navegador pueden publicarse por fases sin tocar la base, pero los nuevos CRUD dependientes de las cuatro tablas NO deben ejecutarse contra el esquema anterior.
