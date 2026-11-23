# V55 — Órdenes de compra, estados globales, PDF y correo

Esta rama está desarrollada para revisión y pruebas. No está desplegada en producción.

## Catálogo y relación

- DDL38: database/espanol/38_estados_modulos_oc_productos_ddl.sql.
- estados: id, codigo único, nombre único. Semillas ACTIVA, RECEPCIONADA y NULA.
- modulos: id, codigo único, nombre único. Semilla ORDEN_COMPRA.
- modulos_estados: id propio, modulo_id, estado_id, posición y activo; relación única por par.
- ordenes_compra.estado_id: FK obligatoria. Se conserva el texto histórico estado durante transición.
- detalles_ordenes_compra.producto_id reemplaza presentacion_id con actualización previa basada en presentaciones históricas.

## Reglas del negocio

La nueva OC nace ACTIVA. La acción Anular cambia a NULA, conserva todos los registros y no altera existencias. RECEPCIONADA queda reservada para Recepciones; no se simula ni se cambia manualmente en este módulo.

Para guardar, se requiere un proveedor activo con correo válido y un detalle de productos activos asociados a ese mismo proveedor. El buscador recibe proveedorId como restricción fija de entrada; no es un filtro editable dentro de la ventana. El backend vuelve a verificar esa relación, independientemente del frontend.

Los importes y el estado son de solo lectura. MariaDB calcula cada subtotal por línea usando DECIMAL; el backend aplica 19% a los ítems no exentos según el catálogo de clasificación y 0% a EXENTO. Es una estimación operativa de compra; no sustituye un DTE ni el futuro diseño fiscal.

## Documento y aviso

El PDF A4 corporativo es generado por el servicio PdfOrdenCompra y utiliza el mismo generador tanto para el adjunto SMTP como para Ver PDF mediante JWT. Ningún token aparece en las URL del proveedor. La orden se confirma en MariaDB antes de enviar el correo, por lo que un fallo SMTP no causa una nueva inserción; la respuesta comunica el fallo y la interfaz permite reenviar.

El PDF consultado refleja la versión actual de la orden. Si se edita tras enviarla, puede cambiar respecto del adjunto original; un archivo histórico inmutable requiere versionado documental adicional que no forma parte de esta etapa.

## Despliegue seguro

1. Respaldo comprobado de la base vigente.
2. SHOW COLUMNS FROM ordenes_compra, SHOW COLUMNS FROM detalles_ordenes_compra y revisar estados históricos antes de convertirlos.
3. Importación MANUAL del DDL38 por el titular en phpMyAdmin y confirmación. Nunca ejecutar DDL ni DML productivo desde GitHub Actions.
4. Validar estructura y vínculos con SHOW y SELECT a tablas propias, sin consultas a catálogos internos restringidos.
5. Recién entonces publicar backend/frontend mediante FTPS con hashes preflight, respaldo, reversión y versionado de JS/CSS.
6. Aceptación autenticada con una operación legítima y confirmación de entrega del correo/PDF, sin datos ficticios productivos.

## Alcance excluido

Recepciones, costo promedio ponderado, movimientos de bodega, cuentas corrientes, DTE y reversas siguen fuera de esta fase. Firecrawl permanece prohibido.
