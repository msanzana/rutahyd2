# Ruta H&D2 V2 — Menú Productos y perfiles de acceso

**Estado 20 de noviembre de 2026:** UI validada y backend desplegado exclusivamente en staging **protegido**. Ninguna migración SQL 12/13/14/16 ha sido acreditada contra la base real de InfinityFree. El frontend principal se actualiza mediante flujo FTPS con verificación remota.

## Objetivo
Reubicar los diez catálogos comerciales como subítems de **Productos**:

1. Rubros
2. Departamentos
3. Familias
4. Líneas
5. Marcas
6. Modelos
7. Tipos de unidad
8. Gramajes
9. Tipos de envasado
10. Condiciones tributarias (Afecto / Exento)

Cada submódulo se controla con **dos permisos separados**:
- `productos.catalogos.<modulo>.ver`: visualización del menú y consulta GET autenticada.
- `productos.catalogos.<modulo>.gestionar`: crear, modificar y eliminar vía POST/PUT/DELETE autenticados; presupone visibilidad del mismo módulo.

Los permisos nuevos se instalan mediante `database/espanol/16_permisos_modulos_producto.sql`, archivo **aditivo** (20 registros, `INSERT IGNORE`) que **no** otorga acceso a roles existentes por defecto. Las 10 tablas de catálogos corresponden a SQL 13, las columnas de productos a SQL 14 y la tabla de tokens a SQL 12.

## Administración de perfiles
- `GET /api/v1/perfiles/catalogos`: devuelve los perfiles (roles) y los permisos de cada submódulo; solo administrador.
- `PUT /api/v1/perfiles/{id}/catalogos`: reemplaza únicamente los 20 permisos comerciales del rol indicado, sin modificar permisos de otros módulos. Requiere los diez módulos completos y `ver=true` si `gestionar=true`.
- `GET /api/v1/perfiles/usuarios`: lista usuarios, estado y perfil, sin hashes de contraseñas; solo administrador.
- `PUT /api/v1/perfiles/usuarios/{id}/rol`: asigna rol operativo a usuario; prohíbe editar el propio administrador o elevar a alguien a Administrador por esta pantalla.
- `POST /api/v1/sesion` y `GET /api/v1/perfil` devuelven el conjunto de permisos del usuario; el token de sesión se mantiene solo en memoria en el frontend.

**Modelo de autorización:** los usuarios heredan permisos de su perfil (rol) a través de `roles_permisos`. No se implementan excepciones por usuario ni una segunda matriz que contradiga al rol. El rol Administrador conserva su bypass de seguridad previamente existente.

## Seguridad
- El frontend oculta submódulos no concedidos y rechaza rutas no visibles; además deshabilita botones de modificación para roles de solo lectura.
- **Solo el backend Laravel autoriza acceso real.** `CatalogoComercialControlador` verifica `PermisosCatalogos::exigir(...)` en GET/POST/PUT/DELETE, consultando los datos de la sesión y los permisos del rol en MySQL. El frontend puede ser manipulado por el usuario; ocultarlo no es protección suficiente.
- El catálogo legado `categorias_productos` y `productos.categoria_id` se conservan sin cambios.
- El administrador, la revocación de permisos y las relaciones de clasificación se probaron con un MySQL efímero en GitHub Actions.
- **La demostración local** permite probar CRUD en el navegador aun sin conexión. No representa autenticación ni datos reales. No se debe confundir con los CRUD que necesitan API.
- Los permisos reales exigen autenticar la API y tener los SQL 12, 13, 14 y 16 instalados y verificados en `if0_43460400_rutahyd2_es`. Evitar cualquier nueva ejecución de DDL sin comprobación previa.
- `/htdocs/api-v2` continúa con `Require all denied`; nunca retirar la denegación, subir `.env` ni exponer secretos antes de configurar un punto de entrada PHP aislado.

## Pruebas aprobadas y evidencia
- [Prueba backend de matriz, revocaciones y usuarios](https://github.com/msanzana/rutahyd2/actions/runs/44684530669)
- [Playwright frontend, roles, menú y perfiles](https://github.com/msanzana/rutahyd2front/actions/runs/44685352752)
- [Staging de backend actualizado bajo protección](https://github.com/msanzana/rutahyd2/actions/runs/44684958406)
- [Auditoría FTPS anterior de archivos](https://github.com/msanzana/rutahyd2/actions/runs/44683332628)

## Lo todavía pendiente en producción
1. Exportar respaldo SQL íntegro y verificable mediante phpMyAdmin de la base española; conservar la original en inglés.
2. Ejecutar el preflight de solo lectura `database/espanol/15_preflight_catalogos_remoto_solo_lectura.sql`.
3. Aplicar **solo las migraciones ausentes** en orden: 12 (tokens), 13 (catálogos), 14 (producto), 16 (20 permisos), previa comprobación de dependencias, recuentos e integridad y respaldo; no repetir ALTER TABLE ya aplicado.
4. Crear administrador de manera segura y configurar secretamente Laravel, CORS/HTTPS y ruta pública que sirva exclusivamente `public/` sin exponer código privado.
5. Repetir login real, prueba 401/403 y CRUD por perfil en la base real de ensayo, antes de abrir producción.
