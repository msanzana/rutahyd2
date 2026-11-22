# Reglas operativas obligatorias — Ruta H&D V2
Fecha: 2026-11-22 (Chile)
Origen: instrucción expresa del titular del proyecto.

## Herramientas
- **Firecrawl está prohibido** en Ruta H&D V2: no utilizar Firecrawl, Hangar, su MCP, agentes, scraper, navegador ni funciones relacionadas. La integración Firecrawl de ChatGPT fue desinstalada por solicitud del titular el 2026-11-22.
- Para código, auditoría de revisiones y despliegues autorizados, utilizar GitHub y los mecanismos de CI/CD ya documentados. No habilitar herramientas de acceso alternativas que violen la restricción anterior.

## Base de datos
- Toda modificación del esquema de MariaDB/MySQL se entrega **exclusivamente como DDL SQL** para ejecución directa del titular en **phpMyAdmin**.
- No ejecutar migraciones Laravel, ALTER/CREATE/DROP automáticos, SQL de escritura por conectores, ni cambios de estructura por navegador remoto, APIs, scripts o CI/CD.
- El titular ejecuta el DDL en phpMyAdmin y comunica el resultado. La confirmación del titular cuenta como declaración de ejecución; no equivale a auditoría independiente del esquema.
- Conservar reglas de respaldo, no publicar credenciales, y no ejecutar SQL de escritura en producción para pruebas.

## Continuidad y UX
- Reutilizar pruebas anteriores exitosas y verificar únicamente deltas o regresiones justificadas. No repetir pruebas por rutina.
- Interfaz corporativa violeta, **compacta, densa y legible**, con mayor información visible, sin reducir accesibilidad, objetivos táctiles ni adaptación móvil.
- Producto: preservar la especificación de cálculo de margen/precios del formulario y encabezados ordenables, clasificaciones, etiquetas de precios y códigos del listado.
- DDL25 `productos_fotos`: el titular declara haberlo ejecutado el 2026-11-22. Verificación independiente pendiente.
- Prohibido presentar como publicada una modificación por el solo hecho de estar en GitHub o de superar pruebas simuladas: exigir evidencia de despliegue y revisión del sitio real.

## Regla de paginación transversal — 2026-11-22
- Todos los listados de registros y elementos del proyecto muestran **máximo 25 registros visibles por página** con controles de anterior/siguiente cuando corresponda.
- Para módulos con API paginada, solicitar páginas reales de máximo 25 sin fingir paginación global de resultados parciales.
- Para catálogos y relaciones ya cargados íntegramente, paginación local de 25 conservando filtros y acceso a los registros siguientes.
- No ocultar ni eliminar registros a causa de la paginación; ventas y listados conservan sus totales y permisos. Fotografías: 25 miniaturas por página sin impedir que la imagen grande recorra las N fotos.
