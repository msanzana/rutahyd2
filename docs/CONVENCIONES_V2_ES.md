# Norma de nomenclatura de RutaH&D2 V2
**Acordada 2026-11-19.**

## Regla
Todas las tablas y columnas persistidas del sistema nuevo deben denominarse en **español**, con nombres en `snake_case` sin tildes/ñ y en minúsculas. Evitar abreviaturas ambiguas; conservar siglas estándar `id`, `rut`, `sku`, `dte`, `sii`, `iva` donde corresponda.

Ejemplos:
```
customers              -> clientes
products               -> productos
sale_notes             -> notas_venta
sales                  -> ventas
sale_details           -> detalles_ventas
sale_details.product_id -> detalles_ventas.producto_id
users.email            -> usuarios.correo_electronico
created_at             -> creado_en
updated_at             -> actualizado_en
```

**No usar nombres ingleses de campo en SQL nuevo.** Texto de interfaz para personas: español. Los comandos reservados SQL (`CREATE TABLE`, `FOREIGN KEY`) continúan en inglés por sintaxis de MySQL.

## Excepciones de contratos
- El Servidor DTE Laravel (`msanzana/sii-dte-backend`) define claves externas en inglés en su JSON, por ejemplo `external_id`, `items`, `receiver.name`, `dte_type`. No se deben traducir esos nombres HTTP; construir un adaptador DTO/mapper con términos internos en español y salida externa exacta.
- Laravel Sanctum y otros paquetes pueden requerir tablas convencionales específicas (p.ej. `personal_access_tokens`). Si se desea traducirlas habrá que registrar modelos/configuraciones personalizadas, no modificar las tablas del paquete a ciegas.
- La base alojada físicamente en InfinityFree tiene nombre `if0_43460400_rutahyd2`, impuesto por el proveedor; no se puede renombrar libremente.

## Estado real
La estructura en español de 34 tablas está preparada en esta rama. **No hay cambios verificados en la base de InfinityFree** por la sesión expirada. Los modelos y servicios originales de Laravel todavía utilizan nombres en inglés y **deben refactorizarse en otra tarea antes de ejecutar la migración en un entorno conectado a ellos.**

## Migraciones
1. Exportar y validar copia completa SQL desde phpMyAdmin.
2. Comprobar tablas/filas mediante `06_previa_infinityfree.sql`.
3. Según el esquema actual elegir `07_migrar_infinityfree_desde_32_tablas.sql` o `08_migrar_infinityfree_desde_34_tablas.sql`. **No ejecutar ambas.**
4. Actualizar código Laravel, consultas, APIs y pruebas antes de activar tráfico.
5. Verificar integridad de FK, recuento de registros y auditoría después.

No correr los scripts de migración sobre `rutahyd2_es_local`; allí se usa el DDL fresco.
