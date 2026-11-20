# Clasificación comercial H&D2 — transición segura de base de datos

**Estado: SQL preparado y NO ejecutado en InfinityFree.**

El backend histórico tiene `categorias_productos` (jerarquía libre por `padre_id`) y `productos.categoria_id`. La petición nueva usa **Rubro → Departamento → Familia → Línea**, **Marca → Modelo**, **Tipo de unidad**, **Gramos**, **Tipo de envasado** y **Afecto / Exento**.

Se prepararon dos migraciones **aditivas**, que no borran tablas:
- `database/espanol/13_catalogos_comerciales_aditivos.sql`: crea 10 catálogos y los dos códigos tributarios.
- `database/espanol/14_productos_clasificacion_campos.sql`: agrega 10 columnas opcionales y claves foráneas en `productos`; ejecutar **una sola vez**.

La ejecución real requiere:
1. Respaldo completo de `if0_43460400_rutahyd2_es`, confirmación de 34 tablas y permisos.
2. Probar primero sobre una copia MySQL 8 de la base con datos y revisar código existente que usa `categoria_id`.
3. Ejecutar 13 y 14 en ese orden y comprobar índices, foráneas y valores.
4. Implementar autenticación, validaciones y CRUD API de los diez catálogos para no aceptar ID cruzados (familia que no pertenece a departamento, etc.).
5. Migrar con criterio explícito los valores existentes de `categorias_productos` a la nueva taxonomía. **No deducir equivalencias automáticamente.**
6. Revisar tratamiento tributario **Afecto/Exento** antes de emitir DTE. Este valor por sí solo no valida legalmente una exención.

El frontend en `msanzana/rutahyd2front` permite administrar los diez catálogos en **modo local de demostración**, sin ejecutar SQL ni conectarse a la base real.

**No aplicar estas migraciones de forma automática durante un despliegue FTPS.**
