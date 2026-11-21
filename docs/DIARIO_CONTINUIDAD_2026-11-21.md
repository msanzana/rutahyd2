# Diario de continuidad — Ruta H&D V2
Fecha de revisión: 2026-11-21

## Alcance y fuentes comprobadas
- GitHub backend: `msanzana/rutahyd2`, rama `main` y rama `feature/catalogos-api-productos-v2-20261120`.
- GitHub frontend: `msanzana/rutahyd2front`, rama `main` y rama `feature/catalogos-api-adapter-v2-20261120`.
- Documento base: `docs/ESTADO_VERIFICADO_MENU_Y_API_2026-11-21.md`.
- Historial conversacional del proyecto: diseño violeta, logotipo H&D V1, jerarquía comercial y menú oficial mediante imagen compartida.
- No se encontró el diario original ni la fuente de conocimiento en Google Drive o ChatGPT Space conectados en esta revisión. No asumir por ello su inexistencia fuera de estos conectores.

## Hechos observados en el código
1. La rama `main` del frontend contiene `assets/js/app.js` y `assets/js/catalogos.js` de demostración que guardan en `localStorage`. Sus CRUD locales no son equivalentes a CRUD en MySQL.
2. La rama de frontend `feature/catalogos-api-adapter-v2-20261120` contiene `assets/js/catalogos-api.js` y `assets/js/productos-remotos.js`. El adaptador exige HTTPS del mismo origen, JWT Bearer y permisos. Existe formulario de productos y llamadas a API.
3. La rama backend `feature/catalogos-api-productos-v2-20261120` registra rutas `/v1/productos`, `/v1/catalogos/{catalogo}`, `/v1/productos/{id}/clasificacion`, protegidas por middleware y permisos.
4. La jerarquía solicitada es Rubro → Departamento → Familia → Línea y Marca → Modelo; atributos adicionales: tipo de unidad, gramos, tipo de envasado y afecto/exento.
5. El documento de estado previo certifica una corrección de apertura de submenús y despliegue al sitio y a `/violeta-demo`. No equivale a haber verificado nuevamente el sitio hoy.
6. El documento previo registra que `/api/v1/estado` devolvía HTTP 503 `no_habilitada` después del arreglo. No hay prueba nueva de JWT real en esta revisión.
7. El documento previo reporta una prueba PHP–PDO–MariaDB satisfactoria (45 tablas y 5 roles), y 0 usuarios entonces. Son resultados históricos y requieren comprobación antes de actuar.

## Bloqueadores y orden de trabajo seguro
- **P0 — Fuente de verdad del menú.** Cotejar visualmente la imagen original de los ítems oficiales. No sustituir padres por una lista inferida. Mientras tanto mantener estructura y evitar regresiones de submenús.
- **P0 — Respaldo.** Ejecutar primero una exportación completa comprobable de MariaDB, guardada fuera del alojamiento productivo y con verificación de integridad. No aplicar migraciones antes del respaldo.
- **P0 — Auditoría sin escritura.** Verificar el estado de `/api/v1/estado`, presencia de archivos esperados, autenticación bloqueada, tablas, esquema, versiones y conteos sin publicar secretos.
- **P1 — Diferencia ramas / despliegue.** Comparar exactamente los archivos de ramas `main`, ramas `feature` y los contenidos publicados; revisar `workflow` con SHA fijado antes de relanzarlo. Nunca asumir que desplegar una rama equivale a hacer merge a `main`.
- **P1 — Administrador y seguridad.** Aprovisionar usuario administrador mediante procedimiento privado de hosting, sin colocar contraseña, hash, JWT o `.env` en el repositorio, acciones, chat o frontend. Probar login y permisos antes de habilitar API.
- **P1 — CRUD integrados.** Probar con sesión autorizada las diez clasificaciones y Productos (crear, listar, editar, desactivar), comprobar las dependencias, claves foráneas y restricciones de baja; distinguir datos locales de datos reales.
- **P2 — Módulos adicionales.** Adaptar Clientes, Pedidos, Inventario e Informes a API después de revisar contratos y permisos. No afirmar que funcionan sobre MySQL mientras persistan en `localStorage`.
- **P2 — Pruebas de regresión.** Comprobar vistas de escritorio y móvil, menú de Productos/Administración, estado 401/403/409/422, escape de texto, paginación, refresh y logout.

## Regla de despliegue
No ejecutar cambios de esquema, habilitar el gateway o cargar credenciales hasta disponer de respaldo validado y comprobación segura de administrador, permisos y autenticación. Este diario es una auditoría de repositorios y no certifica acceso a la base productiva ni ejecución de migraciones.

## Próxima evidencia a registrar
- Resultado y fecha de comprobación de `/api/v1/estado`.
- Identificación de la captura de menú original y lista exacta de padres.
- Identificador de respaldo, tamaño, método de verificación y ubicación protegida (sin divulgar secretos).
- SHA desplegado de frontend y backend y resultado de pruebas autorizadas.

## Continuación verificada — 21-11-2026
- Auditoría privada .env exitosa: https://github.com/msanzana/rutahyd2/actions/runs/44880838282
- Sonda PHP-PDO-MariaDB real exitosa y eliminada: https://github.com/msanzana/rutahyd2/actions/runs/44880836046. HTTP 200, 45 tablas, 5 roles, 0 usuarios, JWT y Argon2id disponibles. No escribió datos.
- PR backend #5 fusionada a rama feature/catalogos-api-productos-v2-20261120; PR frontend #2 fusionada a feature/catalogos-api-adapter-v2-20261120.
- CI de backend usuarios y frontend aprobada: https://github.com/msanzana/rutahyd2/actions/runs/44880651659 y https://github.com/msanzana/rutahyd2front/actions/runs/44880402524
- Staging FTPS completado y verificado por bytes: https://github.com/msanzana/rutahyd2/actions/runs/44881482698. Vista previa /usuarios-v2-preview-20261121/, controladores Usuarios/Recuperacion, rutas, config mail y SQL18 alojados en backend privado. Las páginas públicas y .htaccess Laravel quedaron intactos.
- Comprobación externa de preview en navegador: siete padres, diez clasificaciones de Productos, diez clasificaciones agrupadas en Administración. Usuarios y Perfiles ocultos sin sesión administrativa.
- API de producción todavía devuelve HTTP 503 y estado no_habilitada.

### Bloqueos y próximos pasos
1. Respaldo íntegro con verificación fuera del hosting; todavía no realizado.
2. Contrastar nombres y orden del menú principal con imagen oficial original, todavía pendiente.
3. SQL18 instalado como archivo en hosting, NO ejecutado en MySQL. Verificar antes de aplicar.
4. Crear administrador inicial con contraseña nueva por procedimiento administrativo privado. La última sonda comprobó cero usuarios.
5. Configurar SMTP en entorno protegido y verificar entrega y recuperación real.
6. Verificar JWT real, permisos, CRUD y reversión antes de crear marcador rutahd2_api_ready. No se habilitó la API.

## Revisión gráfica y de autenticación — 21-11-2026
- Frontend corregido en feature/ui-menu-plano-login-20261121, PR #3 fusionado a rama de integración. Subítems alineados sin sangrías ni encabezados internos: Listado y 10 clasificaciones de Productos.
- Nuevo login modal desde cabecera e indicador de estado de API. Si el gateway está deshabilitado no se envían credenciales; la sesión JWT se comparte con catálogos y permisos del frontend.
- CSS responsivo adicional para sidebar con scroll interno, topbar, texto envolvente, formularios y avisos.
- CI frontend: 29 pruebas pasadas, 1 omitida: https://github.com/msanzana/rutahyd2front/actions/runs/44882540313
- Vista previa verificada por FTPS: https://rutahyd2.free.nf/ui-plana-login-v2-20261121/ ; https://github.com/msanzana/rutahyd2/actions/runs/44882632359
- Publicación a portada y violeta-demo con snapshot y cotejo por bytes exitosa: https://github.com/msanzana/rutahyd2/actions/runs/44883250734
- Primer despliegue público revirtió correctamente debido a un error de expansión de variable Bash en la verificación, corregido para ejecución exitosa.
- Browser real confirma login e indicador API no habilitada en portada y violeta-demo; Productos presenta listado + 10 catálogos planos en portada.
- Persisten bloqueos: base productiva sin administrador verificado, API HTTP 503 no_habilitada, SMTP/recuperación sin prueba real, SQL18 sin ejecutar y respaldo externo no certificado.
- Menú principal oficial no cotejado con la imagen histórica de otro chat, no accesible como archivo en esta sesión. Conservar siete padres actualmente publicados hasta recibir imagen original y aplicar nombres exactos.
- Clientes/Pedidos siguen con localStorage de demo; no afirmar fullstack hasta completar su API.

## Menú principal OFICIAL — verificado a partir de imagen del usuario, 21-11-2026
Fuente primaria: imagen entregada en esta conversación, con ocho padres y sin otros padres.
Orden EXACTO: 1. Administración; 2. Compras; 3. Ventas; 4. Proveedores; 5. Clientes; 6. Productos; 7. Bodega; 8. Cuentas corrientes.
Inicio queda accesible por logotipo, pero NO es un ítem principal. Pedidos, Inventario e Informes se retiraron como padres del sidebar. Productos y Administración conservan subítems planos, sin encabezados de tercer nivel.
Frontend rama feature/menu-oficial-ocho-login-inicial-20261121, PR #4 fusionado en la rama de integración. CI: 34 pruebas aprobadas y 1 omitida: https://github.com/msanzana/rutahyd2front/actions/runs/44884897384
Login inicial a pantalla completa con correo y contraseña. Solicita el estado de /api/v1/estado antes de habilitar envío de credenciales. La demo se abre mediante acción explícita y nunca es equivalente a una sesión real.
Consulta frontend hacia Laravel programada para Compras (ordenes_compra), Ventas (ventas), Proveedores, Clientes y Bodega (bodegas), mediante API Bearer JWT; los recursos se renderizan con datos exclusivamente recibidos desde API. Cuentas corrientes NO posee aún endpoint y muestra aviso explícito.
Problema de logo FTPS resuelto incrustando el WebP oficial en HTML para el despliegue (data URI); navegador confirmó naturalWidth=350 en login, barra lateral y portada.
Vista previa: https://rutahyd2.free.nf/menu-oficial-login-v2-20261121/ ; FTP validado https://github.com/msanzana/rutahyd2/actions/runs/44885248237
Publicación productiva estática completada con respaldo y verificación por bytes: https://github.com/msanzana/rutahyd2/actions/runs/44885390909
Portada y violeta-demo verificadas mediante navegador externo: al abrir se ve login inicial; al elegir demo se muestran los ocho padres exactos, sin elementos antiguos; logos cargan correctamente.
**Bloqueo real de backend:** /api/v1/estado continúa respondiendo HTTP 503, estado no_habilitada. No se activó JWT productivo ni se ejecutó SQL. Última auditoría de MariaDB encontró 45 tablas, 5 roles y 0 usuarios; faltan respaldo externo íntegro, SQL18 según corresponda, nuevo administrador aprovisionado en privado, SMTP y ensayos autenticados. No utilizar la contraseña compartida en chat.

## Sitio definitivo únicamente — cierre visual del 21-11-2026
- Por instrucción expresa del usuario, todas las próximas publicaciones de interfaz se realizarán sobre la portada productiva `https://rutahyd2.free.nf/`; no crear nuevas versiones de demostración.
- Nuevo módulo `assets/js/app-produccion.js` de navegación del sistema exclusivamente por JWT y API Laravel. Se dejaron de cargar `app.js` y `catalogos.js` basados en registros locales. `accesos.js` ahora niega permisos si no hay sesión.
- Se unificó el login de la cabecera e inicial en `login-produccion.js`; el formulario paralelo en Administración dejó de usarse. Se quitó el botón de explorar demostración, se deshabilita el envío cuando `/api/v1/estado` no está disponible y se recupera la cuenta solo desde flujo específico.
- Rediseño de acceso y sistema mediante `produccion.css`, con panel de 960px y dos columnas reales en escritorio y distribución vertical en móvil. El menú principal oficial de ocho padres se conserva sin subniveles.
- PRs frontend #5 y #6 integrados a rama feature; PR #1 integró el código frontend productivo a `main`.
- CI aprobada con pruebas de login obligatorio, permisos JWT, recursos API simulados y responsive: https://github.com/msanzana/rutahyd2front/actions/runs/44886964422
- Despliegue inicial del frontend solo producción: https://github.com/msanzana/rutahyd2/actions/runs/44886660040 ; última corrección de estilos: https://github.com/msanzana/rutahyd2/actions/runs/44887034949, ambos exitosos con verificación de bytes, respaldos de archivos del frontend y protección backend intacta.
- Verificación en navegador externo de la portada tras segundo despliegue: no existe botón ni enlace de demostración; login ancho 960px y dos columnas a 1440px, logo 350x350, sin solapes; `/violeta-demo` redirige a la portada principal.
- Persisten bloqueadores productivos NO solventados: gateway `/api/v1/estado` responde HTTP 503 `no_habilitada`, última sonda SQL constató cero usuarios, no hay respaldo de MariaDB fuera del hosting comprobado, migración de recuperación no ejecutada y SMTP no certificado. No se crearon cuentas, ni se alteró SQL, ni se insertaron contraseñas en el código.
- **Importante:** que el frontend tenga adaptadores Laravel y que pase pruebas con API simulada NO implica que ya funcione autenticación JWT contra la base productiva. El login productivo está correctamente bloqueado hasta una activación autorizada y verificada.

### Ajuste final de recuperación del sitio productivo
- Se corrigió el retorno desde el formulario de recuperación al login obligatorio sin dejar una pantalla vacía.
- CI final frontend aprobada: https://github.com/msanzana/rutahyd2front/actions/runs/44887294926
- Publicación final productiva con respaldo y comparación de archivos aprobada: https://github.com/msanzana/rutahyd2/actions/runs/44887370611
- Verificación real en navegador: login sin demo, abrir Recuperar cuenta, cancelar y regresar a Iniciar sesión; API sigue en HTTP 503 y NO se enviaron datos.
- No se modificaron usuarios ni esquema MariaDB. Activación real de JWT sigue bloqueada por falta de administrador inicial respaldado y pruebas de correo.

## Alta de administrador solicitada — 21-11-2026 (estado verificable)
- Se solicitó el primer administrador con nombre completo, RUT validado y correo indicado por el titular. Evitar publicar esos datos personales o contraseñas en archivos GitHub.
- Diagnóstico sobre **MariaDB Ruta H&D correcta** por sonda PHP de solo lectura, workflow https://github.com/msanzana/rutahyd2/actions/runs/44888264970: PHP/PDO conectan, 45 tablas, 5 roles, 0 usuarios, librería JWT disponible y Argon2id habilitado. Sonda eliminada correctamente.
- BlazeSQL disponible en este chat está conectado a una base DTE SII diferente y sus consultas son de solo lectura. No permite crear usuarios en Ruta H&D.
- Preparados en rama `feature/alta-admin-rut-segura-20261121` (PR #6 integrada a rama de backend) los archivos `database/espanol/19_rut_usuarios.sql`, `app/Support/RutChileno.php`, modelo Usuario con RUT y CLI `rutahd:crear-administrador` con `--rut` y `--nombre`.
- El CLI exige SMTP real y las tablas de recuperación/token. Genera contraseña inicial aleatoria inaccesible y **envía enlace de un solo uso para definir la contraseña**; no envía contraseñas en texto plano, rollback si falla el correo.
- Pruebas de sintaxis y validador RUT aprobadas: https://github.com/msanzana/rutahyd2/actions/runs/44888377005
- **NO se ha creado aún la cuenta ni enviado ningún correo**. No se ejecutó SQL19, ni se importó SQL18 ni se activó la API. La cuenta no puede anunciarse como existente hasta contar con acceso de escritura seguro a `if0_43460400_rutahyd2_es` y comprobar SMTP y respaldo.
- Orden necesario: respaldo externo validado -> migración si hace falta -> configurar SMTP -> alta privada del administrador -> confirmar recepción/enlace e inicio JWT -> activar marcador `rutahd2_api_ready` -> verificar CRUD real con permisos.

## Aclaración de herramienta operativa — 21-11-2026
- El usuario confirma que la navegación y automatización web actual de Ruta H&D se está realizando con **Firecrawl Hangar**: https://hangar.firecrawl.dev/. Se debe priorizar ese entorno y las herramientas Firecrawl disponibles para cualquier interacción web autorizada; no usar otros conectores de datos para escribir en la base Ruta H&D.
- Este diario previamente documentaba verificaciones de navegador y transferencias FTPS por GitHub Actions, pero no incluía literalmente la dirección de Hangar; esta entrada la registra expresamente.
- En esta revisión, el navegador remoto Firecrawl no pudo iniciar sesión y la lectura anónima de la URL devolvió HTTP 404. Esto NO prueba indisponibilidad del entorno autenticado del usuario; hace falta acceso de sesión autorizado para acciones dentro de Hangar.
- Regla de seguridad vigente: la navegación mediante Hangar no equivale a disponer de permisos SQL. Crear el administrador únicamente tras verificar acceso a la base correcta, respaldo, estructura, SMTP y mecanismo seguro de contraseña; mantener API bloqueada hasta completar pruebas reales.
