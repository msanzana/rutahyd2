# Ruta H&D V2 — Resultado real de migraciones en InfinityFree
Fecha: 2026-11-21 (Chile). Entorno: **producción**, base exclusiva `if0_43460400_rutahyd2_es`, MariaDB `11.4.13-MariaDB`.

## Acceso y respaldo
Acceso autenticado a InfinityFree → phpMyAdmin por Firecrawl, sin divulgar credenciales. Las consultas y DDL fueron ejecutadas **en MySQL real**.

Se produjo un **respaldo previo** exportado desde phpMyAdmin por el método SQL/Custom, todas las tablas con estructura y datos, como texto; se comprimió y se entregó al propietario mediante archivo privado descargable en ChatGPT. **No se sube el contenido de la BBDD a GitHub.**

- SQL sin comprimir: **44.075 bytes**.
- SHA-256 SQL: `220bb1189e4db51abab0919fff28a3375ece37973311212d5f550ad4ff5850f5`.
- Verificaciones: **34 sentencias CREATE TABLE**, **3 INSERT INTO**, cierre `COMMIT;`.
- El respaldo no ha sido reimportado en un entorno aislado (la integridad de formato se verificó, no la restauración completa).
- Guardar copia fuera del hosting y protegerla; para restaurar usar una base vacía o un procedimiento de rollback revisado, no importar encima del esquema actualizado.

## Preflight, antes de modificar
- Total: **34 tablas**.
- Tabla JWT `tokens_acceso`: **0**.
- Catálogos nuevos: **0/10**.
- Columnas de clasificación: **0/10**.
- Datos: `bodegas` **2**, `roles` **5**, `permisos` **12**; las otras **31 tablas vacías**, incluidas `usuarios` y `productos`.
- Rol `administrador` ya existía; no había usuarios.

## Migraciones EJECUTADAS en producción
Se aplicaron mediante la interfaz SQL de phpMyAdmin en la base española, en este orden:

1. **SQL 12** `12_tokens_acceso_opcional.sql`: tabla `tokens_acceso` creada; verificación posterior: 35 tablas, tabla con columnas `usuario_id`, `hash_token`, `expira_en`.
2. **SQL 13** `13_catalogos_comerciales_aditivos.sql`: creados los diez catálogos y semillas tributarias `AFECTO`/`EXENTO`; verificación: **45 tablas** y **10** catálogos.
3. **SQL 14** `14_productos_clasificacion_campos.sql`: agregadas diez columnas NULL de clasificación y **diez claves foráneas**; verificación: 10/10 columnas permiten NULL, 10/10 FK, `productos` mantiene cero registros. **NO REPETIR SQL 14**, es un ALTER no idempotente.
4. **SQL 16** `16_permisos_modulos_producto.sql`: insertados **20 permisos** nuevos, verificados en interfaz SQL.

Se ejecutó la verificación exacta **SQL 17** `17_verificar_clasificacion_y_perfiles_lectura.sql`, solo lectura. Resultados confirmados:
- Base correcta: `BASE_V2_CORRECTA`.
- Tablas **45**, tokens **1**, catálogos **10**, columnas **10**, FK **10**, permisos de catálogo **20**, columna heredada `productos.categoria_id` conservada **1**.
- Total permisos existentes **32**, `condiciones_tributarias` **2**, `productos` **0**, `usuarios` **0**.
- Cinco roles existentes; los 20 permisos nuevos no se asignaron masivamente a roles. La aplicación contempla bypass autenticado para el rol administrador en `PermisosCatalogos`.

## Pendiente: usuario y activación del backend
**NO se creó una cuenta administradora.** Una operación automatizada de inserción de credencial fue bloqueada por controles de seguridad del conector; se respetó el bloqueo y la verificación de solo lectura confirmó `usuarios = 0`. No guardar contraseñas ni hashes en GitHub o este informe.

Se debe:
1. Crear la cuenta solicitada de forma local/privada con **contraseña NUEVA**, hash **Argon2id** generado por PHP (`password_hash`) y SQL importado por el propietario desde phpMyAdmin, sin compartir secretos en conversaciones.
2. Instalar en `/htdocs/api-v2/.env` privado `DB_HOST=sql307.infinityfree.com`, nombre y usuario reales de MySQL, contraseña privada, `APP_KEY` único y `RUTAHD_JWT_SECRET` distinto, `HASH_DRIVER=argon2id`, `APP_DEBUG=false`, origen frontend.
3. Confirmar permisos de archivos, conectividad PHP↔MySQL y 401/403/JWT con usuarios de prueba autorizados antes de activar el marcador `storage/app/private/rutahd2_api_ready`.
4. Mantener el gateway `/api` bloqueado (HTTP 503) hasta completar los pasos previos. Retirar el marcador para rollback inmediato de la API.

**Conclusión:** migraciones BBDD **completadas y verificadas**. Creación de administrador, configuración privada, activación JWT y pruebas HTTP de autenticación **pendientes**. El respaldo previo fue generado y entregado como archivo privado, no publicado en el repositorio.


## Estado posterior de integración — 21 noviembre 2026

- [Sincronización gateway Laravel + frontend](https://github.com/msanzana/rutahyd2/actions/runs/44875111753): SUCCESS, cotejada por bytes remotos.
- [Verificación configuración privada](https://github.com/msanzana/rutahyd2/actions/runs/44875673709): SUCCESS; .env existe, APP_KEY y RUTAHD_JWT_SECRET independientes, host/base/usuario MySQL correctos.
- [Diagnóstico PHP→MariaDB real](https://github.com/msanzana/rutahyd2/actions/runs/44875842428): SUCCESS; conexión PDO desde PHP 8.4 del propio InfinityFree con 45 tablas y cinco roles; biblioteca Firebase JWT disponible y password_hash Argon2id soportado. Sonda efímera autenticada y eliminada.
- [Cliente remoto y solución anti-bot publicada](https://github.com/msanzana/rutahyd2/actions/runs/44876079811): SUCCESS; assets verificados en tres ubicaciones y siete entradas padres de menú preservadas.
- Firecrawl confirmó en navegador público botón «Verificar API Laravel» y respuesta esperada «API protegida (HTTP 503)».

### Protección anti-bot necesaria para el frontend

El hosting de InfinityFree devuelve HTML de desafío JavaScript para fetch del mismo dominio si se usa credentials:omit. Se verificó HTTP 503 JSON al usar credentials:same-origin. La versión aprobada del frontend usa únicamente cookies técnicas del mismo origen; los tokens JWT permanecen en memoria y solo se envían con Authorization: Bearer.

### Lo que falta

- La base productiva está migrada y la conexión PHP→MySQL es efectiva.
- La cuenta administradora aún NO existe: cero filas en usuarios.
- La API pública aún NO está activada, marcador privado ausente, HTTP 503.
- Antes de activar: crear administrador mediante procedimiento privado con contraseña NUEVA y hash Argon2id, probar login Bearer y errores 401/403, lecturas/CRUD autorizados, y entonces crear el marcador de activación. Para cerrar la API retirar ese marcador.
- El frontend mantiene los CRUD generales de Pedidos, Clientes, Productos e Informes en localStorage demostrativo. SOLO los catálogos remotos (CRUD) y las consultas de recursos (lecturas) interactúan con Laravel/MySQL. No mezclar ambos almacenes.

Nota operativa: el primer workflow de generación .env terminó en failure por comprobar un listado FTPS que no muestra archivos ocultos; el posterior audit directo verificó la existencia, contenidos estructurales correctos y conectividad real. No reinstalar ni regenerar claves.
