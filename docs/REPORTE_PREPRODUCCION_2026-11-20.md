# Ruta H&D V2 — Resultado de pruebas y compuertas de producción
Fecha: 20 de noviembre de 2026.

## Estado de la implantación
**No afirmar producción funcional.** Los archivos del cliente JWT y backend JWT fueron transferidos a InfinityFree, pero no se ha acreditado la ejecución de las migraciones sobre la base real `if0_43460400_rutahyd2_es`, ni la instalación de `.env`, creación de cuenta administrativa ni activación del gateway mediante el marcador `rutahd2_api_ready`.

## Evidencia reproducible
- [Prueba backend PHP JWT/MySQL temporal](https://github.com/msanzana/rutahyd2/actions/runs/44750304684) — `success`.
- [Prueba frontend real Chromium → Laravel → MySQL temporal](https://github.com/msanzana/rutahyd2front/actions/runs/44749026047) — `success`.
- [Publicación del cliente JWT en 3 destinos FTPS](https://github.com/msanzana/rutahyd2/actions/runs/44749640893) — `success`.
- [Subida original del backend JWT](https://github.com/msanzana/rutahyd2/actions/runs/44749501564) — `success` al concluir, sin abrir la API.
- [Reparación focalizada de JWT/autoload en staging](https://github.com/msanzana/rutahyd2/actions/runs/44752640588) — `success`.
- [Auditoría final FTPS de JWT y gateway](https://github.com/msanzana/rutahyd2/actions/runs/44752235305) — `success` en el segundo intento. Primer intento falló por ausencia temporal de `config/rutahd.php` durante el despliegue.
- [Prueba HTTP pública sin credenciales](https://github.com/msanzana/rutahyd2/actions/runs/44752930483) — ejecución completada, pero las cuatro solicitudes HTTP dieron **error de transporte curl 52** (respuesta vacía), por lo que **no acredita funcionalidad HTTP**, ni 503/401/403 reales.

## Datos y credenciales
- Base histórica española: `if0_43460400_rutahyd2_es`, documentada con 34 tablas. No se han comprobado sus filas ni cambios recientes de forma directa.
- SQL 12 (tokens), SQL 13 (10 catálogos), SQL 14 (10 columnas y FKs), SQL 16 (20 permisos), archivos 15/17 (diagnósticos) están almacenados en el staging protegido. Haberlos subido **NO es haberlos ejecutado**.
- JWT utiliza firma HS256 con secreto `RUTAHD_JWT_SECRET` independiente de contraseñas, duración de una hora y tabla de revocación. No se ha creado cuenta administradora productiva.
- La contraseña de administrador se compartió en conversación: **rotar a contraseña nueva antes de producción**. Usar únicamente hash irreversible Argon2id/bcrypt, nunca colocarla en GitHub ni en argumentos de GitHub Actions.
- La conexión BlazeSQL disponible corresponde a `db_sii_dte`, proyecto diferente y de solo lectura; no usarla para Ruta H&D.
- Hosting gratuito InfinityFree no expone MySQL remoto: se necesita sesión phpMyAdmin autorizada o PHP seguro ejecutado en el hosting.

## Requisitos bloqueantes antes de activar API y acceso a producción
1. Acceso a cuenta InfinityFree/phpMyAdmin en una sesión controlada y respaldo exportado y comprobado; proteger el respaldo fuera de la web pública.
2. Ejecutar exclusivamente SQL 15 de solo lectura, obtener inventario actualizado, aplicar solo migraciones ausentes 12,13,14,16, luego ejecutar SQL 17 y comparar la integridad de tablas/cuentas/productos heredados.
3. Crear o actualizar el administrador por canal seguro y contraseña nueva; almacenar únicamente hash Argon2id/bcrypt. Confirmar que `Hash::check` funciona en PHP 8.4 real.
4. Configurar en ruta privada `.env` el DB_HOST real, DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_KEY y JWT_SECRET **diferentes**, APP_DEBUG=false, entorno production, HTTPS y origen permitido.
5. Probar salud `/api/estado` vía navegador real, denegación 401 de GET protegido sin JWT, 401 con firma alterada, 403 sin permiso, cierre de sesión/expiración, lectura y CRUD sobre datos de prueba con cuenta de rol no admin.
6. Probar que rutas de `api-v2/` y archivos privados no se sirven directamente; mantener `.htaccess Require all denied`.
7. Crear marcador `storage/app/private/rutahd2_api_ready` **solo después** de completar todos los anteriores. Para rollback inmediato, retirar el marcador y confirmar HTTP 503.

**No automatizar acciones de escritura hasta que los pasos 1–4 hayan sido corroborados.** Las pruebas CI son válidas para código pero no sustituyen pruebas con datos de producción.

## Menú original
Mantener exactamente Inicio, Pedidos, Clientes, Productos, Inventario, Informes y Administración. Los 10 catálogos son hijos de Productos y Perfiles y permisos es hijo de Administración. No publicar una rama que cambie esta jerarquía sin compararla con la imagen oficial del proyecto.


## Auditoría posterior y situación comprobada a las 16:22 UTC

- [Prueba navegador real en hosting #44772584141](https://github.com/msanzana/rutahyd2/actions/runs/44772584141): **SUCCESS**. Chromium navegó el frontend HTTPS con **HTTP 200**, encontró exactamente los **siete módulos padres**, y comprobó que `/api/v1/estado` respondió **HTTP 503 en JSON**, conforme al bloqueo previsto de la API. Esto aclara que los errores `curl 52` anteriores procedían del transporte/antibot del entorno de prueba, no probaban que el frontend estuviera caído.
- [Auditoría FTPS de estado #44771858205](https://github.com/msanzana/rutahyd2/actions/runs/44771858205): `.env` **no presente**, marcador `rutahd2_api_ready` **no presente**, directorio Laravel **protegido** por `Require all denied`.
- [Auditoría FTP de nombres #44772104056](https://github.com/msanzana/rutahyd2/actions/runs/44772104056): marcó falsamente como ausentes los seis scripts por el método de análisis de listado, **no es evidencia válida para concluir ausencia**.
- [Verificación FTPS directa #44772410412](https://github.com/msanzana/rutahyd2/actions/runs/44772410412): **SUCCESS**; descargó los **seis SQL 12 a 17 uno por uno**, comparó bytes exactos contra GitHub y confirmó `.htaccess` intacto. **Los scripts están alojados pero no ejecutados en MySQL real**.
- Sin conexión autenticada a `if0_43460400_rutahyd2_es`, no es seguro ni técnicamente posible confirmar backup, tablas, datos, alta de administrador o aplicar `ALTER TABLE` por los conectores disponibles en esta conversación.
- Por tanto la API mantiene deliberadamente HTTP 503. **No** subir `.env`, crear el marcador ni publicar acceso a cuentas hasta respaldar/verificar MySQL y configurar secretos privados por sesión autorizada.
