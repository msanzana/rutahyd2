# Ruta H&D V2 · Especificación V38 (2026-11-22)

## Estado del despliegue

**Código preparado en ramas feature y PR en borrador; NO publicado en InfinityFree.**
Los DDL29–32 no se ejecutan automáticamente. Exclusivamente el titular podrá
ejecutarlos manualmente tras respaldo en phpMyAdmin, base `if0_43460400_rutahyd2_es`.

## Importación manual y dependencias

1. `database/espanol/29_geografia_5_tablas_ddl.sql`: tablas paises, regiones,
   provincias, comunas, ciudades con claves foráneas restrictivas.
2. `database/espanol/30_semillas_geografia_chile_16_56_346.sql`: 1 país Chile,
   **16 regiones, 56 provincias y 346 comunas** con códigos CUT provistos por
   Gobierno Digital (dataset versionado https://github.com/felipesanma/chile-utils,
   licencia MIT). En ciudades incluye **347 referencias de localidad**; 346 son
   referencias comunales y 1 capital provincial adicional.
3. `database/espanol/31_proveedores_identificacion_sii_ddl.sql`: añadir columnas
   de identificación, giro, ACTECO, email DTE, representante, dirección, contacto,
   impuestos declarados y pago.
4. `database/espanol/32_ciudad_id_entidades_ddl.sql`: `ciudad_id` nullable en
   proveedores, clientes, usuarios, bodegas y empresas **solo si ya existe**. No
   crear empresa artificialmente ni perder clientes.comuna histórica. Los FK de
   este cuarto archivo se aplican una vez; comprobar SHOW CREATE TABLE antes
   de repetirlo. Evitar completar domicilios históricos adivinando localidades.

**Advertencia importante:** `ciudades` no es una copia exhaustiva de ciudades,
pueblos, aldeas y caseríos del INE. Una comuna puede albergar muchas localidades.
El origen de las referencias comunales se etiqueta `REFERENCIA_COMUNAL`, para
evitar confundirlas con una ciudad censal. Para completar esa cobertura se
necesitan datos de localidades georreferenciados con comuna fiable (cartografía
INE Censos 2017/2024). No inventar asignaciones comunales a partir de nombres.

## Contrato API

- GET /api/v1/ubicaciones/{paises|regiones|provincias|comunas|ciudades}
  con padre_id, page, por_pagina, buscar. JWT; autorización por administración
  o permisos comerciales consultivos.
- GET /api/v1/ubicaciones/jerarquia/{ciudadId} resuelve cinco niveles con JOIN.
- POST/PUT/DELETE /api/v1/ubicaciones/{tipo}[/{id}]: administrador únicamente;
  validación de jerarquía CUT, padres y restricciones de dependencias.
- POST/PUT /api/v1/recursos/{clientes|proveedores|bodegas} recibe únicamente
  `ciudad_id` de geografía, sin guardar país/región/provincia/comuna redundantes.
- GET /api/v1/recursos/{clientes|proveedores} devuelve nombres calculados por JOIN.
- POST/PUT /api/v1/usuarios recibe `ciudad_id`; la cuenta sigue su ACL y
  revocaciones originales.
- GET /api/v1/proveedores/mix: lista proveedores, mismos datos generales del
  directorio y `cantidad_productos_mix` calculada por COUNT DISTINCT producto_id
  de vínculos activos. No altera inventario.
- CRUD de `productos_proveedores` y códigos `productos_codigos_barra_compra`
  se conserva. Cada relación puede tener varios códigos de compra, unidades
  por código y simbología; recepción aún no se implementa.

## Proveedor y SII

Campos tributarios: RUT, razón_social, giro, actividad_economica_codigo,
tipo_contribuyente, inicio_actividades (declarado), situacion_tributaria
(declarada), dirección + ciudad_id (se resuelve comuna y región), correo_dte.
Complementarios: nombre_fantasia, representante_legal, representante_rut,
contacto_nombre, contacto_cargo, contacto_telefono, contacto_correo, teléfono
primario/secundario, correo, sitio_web, código_postal, observaciones,
moneda_preferida, plazo_pago_dias.

Los metadatos de "situación tributaria" e "inicio de actividades" son declarados
por usuario y **NO consultan ni certifican estado real ante SII**. RUT
normalizado por rutina chilena de dígito verificador cuando se informa.
La ficha tiene modo claro/oscuro y accesibilidad móvil.

## Salvaguardas

- No se destruyen columnas legacy, datos históricos, claves externas.
- No ejecutar PHP Artisan migrations ni SQL de escritura por connector o CI.
- La interfaz sigue requiriendo JWT. Al entrar a sesión los submenús se cierran.
- Menú: Administración → Ubicaciones → Países, Regiones, Provincias, Comunas,
  Ciudades. Administración solamente, incluido el buscador superior.
- Máximo 25 registros por página en mantenedores, paginadores arriba y abajo.
- La vista Mix muestra proveedores con columna cantidad, botón Editar que
  abre panel con asociados, costo/código de proveedor y códigos de compra.
- Condicionar cualquier despliegue a DDL29-32 verificados en producción y
  pruebas CI exitosas. Prohibido Firecrawl.

Fuentes: https://apis.digital.gob.cl/dpa/ y
https://github.com/felipesanma/chile-utils/tree/main/territorio ,
https://github.com/vicentezaror/regiones-chile ,
https://www.sii.cl/destacados/factura_electronica/guias_ayuda/emitir_factura_compra_electronica.htm .
