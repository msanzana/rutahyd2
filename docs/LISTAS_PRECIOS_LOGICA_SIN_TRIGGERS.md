# Ruta H&D V2 · Listas de precios sin triggers

## Decisión de arquitectura

Se **eliminan los cuatro triggers SQL propuestos**. No se instalan, no se actualizan ni se necesitan:
- control de lista base antes de INSERT;
- control de lista base antes de UPDATE;
- asignación automática al crear un cliente;
- creación de precio base al crear un producto y sincronización de SKU.

El reemplazo es el servicio centralizado
`app/Services/GestionListasPrecios.php`, ejecutado desde Laravel.

## DDL

`database/espanol/24_listas_precios_sin_triggers.sql` crea:
1. `listas_precios` — única Lista Base activa; unicidad resguardada también con índice único sobre columna calculada. No es un trigger.
2. `listas_precios_detalle` — única combinación lista/producto y SKU sincronizado por Laravel.
3. `clientes_listas_precios` — clave primaria `cliente_id`: una sola lista por cliente.
4. `productos.costo_neto` y `lineas.minimo_margen_precio_venta`.

Semilla `BASE` y backfill de clientes/productos existentes mediante SQL inicial explícito.
**Los precios existentes se conservan** desde `presentaciones_productos.precio_neto`.
Como no hay costos históricos confiables, margen queda `NULL` cuando el costo es cero.
No se recalculan precios históricos arbitrariamente.

`ALTER TABLE` es de **una sola ejecución**. Respaldar y revisar columnas existentes
antes de importar en producción. DDL24 está separado de DDL23 de preferencias/permisos;
la rama actual del backend contiene referencias a ambos y debe desplegarse
únicamente cuando el esquema correspondiente esté importado.

## Funciones Laravel y cuándo se llaman

| Función | Uso |
|---|---|
| `listaBaseId()` | Comprueba que exista una Lista Base activa |
| `crearLista()` | Valida unicidad y código reservado `BASE` |
| `actualizarLista()` | Impide desactivar/promover/convertir indebidamente la base |
| `asignarListaBaseCliente(clienteId)` | Se invoca en `EntidadesComercialesControlador::ejecutar` al crear clientes |
| `asegurarPrecioBaseProducto(productoId)` | Se invoca en `ProductoComercialControlador::guardar` después de guardar presentación |
| `sincronizarSkuProducto(productoId, sku)` | Se invoca al editar SKU de productos |
| `asignarListaCliente(clienteId, listaId)` | Sustituye base por una única especial o viceversa |
| `guardarPrecioLista(...)` | Calcula precio↔margen con costo, valida mínimo de la línea |
| `precioEfectivoCliente(clienteId,productoId)` | Usa EXCLUSIVAMENTE la lista seleccionada; jamás recurre silenciosamente a la base si falta el precio especial |

Las tres operaciones automáticas de cliente/producto son parte de la
**misma transacción** del CRUD. Si falla la asignación, también se deshace
el alta comercial.

La seguridad de concurrencia se apoya en transacciones Laravel, bloqueos
`FOR UPDATE` e índices `UNIQUE` de MariaDB/MySQL. No necesita triggers.

## Regla del margen

- Si se informa **costo + margen (%)**, se obtiene `precio = costo × (1 + margen / 100)`.
- Si se informa **costo + precio**, se obtiene `margen = (precio - costo) / costo × 100`.
- Si se informan ambos se comprueba su coherencia.
- `costo=0`: se permite únicamente precio cero sin porcentaje calculado.
- El backend rechaza un margen inferior a
  `lineas.minimo_margen_precio_venta`.
- La interfaz podrá mostrar el cálculo en tiempo real, pero Laravel repetirá
  toda la validación antes de persistir.

## Pendiente antes del despliegue funcional completo

El CRUD específico de listas, el selector de cliente y la integración definitiva
en `ServicioVenta` siguen siendo parte de la ampliación de precios, no de
este reemplazo puntual de triggers. La función de precio efectivo ya está
preparada, pero **no se ha conectado todavía al registro definitivo de ventas**.
No afirmar que la venta usa precios especiales hasta conectar y probar esa ruta.

**No se ha ejecutado DDL24 ni desplegado este servicio en producción.**
Ejecutar las pruebas solo con MySQL/MariaDB temporales de GitHub Actions.
