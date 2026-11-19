# RutaH&D2 — ampliación del ERD con DTE (2026-11-19)

Las **32 tablas existentes** de RutaH&D2 se mantienen sin cambios. Se proponen dos tablas nuevas, `sales` y `sale_details` (34 tablas al aplicar la migración).
**Estado de despliegue:** SQL versionado, todavía sin confirmar ejecución en InfinityFree.

```mermaid
erDiagram
    customers ||--o{ sales : "realiza"
    sale_notes o|--o| sales : "origina opcional"
    users o|--o{ sales : "registra"
    sales ||--|{ sale_details : "incluye"
    product_presentations o|--o{ sale_details : "presentacion"
    sale_note_items o|--o{ sale_details : "origen"
    sale_notes ||--o{ sale_note_items : "lineas"
    customers ||--o{ sale_notes : "pedido"
    sales {
        bigint id PK
        bigint customer_id FK
        bigint sale_note_id FK
        varchar sale_number UK
        varchar status
        date issue_date
        smallint dte_type
        char dte_external_id UK
        bigint dte_company_id
        bigint dte_external_system_id
        bigint dte_proposed_folio
        smallint dte_proposed_sii_document_type
        int dte_branch_office_number
        int dte_facility_number
        varchar dte_external_branch_code
        bigint dte_remote_document_id
        bigint dte_folio
        varchar dte_status
        varchar dte_sii_environment
        varchar receiver_document
        varchar receiver_name
        varchar receiver_giro
        varchar receiver_address
        bigint receiver_city_id
        varchar receiver_email
        decimal net_amount
        decimal exempt_amount
        decimal tax_amount
        decimal discount_amount
        decimal total_amount
        json header_payload
        json references_payload
        json dte_request_payload
        json dte_response_payload
        varchar dte_last_error_code
        datetime dte_requested_at
        datetime dte_created_at
        datetime dte_accepted_at
        datetime dte_rejected_at
        bigint created_by FK
        datetime created_at
        datetime updated_at
    }
    sale_details {
        bigint id PK
        bigint sale_id FK
        bigint sale_note_item_id FK
        bigint presentation_id FK
        int line_number
        varchar item_code_type
        varchar item_code
        varchar name
        text description
        decimal quantity
        decimal unit_price
        decimal discount_percent
        decimal discount_amount
        boolean tax_exempt
        decimal line_amount
        json extra_payload
        datetime created_at
        datetime updated_at
    }
    customers {
        bigint id PK
        varchar name
    }
    sale_notes {
        bigint id PK
        varchar number
    }
    sale_note_items {
        bigint id PK
        bigint sale_note_id FK
    }
    users {
        bigint id PK
    }
    product_presentations {
        bigint id PK
        bigint product_id FK
    }
```

El `company_id` del Servidor DTE no se envía en JSON: lo determina el JWT técnico.
Los campos `dte_company_id`, `receiver_city_id` y `dte_remote_document_id` son referencias al servidor **remoto**, no claves foráneas de la base local.

**Contrato inspeccionado:** `msanzana/sii-dte-backend` rama `develop`. No se ha confirmado un contrato explícito de versión `v2`.
