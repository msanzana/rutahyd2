# RutaH&D2 — integración a Servidor DTE Laravel
Fecha de investigación: 2026-11-19

## Fuente inspeccionada
GitHub: `msanzana/sii-dte-backend`, rama `develop`
- `app/Modules/Dte/Presentation/Http/Requests/StoreDteDocumentRequest.php`
- `app/Modules/Dte/Presentation/Http/Controllers/DteDocumentController.php`
- `app/Modules/Dte/Application/DTOs/CreateDteDocumentInputDto.php`
- `app/Modules/Dte/Application/DTOs/DteLineItemInputDto.php`
- `app/Modules/Dte/Application/DTOs/DteReferenceInputDto.php`
- `app/Modules/Dte/Presentation/Http/Routes/api.php`
- `routes/api.php`
- Migraciones de dte_documents, dte_line_items, dte_references

**Nota de versión:** Se inspeccionó rama develop; NO se ha verificado una especificación expresamente etiquetada `v2`. La ruta inspeccionada es `POST /api/internal/dte/document`, protegida por `jwt.access` + `jwt.dte.scope`. La empresa (company_id) se toma del token, **no** del JSON.

## Modelo local: 34 tablas
- 32 tablas previas **sin modificaciones**.
- `sales` nueva: cabecera de la venta, receptor, totales, referencia a `sale_notes`, y estado de emisión DTE.
- `sale_details` nueva: líneas de venta y contrato `items[*]` con 1..60 líneas; **único identificador de producto = `product_id`**. No persiste SKU, nombre ni descripción duplicados.
- `sales.customer_id` -> `customers.id`, `sales.sale_note_id` -> `sale_notes.id`, `sales.created_by` -> `users.id`.
- `sale_details.sale_id` -> `sales.id`, `sale_details.product_id` -> `products.id`, `sale_details.sale_note_item_id` -> `sale_note_items.id`.
- `sales.references_payload` es un array JSON de referencias DTE para respetar el requisito de **solo dos nuevas tablas**; validar su estructura en la capa Laravel.
- `sales.header_payload` JSON cubre encabezados adicionales (p.ej. ind_servicio).
- `sales.dte_company_id` = ID de empresa en el servidor DTE, NO un FK local. `receiver_city_id` = ID de ciudad en el servidor DTE, NO un FK local.
- Almacenar un snapshot del receptor en la venta, aunque cambie la ficha del cliente.
- `sales.dte_remote_document_id` refiere a un documento del servidor remoto; NO es una clave foránea local.
- `sales.dte_external_id` representa el UUID de idempotencia enviado al servidor. Generarlo en la app UNA VEZ, guardarlo, no regenerarlo al reintentar.
- `sales.dte_external_system_id` solo para flujo técnico con reservas de folio. Si se usa, `dte_proposed_folio` obligatorio.

## Payload esperado
```json
{
  "external_id": "UUID-estable-por-venta",
  "dte_type": 33,
  "issue_date": "2026-11-19",
  "receiver": {
    "document": "RUT",
    "name": "Razón social",
    "giro": "Giro",
    "address": "Dirección",
    "city_id": null,
    "email": null
  },
  "items": [
    {
      "item_code_type": "SKU",
      "item_code": "COD-1",
      "name": "Producto",
      "description": null,
      "quantity": 1,
      "unit_price": 10000,
      "discount_percent": 0,
      "discount_amount": 0,
      "tax_exempt": false,
      "extra_payload": null
    }
  ],
  "references": [],
  "header_payload": {}
}
```

## Validaciones del repositorio fuente
- `dte_type` requerido integer (tipos efectivamente autorizados dependen de datos y dominio del servidor).
- `receiver.document` max20, `receiver.name` max120; dirección obligatoria para tipo 33.
- `receiver.giro` max150, dirección max150, correo max150, ciudad ID remoto opcional.
- Líneas: 1..60; nombre max120, item_code_type max20, item_code max50.
- Cantidad >0; precio >=0; descuentos % entre 0 y 100; monto >=0; boolean tax_exempt.
- `header_payload.ind_servicio` obligatorio y uno de 1,2,3,4 para DTE 39/41.
- Referencias permitidas: referenced_dte_type, referenced_folio, referenced_issue_date, referenced_code, reason y extra_payload.
- El source request presenta el campo `referenced.*.extra_payload`, singular, pero su controlador trabaja con `references`: revisar este detalle con pruebas de contrato antes de enviar extra_payload de referencias.

## Regla de producto (2026-11-19)
- `sale_details.product_id` es FK obligatoria a `products.id`. No existe `sale_details.presentation_id`.
- `products.name`, `products.sku` y `products.description` se consultan mediante el repositorio/modelo `Product` cuando se prepara el DTE.
- Cantidad, precio, porcentaje y monto de descuento, impuesto/exención y total de línea se conservan en el detalle por ser datos históricos de la transacción.
- `SaleDtePayloadService::buildAndFreeze($saleId)` persiste el JSON del primer intento en `sales.dte_request_payload`, para que cambios posteriores en el catálogo no alteren reintentos. La integración HTTP todavía no implementada debe usar ese método.
- Una migración complementaria maneja esquemas anteriores: primero crea `product_id` y migra su valor desde `product_presentations.product_id`; respalda campos históricos en `extra_payload.legacy_product_snapshot` y solo entonces retira columnas redundantes.

## Trabajo pendiente
1. Generar/validar JWT técnico, ámbito empresa+sistema externo, y catálogo remoto de ciudades; no guardar tokens en SQL ni GitHub.
2. Implementar un cliente HTTP con reintentos controlados, autenticación, logs sin secretos y sincronización de estado SII.
3. Probar tipos DTE 33/34/39/41/52/56/61 según autorización real, CAF y entorno SII.
4. Ajustar a la especificación **v2** definitiva cuando se proporcione un contrato explícito.
5. Revisar reglas de impuestos/IVA y aritmética decimal (los servicios de ejemplo usan float y no están listos para emisión real).
6. Evitar registrar datos personales completos en logs. Cifrar/limitar acceso a dte_request_payload y dte_response_payload.
7. El esquema SQL puede instalarse mediante phpMyAdmin en InfinityFree. No configurar FTP ni emitir DTE por este cambio.
