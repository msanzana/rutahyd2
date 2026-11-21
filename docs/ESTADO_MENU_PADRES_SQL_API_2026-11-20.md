# Ruta H&D2 | Corte técnico menú histórico, SQL y conexión API
Fecha: 20 de noviembre de 2026. Este archivo distingue despliegues FTPS de habilitaciones MySQL reales.

## Menú principal preservado (fuente verificable)
La fuente específica que sí fue posible inspeccionar es `msanzana/rutahyd2front/README.md` en `main`, que enumera **Inicio, Pedidos, Clientes, Productos, Inventario, Informes y Administración**.
La actualización conserva esos **siete padres exactos, en ese orden**. Productos aloja el Listado de productos y los diez catálogos; Administración aloja **Perfiles y permisos** como subítem, no como nuevo padre.
Las imágenes históricas mencionadas en el proyecto no están identificadas de forma inequívoca en los archivos consultables en este contexto; no se debe extrapolar módulos adicionales sin volver a ver dichas capturas.

## Permisos
Cada catálogo tiene `ver` y `gestionar` independientes (20 códigos). Los permisos de backend se validan en PHP con el rol de usuario y MySQL. Menú oculto no equivale a acceso denegado: las rutas GET/POST/PUT/DELETE validan también el perfil.
La matriz de perfiles solo admite modificación por administrador, permite asignar usuarios a roles operativos sin promoción administrativa y comprueba cambios inmediatamente.

## Evidencia de integración real en CI (NO producción)
- [Suite UI con padres históricos](https://github.com/msanzana/rutahyd2front/actions/runs/44688949662): `success`.
- [Prueba navegador → Laravel HTTP → MySQL 8 EFÍMERO](https://github.com/msanzana/rutahyd2front/actions/runs/44688949770): `success`, login administrador, CRUD real de rubro temporal, permisos guardados para Vendedor, segundo login y 403 de escritura, visibilidad correcta.
- [Pruebas de migraciones y roles](https://github.com/msanzana/rutahyd2/actions/runs/44687588326): `success`, sin datos de producción.
No se debe interpretar MySQL temporal como una consulta en la base `if0_43460400_rutahyd2_es`.

## Infraestructura real de InfinityFree
- [Auditoría de rutas FTP](https://github.com/msanzana/rutahyd2/actions/runs/44687985499): no existía `/htdocs/.htaccess` raíz ni `/htdocs/api` y el área `/htdocs/api-v2` estaba bloqueada con `Require all denied`.
- [Gateway instalado](https://github.com/msanzana/rutahyd2/actions/runs/44688523287): creados `/htdocs/api/.htaccess` y `/htdocs/api/index.php`, verificados byte a byte, sin tocar el backend privado ni el root de frontend.
- **Compuerta de activación:** el gateway requiere DOS condiciones: `/htdocs/api-v2/.env` y `/htdocs/api-v2/storage/app/private/rutahd2_api_ready`. No se ha creado ninguna. Por diseño, devuelve 503 en lugar de iniciar Laravel hasta que se autorice la activación.
- El cliente web apunta al mismo origen HTTPS `/api/v1` y elimina tokens de memoria al cerrar sesión; solo presenta catálogos remotos tras autenticar. En modo demo no sincroniza los registros locales.
- **Prueba HTTP desde esta conversación:** el sitio rechazó la consulta externa; no se acreditó desde navegador una respuesta 503 real. Verificar en navegador de usuario o modo Work.

## Scripts de base de datos
- [Staging SQL 12–17](https://github.com/msanzana/rutahyd2/actions/runs/44687699297): seis archivos SQL presentes en `/htdocs/api-v2/database/espanol`, verificados byte a byte, **sin ejecutarse**.
- `12_tokens_acceso_opcional.sql`: tokens de sesión.
- `13_catalogos_comerciales_aditivos.sql`: 10 tablas Rubro→Departamento→Familia→Línea, Marca→Modelo, tipos de unidad, gramajes, envasado, condiciones tributarias.
- `14_productos_clasificacion_campos.sql`: 10 claves foráneas en productos, preserva `categoria_id`.
- `15_preflight_catalogos_remoto_solo_lectura.sql`: diagnóstico de solo lectura antes de tocar estructura.
- `16_permisos_modulos_producto.sql`: 20 permisos de ver/gestionar por catálogo.
- `17_verificar_clasificacion_y_perfiles_lectura.sql`: diagnóstico posterior de tablas, columnas, FK, permisos y legado.

**Orden seguro para terminar:** seleccionar exactamente `if0_43460400_rutahyd2_es` en phpMyAdmin; obtener y comprobar respaldo; ejecutar SQL 15; inventariar qué SQL faltan y aplicar **solo los ausentes** en orden 12→13→14→16; verificar con SQL 17; configurar privada y correctamente Laravel con el host MySQL de InfinityFree; crear administrador de forma protegida; verificar sesión y 401/403; solo entonces crear el marcador de activación mediante procedimiento controlado y comprobar operación HTTP de la app. Si falla, retirar el marcador para volver al modo 503. No subir credenciales al repositorio ni compartirlas en el chat.

## Restricciones materiales
El conector SQL visible en esta sesión es `db_sii_dte`, un sistema distinto. No tenemos un acceso SQL real de escritura a la base Ruta H&D2. FTPS permite subir archivos, pero **no** ejecutar SQL en MySQL; InfinityFree bloquea conexiones directas remotas y requiere phpMyAdmin o scripts PHP dentro del alojamiento. No se deben ejecutar migraciones destructivas ni usar la base DTE.
