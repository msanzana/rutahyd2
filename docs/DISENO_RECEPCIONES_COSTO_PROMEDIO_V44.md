# Ruta H&D V2 · V44 — Contrato de recepción de mercaderías y costo promedio ponderado (diseño, SIN desplegar)
Fecha: 2026-11-22 · Base: API V42 · Estado: especificación previa a DDL / pruebas

## Fuente de verdad y estado existente
- `productos.costo_promedio_ponderado` es DECIMAL(15,4) y exclusivamente lectura en la ficha comercial (DDL28, V37). Se actualiza **solo** desde una recepción confirmada, nunca desde POST/PUT Producto.
- `saldos_inventario` tiene clave compuesta `(bodega_id,presentacion_id)` y `cantidad DECIMAL(15,3)`. `presentaciones_productos` conecta `producto_id` y `unidades_por_envase DECIMAL(15,3)`; `movimientos_inventario` registra presentación, bodega, cantidad, referencia y usuario.
- `ordenes_compra` y `detalles_ordenes_compra` ya existen. La compra a proveedor está normalizada en `productos_proveedores`, con `ultima_recepcion_id` y `fecha_ultima_recepcion` reservadas sin actualización hasta este módulo.
- En producción la V42 está publicada; **este documento no implementa ni despliega recepciones y no autoriza cambios SQL**.

## Flujo propuesto para la primera implementación
1. Crear recepción **borrador**: proveedor activo, bodega activa, fecha y referencia de documento, orden de compra opcional. Puede editar líneas mientras esté borrador. No altera saldos, costo medio, SKU, precios ni movimientos.
2. Agregar líneas: vínculo existente proveedor-producto, presentación compatible con el producto, cantidad positiva en presentación, unidades por envase (snapshot), costo **neto** efectivo por unidad base (CLP, 4 decimales), subtotal. Un código de compra puede resolver presentación/unidades, pero no decide silenciosamente el costo.
3. Vista previa de cantidades de presentación y unidades base, total neto y variación propuesta de costo promedio, sin guardar esas proyecciones como costo real. Validar números decimales en servidor y frontend.
4. **Confirmar una sola vez** con clave de idempotencia; bloquear recepción, vínculos, productos y saldos necesarios en transacción. Validar coherencia de proveedor, producto y presentación; subir saldos, crear movimiento `tipo_movimiento='recepcion_compra'` por línea y actualizar costo promedio del producto. Actualizar `ultima_recepcion_id` y fecha del vínculo. Commit total o rollback completo.
5. Una recepción confirmada es inmutable. Las correcciones posteriores requieren un flujo separado de reversa, con trazabilidad y política de valorización, no `DELETE` ni estado editable que deje saldos incongruentes.

## Cálculo de costo promedio ponderado propuesto
Para cada producto, unificar unidades de todas las presentaciones:
- Q0: suma de saldos previos `cantidad_presentacion × unidades_por_envase` en todas las bodegas.
- C0: costo promedio ponderado actual por unidad base, tomado del registro del producto.
- q: unidades base recibidas = `cantidad_recibida_presentacion × unidades_por_envase_snapshot`.
- c: costo neto real por unidad base de la línea.
- Si Q0>0 y tiene valorización confiable: `C1 = (Q0×C0 + q×c)/(Q0+q)`.
- Si Q0=0: `C1=c`.
- No redondear a float; decimal exacto/BCMath y redondeo final a 4 decimales. Al recibir varias líneas del mismo producto, combinar las líneas dentro de la **misma transacción**, sin dobles lecturas de Q0.
- Si Q0>0 y C0=0 sin saldo inicial valorizado o validado, **no suponer** que el inventario tiene costo cero: exigir regularización/valoración inicial antes de confirmar para no corromper el promedio.

## Estructura DDL a desarrollar y validar en MySQL/MariaDB efímero
- `recepciones_mercaderia`: cabecera; id; numero interno único; proveedor_id; bodega_id; orden_compra_id nullable; tipo/número de documento de origen; estado; fecha; creado_por/confirmado_por; fecha_confirmacion; token de idempotencia; fecha de creación/actualización.
- `recepciones_mercaderia_detalle`: id; recepcion_id; linea; `producto_proveedor_id`; `presentacion_id`; cantidad en presentaciones; unidades_por_envase_snapshot; unidades_base; costo_neto_unitario_base; subtotal neto; movimiento_inventario_id nullable y único. FKs restrictivas, identificador de línea único por recepción, CHECK positivos cuando corresponda.
- El DDL se entregará **solo como SQL para ejecución manual del titular en phpMyAdmin**. Los scripts de CI cargan bases efímeras; nunca ejecutar migraciones de Laravel ni DDL desde CI/servidor.
- No añadir FK de `productos_proveedores.ultima_recepcion_id` hasta confirmar exactamente la tabla y el historial de recepción. Nunca apuntar a tabla inexistente.

## Concurrencia, seguridad y pruebas obligatorias
- Middleware Bearer/JWT, permiso `compras.gestionar` y submódulo de recepción explícito, sin abrir rutas de escritura genérica a inventario.
- Orden de bloqueo determinista (IDs de producto, presentación, bodegas), `lockForUpdate`, transacción y reintento controlado ante deadlocks. Encabezado idempotente para impedir doble recepción al reintentar.
- Pruebas efímeras: 1 presentación; varias presentaciones del mismo SKU; lotes de 2 líneas del mismo SKU; Q0=0; C0=0 con saldo previo; producto/proveedor inactivos; vínculo no coincidente; costo/cantidad inválidos; idempotencia; rollback si falla movimiento; usuarios sin permiso; página 25; promedio exacto a cuatro decimales.
- Pruebas productivas: únicamente GET y smoke 401 sin credenciales; no crear recepciones de prueba en producción.

## Pendientes que dependen de la política comercial
- Acordar qué costo neto prima (precio por contrato, orden de compra, factura), flete/descuentos y devoluciones: esta primera versión supone **costo neto unitario real confirmado de la recepción** y exige validación explícita.
- Confirmar inventario inicial y `unidades_por_envase` de presentaciones históricas antes de costear Q0; no modificar retroactivamente presentaciones con stock.
- Dejar para una etapa específica reversas, mermas, notas de crédito, costos por lotes y valorización histórica.
