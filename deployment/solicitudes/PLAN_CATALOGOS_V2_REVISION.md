# Ruta H&D2 — Cierre de catálogos comerciales (V2)

**Estado:** código propuesto para revisión. No instalado ni migrado en InfinityFree.

## Lo recuperado y reutilizado
- `database/espanol/13_catalogos_comerciales_aditivos.sql` contiene los diez catálogos y las relaciones padre-hijo.
- `database/espanol/14_productos_clasificacion_campos.sql` incorpora diez claves foráneas opcionales al producto.
- La UI violeta en `msanzana/rutahyd2front` ya tiene los diez mantenedores, por ahora con `localStorage`.

## Este incremento
- `GET /api/v1/catalogos/{catalogo}`: listar catálogo (respuesta `items`).
- `POST /api/v1/catalogos/{catalogo}`: crear.
- `PUT /api/v1/catalogos/{catalogo}/{id}`: editar.
- `DELETE /api/v1/catalogos/{catalogo}/{id}`: eliminar si no tiene referencias.
- `PATCH /api/v1/productos/{id}/clasificacion`: actualizar solo los diez campos de clasificación de un producto existente, sin modificar `categoria_id`, precios, stock ni IVA.
- Todos requieren `token.rutahd` y `permiso.rutahd:productos.gestionar` (el administrador conserva su bypass habitual).

### Contrato de catálogos
```json
{"nombre":"Alimentos","activo":true,"parentId":null}
```
Para `departamentos`, `familias`, `lineas` y `modelos`, `parentId` es el ID numérico de su padre. Para los demás se envía `null`. En `gramajes`, `nombre` representa gramos numéricos (por ejemplo `500`); la API normaliza a tres decimales. Las condiciones tributarias son `Afecto` y `Exento`.

### Antes de aplicar a InfinityFree
1. Obtener el esquema **real** de la base y respaldar tablas y datos. Verificar si existen tablas y columnas antes de ejecutar los scripts: el SQL 14 no es idempotente.
2. Confirmar que InfinityFree ejecuta una versión Laravel compatible, que `tokens_acceso` y `productos.gestionar` existen y que la API no está expuesta sin configuración privada.
3. Ejecutar el script 13 y luego 14 **solo** si el esquema real corresponde a la rama `feature/backend-v2-es`. No ejecutar el DDL `database/sql/01_core_catalog.sql` sobre una base en español.
4. Comprobar la estructura, FKs, productos antiguos e integridad de `categoria_id` con consultas de solo lectura.
5. Desplegar backend únicamente tras probarlo con autenticación y con respaldo/reversión; instalar la UI conectada a la API como paso posterior. **Nunca** subir `.env`, contraseñas ni tokens al repositorio.
6. Probar una transacción de creación y una de edición bajo cuenta de prueba, sin tocar operaciones reales; confirmar bloqueos 401/403/409/422.

### Limitaciones de este incremento
- El frontend **sigue siendo demostración local**: aún no sustituye el `localStorage` por API; no se ha autorizado la conexión productiva.
- El antiguo árbol `categorias_productos` permanece intacto: su correspondencia con el árbol nuevo necesita un mapa de migración de datos real; no forzar una equivalencia automática.
- El endpoint de clasificación requiere **los diez identificadores** y solo actualiza un producto que ya existe. La creación general del producto y la migración de registros antiguos requieren integración posterior.
- Ningún cambio de esta rama activa un flujo de despliegue: el workflow actual usa una rama/activador distinto.

### Validaciones a ejecutar al integrar
- Rubro → departamento → familia → línea válidos; modelo de la marca correcta.
- Categoría referenciada no puede borrarse, desactivarse ni cambiar de padre.
- Identificadores inexistentes e inactivos devuelven 422; duplicados 409.
- Un token inválido devuelve 401; usuario sin permiso 403.
- Los productos heredados conservan `categoria_id`, proveedor, SKU, precio de presentaciones y datos históricos.
