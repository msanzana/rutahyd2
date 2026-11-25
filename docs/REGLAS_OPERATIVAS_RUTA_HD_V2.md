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

## Regla de interruptores (switches) corporativos — 2026-11-22
- Todos los controles visibles de tipo activado/desactivado que antes utilizaban una casilla se presentan como **switches compactos violetas** en el frontend Ruta H&D V2, manteniendo legibilidad y adaptación móvil.
- Mantener comportamiento nativo del control de formulario, estado checked, navegación por teclado, foco visible y tratamiento de disabled; no alterar semánticas de campos de selección múltiple donde implique romper permisos o relaciones.
- Conservar el switch temático oscuro existente, sin duplicarlo.

## Regla visual de paginación y checkboxes — 2026-11-22
- Las páginas del sistema deben representarse con **números en círculos**; la página actual lleva el **violeta corporativo** como fondo y contraste legible, marcado con `aria-current=page`.
- Mantener Anterior/Siguiente, puntos suspensivos para secuencias extensas y máximo 25 registros por página. La navegación remota siempre debe consultar el número de página real en Laravel, sin reutilizar filas de otra página.
- Los checkboxes de selección múltiple (p. ej., permisos Ver/Gestionar) muestran esquinas suavemente **redondeadas**, marca de verificación legible, foco accesible y soporte de teclado. Los activadores binarios independientes mantienen sus switches corporativos.
- Interfaces compactas y responsive, con contraste violeta y estados deshabilitado/foco perceptibles.

## Regla de estados, tooltips y precios — v34
- **No presentar botones Eliminar/Borrar en tablas de registros.** Usar switches compactos de estado (activar/desactivar) únicamente cuando existe operación reversible respaldada por Laravel; confirmar la desactivación, revertir visualmente ante error y preservar historial. No exponer por accidente rutas destructivas al usuario. La galería de fotografías mantiene su administración independiente y requiere confirmación al eliminar una foto.
- Respetar registros protegidos: administradores, Lista Base y clasificaciones tributarias invariantes no se desactivan mediante switches.
- Los botones de guardado se titulan «Guardar» (sin «en MySQL»); en fichas de Producto usar «Precio» como encabezado visual sin alterar el significado tributario del valor almacenado.
- En Productos, etiquetas de precios por lista y códigos de barras aparecen en pila vertical dentro de cada celda; el ancho de la tabla se ajusta al contenido con desplazamiento horizontal adaptable.
- Tooltips de acciones con texto personalizado **debajo del botón**, portados fuera de tablas para evitar recortes por overflow o columnas sticky; navegación por teclado con foco visible.
- Los listados no deben exhibir avisos tecnológicos persistentes como «CRUD real con JWT» o «Producto actualizado en MariaDB»; los mensajes de error sí deben conservarse.
- El switch de activo en Lista de precios utiliza diseño violeta compacto específico (42 × 24 px) que prevalece sobre reglas genéricas de inputs.

## Regla de navegación global y paginación doble — v35 (2026-11-22)
- En todos los módulos de mantenimiento de ítems, los botones **Nuevo/Nueva/Crear/Asociar** destinados a altas de registros van **a la derecha del toolbar del listado**, antes de la tabla; filtros y búsqueda quedan a la izquierda. La ubicación se conserva en vista móvil.
- Cuando existan varias páginas, se presenta un **paginador numérico circular arriba y otro abajo** de la lista, compartiendo página seleccionada, número de registros y límite de 25 por página. No sustituir paginación remota por segmentación local de una página ya cargada.
- Al hacer clic en una flecha o número de cualquiera de los dos paginadores, navegar a la página elegida y **desplazar la vista a la parte superior**. El scroll respeta preferencia de movimiento reducido.
- Al iniciar sesión o al restaurarla, todos los **submenús principales parten contraídos** (Administración, Productos, Proveedores) y el menú móvil se cierra. Solo las interacciones del usuario los expanden nuevamente.
- Sobre el menú oficial incluir un **buscador compacto de opciones y subopciones**, tolerante a tildes y palabras omitidas, con búsqueda por varios términos y navegación de teclado. Construir resultados desde las opciones oficiales y filtrarlos por permisos JWT/ACL; nunca mostrar opciones ocultas por permisos ni inventar rutas.
- Mantener paleta violeta, densidad legible, accesibilidad y responsive. Firecrawl continúa prohibido; para cambios de esquema solo DDL manual en phpMyAdmin.

## Regla de costo promedio ponderado en Productos — V37 (2026-11-22)
- El producto mantiene `costo_neto` comercial editable; a su lado aparece `costo_promedio_ponderado` como campo **solo lectura** y con diseño corporativo responsive.
- La ficha de consulta también expone el costo promedio junto al costo neto, sin recalcularlo en frontend.
- DDL28 `database/espanol/28_costo_promedio_ponderado_productos_ddl.sql` crea la columna `DECIMAL(15,4) NOT NULL DEFAULT 0.0000`. Los productos existentes y futuros parten en 0 hasta la implementación de recepciones.
- Prohibido aceptar `costoPromedioPonderado` en POST/PUT de Productos; la actualización automática corresponderá a la futura recepción de mercadería y su metodología de costo/stock, que **NO está implementada** en V37.
- El DDL lo ejecuta únicamente el titular en phpMyAdmin con respaldo previo. No anunciar valor persistente en producción ni publicar funcionalidad como completa antes de confirmar DDL28 y despliegue del API compatible.


## Regla obligatoria de seguimiento y cierre de despliegues — V68 (2026-11-24)
- Cada despliegue autorizado debe seguirse desde el disparo del workflow GitHub Actions hasta su resultado terminal, dentro de la sesión de trabajo y mientras las herramientas lo permitan. Registrar ID, enlace, rama y commit exactos; consultar periódicamente el estado de la ejecución y sus jobs/steps. No dar por terminada una tarea solo por haber subido el código o generado el workflow.
- Revisar el feedback real de ejecución: validaciones nuevas pertinentes, respaldo, FTPS, cotejo de bytes, comprobación del sitio y protección JWT, y estado del rollback según corresponda. Si falla, identificar la etapa y revisar logs relevantes sin exponer secretos; verificar restauración y corregir únicamente la causa, evitando despliegues duplicados o concurrentes. No repetir pruebas exitosas ni versiones anteriores por rutina.
- Solo declarar «publicado y verificado técnicamente» cuando el workflow concluya SUCCESS y exista evidencia de las comprobaciones previstas. Separar siempre verificación técnica de aceptación funcional autenticada y de funcionalidades todavía pendientes.
- Si el workflow continúa en cola o ejecución al alcanzarse el límite operativo de la sesión, informar su estado exacto, enlace e hitos pendientes; nunca afirmar éxito, prometer seguimiento en segundo plano sin automatización autorizada ni iniciar otra publicación mientras exista una ejecución activa. Retomar la misma ejecución por su ID en la siguiente revisión.
- Incorporar al diario de continuidad el resultado final comprobado, enlace de la ejecución, alcance real, respaldo/rollback y pendientes, de modo que la siguiente sesión no repita trabajo terminado.

## Regla obligatoria de recepción directa e inventario — V95 (2026-11-25)
- Confirmación expresa del titular: una recepción directa (única, completa, cuadrada y sin consolidación) ingresa inmediatamente al stock disponible y aumenta la existencia física una sola vez. No genera entrada ni transferencia a tránsito, ni exige consolidación.
- En la misma transacción se actualiza CPP según V77, costo de entrada, Kardex de ENTRADA_DIRECTA con tipo documental y folio original, libro de compras y estado RECEPCIONADA de la OC.
- Recepciones parciales, múltiples vigentes o descuadradas mantienen tránsito hasta consolidación; ésta libera tránsito a disponible sin incrementar nuevamente el físico. Reservas de venta corresponden a comprometido, no a tránsito.
- La opción de completar cierre directo histórico sólo regulariza recepciones anteriores acreditadas en tránsito; nunca es un paso de una recepción directa nueva. No duplicar stock ni movimientos. Mantener esta regla en cambios futuros y pruebas focalizadas.
