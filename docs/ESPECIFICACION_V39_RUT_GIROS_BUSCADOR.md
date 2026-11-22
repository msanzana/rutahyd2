# Ruta H&D V2 · V39 — Proveedores mínimos, RUT y selector universal

**Fecha:** 22-11-2026  
**Estado:** código completo en ramas V39, **sin publicar en InfinityFree**, **sin SQL ejecutado**.
Requiere importación manual del titular en phpMyAdmin y auditoría productiva antes del deploy.

## 1. Importación segura en etapas

1. **Respaldar la tabla `proveedores` completa** (los 15 campos a eliminar pueden tener datos históricos). Idealmente respaldar toda MariaDB Ruta H&D. No usar `migrate` Laravel ni SQL de escritura vía CI.
2. Ejecutar **DDL33** `database/espanol/33_giros_sii_catalogo_ddl.sql`: crea `giros_sii`, 6 caracteres como clave primaria para preservar los ceros de la izquierda.
3. Ejecutar **DDL34** `database/espanol/34_semillas_giros_sii_completo_674.sql`: 674 códigos económicos oficiales **únicos**, con glosa, primera/segunda categoría, información IVA y disponibilidad, tomados directamente de `https://www.sii.cl/ayudas/ayudas_por_servicios/1956-codigos-1959.html` a 2026-11-22. El generador `scripts/generar_ddl_giros_sii_v39.py` verifica integridad y registra procedencia.
4. Comunicar que **DDL33/34 están ejecutados**. Auditar con `SHOW COLUMNS` y `SELECT COUNT(*)`; desplegar fullstack V39 con controles de integridad y rollback, SIN alterar la base.
5. **Sólo después de comprobar interfaz y API V39**, ejecutar **DDL35** `database/espanol/35_proveedores_eliminar_campos_v39_ddl.sql` tras verificar respaldo. DDL35 es **destructivo**, no se ejecuta antes del frontend V39 porque el frontend anterior aún envía columnas que se retirarán. Comprobar en phpMyAdmin que las 15 columnas ya no existen. La pérdida de datos de esas columnas es irreversible sin el respaldo.
6. Verificar edición/creación de proveedores, validación RUT, giros, buscador único/múltiple, acceso de perfiles y rutas HTTP 401 sin sesión. No crear registros de prueba en producción.

## 2. Campos que conserva Proveedores

`id`, `nombre`, `razon_social`, `nombre_fantasia`, `rut`,
`tipo_contribuyente`, `giro`, `actividad_economica_codigo` (SII, siempre 6 dígitos
al crear/editar), `direccion`, `direccion_numero`, `ciudad_id`,
`correo_electronico` (un único correo), `telefono`, `plazo_pago_dias`,
`activo` y campos de auditoría/técnicos existentes.

**Se eliminan sólo de Proveedores:** `inicio_actividades`, `direccion_detalle`,
`codigo_postal`, `situacion_tributaria`, `correo_dte`,
`telefono_secundario`, `sitio_web`, `representante_legal`,
`representante_rut`, `contacto_nombre`, `contacto_cargo`,
`contacto_telefono`, `contacto_correo`, `moneda_preferida` y `observaciones`.

**RUT:** módulo cliente y proveedor validan algoritmo módulo 11, cuerpo de 7–8 dígitos,
dígito verificador numérico o K, normaliza a `12345678-5`. Campo opcional para
proveedores extranjeros, pero si se informa debe pasar validación. Cliente y backend
validan, no sólo el navegador. Este algoritmo comprueba DV, **no certifica estado ante SII**.

**Actividad económica:** seleccionar por botón que abre catálogo SII. El backend
acepta códigos de seis dígitos presentes y activos en `giros_sii`, y determina la
glosa desde fuente oficial. El campo `giro` del usuario no se usa como autoridad.
Se preservan registros previos con códigos de actividad no homologados; no se añade
FK retrospectiva destructiva antes de auditoría de legados.

## 3. Componente transversal de productos

`window.RutaHDBuscadorProductos.open({mode,title,preset,lockedFilters,initial,excludeIds,source,onSelect,fields,context,initialSearch})`
devuelve Promise:
- `mode:'single'`: producto `{id,sku,nombre,clasificacion,codigosVenta,...}` o `null`.
- `mode:'multiple'`: array de productos (selección estable entre páginas) o `null`.

`preset` define los valores iniciales; `lockedFilters` fuerza límites por módulo
(por ejemplo `{proveedor_id:4}`), `fields` limita los controles visibles.
`source` permite buscar en un backend específico (p. ej. ventas/clientes con
tarifa propia). El componente **no** asume precio/stock autorizado ni crea líneas.
Cada consumidor valida sus reglas al guardar y nunca debe confiar en `preset`
como medida de seguridad: los permisos siguen resolviéndose en Laravel.

Endpoint read-only bajo JWT `GET /api/v1/productos/buscador` filtra productos activos
por búsqueda global SKU/nombre/descripción/códigos venta o compra; clasificaciones
Rubro→Departamento→Familia→Línea, Marca→Modelo, unidad/envase/tributario; rango de
SKU e ID; proveedor con/sin vínculo; y paginación máximo 25.

**Integraciones efectivas V39:** `proveedores-productos` (selección individual para
mix) y venta con tarifa específica por cliente (selección individual). La selección
múltiple está implementada y probada, lista para órdenes de compra, facturas,
notas de crédito, guías, órdenes de trabajo y notas de venta al construir sus
respectivos formularios. No afirmar que esos formularios aún inexistentes ya la usen.

## 4. Iconos y pruebas

`listados-ui.js` detecta cualquier `th` Acciones y convierte sus botones en
iconos uniformes, con `title` y `aria-label`. Soporta listas de Clientes,
Proveedores, Usuarios, Ubicaciones, Mix y acciones de venta; incluye icono
editar, códigos de compra, eliminar/quitar, correo y seleccionar, sin suprimir
la confirmación modal para cambios de estado.

- Fuente SII: GitHub Actions `45059257728`, 674 códigos distintos, resultado OK.
- Frontend: workflow `.github/workflows/v39-ci.yml`, 35 tests Playwright
  contra API simulada, sin conexión con InfinityFree.
- Backend: `.github/workflows/v39-laravel-ci.yml`: sintaxis PHP, contrato
  API y prueba de RUT MOD11, 674 códigos en DDL34, 15 drops DDL35.
- **Todos estos tests son aislados y no prueban la versión V39 publicada**, pues
  el despliegue está deliberadamente condicionado al DDL33/34 manual.

## 5. Continuidad

Preservar V38 (producción), logo corporativo, rutas y hotfixes ya publicados.
Antes de publicar V39 combinar por tres vías `routes/api.php` y
`EntidadesComercialesControlador.php` con la copia real FTPS: la producción
incluye correcciones adicionales no presentes en fuente V38. Parchar
`index.html` quirúrgicamente conservando logo `hd-branding/logo-hd-site.png`
y V37; no subir el HTML de Github como sustitución total.
Conservar copia descargable, rollback, validar rutas JWT y JS/CSS.
**Firecrawl prohibido.** No ejecutar los DDL desde GitHub Actions ni conexiones.
