# Ruta H&D V2 — Estado comprobado de menú e integración (21-11-2026)

## Fuente de verdad para menú principal
- El menú actualmente publicado contiene: Inicio, Pedidos, Clientes, Productos, Inventario, Informes, Administración.
- **No se ha cotejado esta lista con la imagen original del menú oficial**, compartida por el usuario en otro chat del proyecto. Esa imagen debe recuperarse visualmente antes de afirmar que los siete nombres corresponden a la especificación oficial o de sustituir el menú principal.
- No deducir ni fabricar nombres de menús oficiales.
- Se conservó íntegro el menú principal durante el arreglo de subítems.

## Incidencia submenú Productos
- El código anterior abría simultáneamente Productos y Administración al hacer clic en una clasificación desde Administración.
- Se corrigió en el frontend [rama de catálogo](https://github.com/msanzana/rutahyd2front/tree/feature/catalogos-api-adapter-v2-20261120), usando el origen de navegación para mantener abierta solo una rama.
- Se agrupan los diez subítems: Rubro→Departamento→Familia→Línea; Marca→Modelo; Unidades, gramos, envasado y tributación.
- Desplegado exclusivamente en /htdocs y /htdocs/violeta-demo mediante el workflow [44878870068](https://github.com/msanzana/rutahyd2/actions/runs/44878870068).
- Se verificó directamente en navegador real el 21-11-2026 con versión app.js?v=20261121-5: los tres encabezados aparecen, hay diez links Product y diez shortcuts Admin; el click Admin oculta Product y el click en Rubros desde Admin mantiene abierta solo Admin.
- La vista previa antigua catalogos-v2-preview no se actualizó por estructura de menú distinta.

## Conexión frontend ↔ backend ↔ MySQL
- GET https://rutahyd2.free.nf/api/v1/estado sigue devolviendo HTTP **503** JSON `{"servicio":"Ruta H&D2 API","estado":"no_habilitada"}`; probado en navegador real después del arreglo de menú.
- Por lo tanto **NO hay conexión funcional para CRUD autenticado en producción**; el frontend puede llamar al gateway, pero el gateway no está habilitado.
- La última prueba de PHP→PDO→MariaDB en el propio hosting sí conectó (45 tablas y 5 roles): [44875842428](https://github.com/msanzana/rutahyd2/actions/runs/44875842428).
- En esa prueba existían 0 usuarios. Verificar nuevamente antes de afirmar que sigue igual.
- La falta de administrador y el marcador privado de activación son bloqueos separados. No activar API sin usuario autorizado y pruebas de login, permisos y seguridad.
- Las secciones de clientes/pedidos generales todavía usan datos de demostración local; solamente los CRUD de catálogos y productos cuentan con adaptadores Laravel, sujetos a autenticación.
- No se aplicaron migraciones ni cambios MySQL durante el arreglo del menú.

## Próximos pasos necesarios
1. Abrir la captura exacta de menú oficial enviada en el otro chat y adaptar SOLO los padres de navegación según la fuente visual.
2. Crear administrador de manera segura sin enviar credenciales ni hashes por chat o GitHub; existe herramienta de aprovisionamiento en `tools/crear_admin_sql_windows.ps1` de la rama de backend.
3. Probar login JWT real y permisos sin exponer secretos, entonces habilitar marcador privado, con plan de cierre.
4. Probar el CRUD real con credenciales autorizadas y separar claramente los módulos que todavía usan localStorage.
