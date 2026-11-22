# Ruta H&D V2 — Reanudación V43, 22-11-2026

## Incidencia nueva — Proveedores → Proveedores productos

- El titular aportó captura del directorio `Proveedores · Mix de productos` con aviso `No se pudo consultar el servidor (HTTP 500).`, antes de presentar filas de proveedores.
- Ruta del directorio: `GET /api/v1/proveedores/mix`, declarada en `routes/api.php`, protegida con token y permisos `compras.gestionar` y submódulo `proveedores_productos,ver`.
- Implementación: `ProductosProveedoresControlador::mix` consulta `proveedores`, subconsulta agrupada de `productos_proveedores` y uniones geográficas hasta `paises`. Existe además `GET /api/v1/proveedores-productos` para vínculos individuales. No confundir ambos endpoints.
- El DDL29 documenta las relaciones `ciudades.comuna_id`, `comunas.provincia_id`, `provincias.region_id`, `regiones.pais_id`. La auditoría V39 constató DDL33-35, pero no prueba que la consulta exacta del directorio funcione en la sesión actual.
- **Causa aún no certificada**: HTTP 500 no identifica columna ni excepción; queda prohibido atribuirlo a una columna sin logs o auditoría de solo lectura.
- Se añadió en esta rama el workflow `.github/workflows/v43-diagnostico-mix-500.yml` para recuperar solo logs por FTPS, clasificar errores del controlador sin exponer SQL ni datos personales y eliminar copias temporales. No escribe en MySQL ni cambia archivos del hosting. Verificar su ejecución antes de afirmar un resultado.
- No aplicar DDL por rutina: únicamente entregar un DDL adicional al titular si la evidencia acredita diferencia de esquema. Sin migraciones Laravel ni Firecrawl.
- Al tener diagnóstico, hacer corrección mínima, probarla en aislamiento, preflight con los archivos exactos de producción V42, respaldo FTPS y rollback, y comprobar el despliegue antes de declarar arreglado.

## Pendientes previos preservados

1. Consolidar las fuentes V38→V42, actualmente distribuídas en PR en borrador; no fusionar ni publicar ciegamente debido a parches productivos acumulados.
2. Verificar con sesión autorizada `GET /api/v1/giros-sii?buscar=Fabricación de otros productos` (el SELECT V41 devolvió 7 resultados, el HTTP autenticado no fue comprobado).
3. Validación funcional autorizada de alta en lote de productos del proveedor, `POST /api/v1/proveedores-productos/lote`, sin registros ficticios ni escrituras de smoke.
4. Verificar operaciones auténticas de Productos y Listas de precios tras los DDL registrados, sin repetir los DDL ni escribir para pruebas.
5. Diseñar e implementar recepción de mercaderías y costo promedio ponderado, que sigue sin automatizarse. Separar primero contratos de recepción, esquema DDL manual, y lógica transaccional del inventario.

## Criterio de cierre del HTTP 500

Cerrado solamente si se confirma la causa por log o consulta SELECT, se verifica la corrección exacta de la ruta, pasan pruebas en entorno aislado, y se constata en producción con sesión habilitada y HTTP 200 que devuelve `data`, `total` y paginación coherente. Un estado API público 200 o JWT 401 sin sesión no valida el endpoint autenticado.

## Reglas permanentes

Preservar UX violeta y preloader V42, menú y versiones anteriores, paginación 25 arriba/abajo, botones de acciones con iconos, mensajes sin términos técnicos en la interfaz, sin Firecrawl, esquema exclusivamente DDL manual en phpMyAdmin y respaldo antes de cualquier publicación.
