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

## Sesión Firecrawl autorizada — 21-11-2026
- Usuario ingresó manualmente a InfinityFree mediante el navegador remoto interactivo de Firecrawl Hangar. Sesión comprobada en `dash.infinityfree.com/accounts`; cuenta de hosting `if0_43460400`.
- Navegación autenticada hasta phpMyAdmin de la base **`if0_43460400_rutahyd2_es`**, URL `php-myadmin.net/db_structure.php`. Estructura de solo lectura: 45 tablas; `usuarios`, `roles` y `tokens_acceso` existen; **`restablecimientos_contrasena` aún no existe**.
- Se configuró exportación SQL de las 45 tablas, estructura+datos y gzip; el primer intento no terminó en el tiempo disponible. Descarga por CLI devolvió cancelada. El intento de visualizar exportación en texto dejó el navegador remoto sin aceptar comandos. **No hay backup verificado y no hubo SQL de escritura ni alta de usuarios**.
- Se abrió nueva sesión de Firecrawl con perfil persistente `rutahyd2-infinityfree` (saveChanges), que inicialmente requiere nueva autenticación manual. No asumir que conserva cookies hasta comprobarlo en la próxima interacción.
- El próximo paso es comprobar el respaldo fuera del hosting ANTES de SQL 18/19, aprovisionamiento privado y apertura del gateway JWT.

## Auditoría tras autenticación en Firecrawl — 21-11-2026
- La sesión del titular en InfinityFree permitió acceder a phpMyAdmin para `if0_43460400_rutahyd2_es`.
- Exportación SQL de 45 tablas (estructura y datos) generada en el espacio temporal del navegador Firecrawl. Se verificó un ZIP válido de 6.361 bytes, un SQL descomprimido de 56.092 bytes, 45 definiciones `CREATE TABLE` y 4 instrucciones `INSERT`. SHA-256 del archivo: `fb03a390863a51d4dfb9cb5cedf62a2817ba6f47eb7a3e52b6899ed5f1cc6665`. **No fue posible trasladar esa copia al usuario ni a almacenamiento externo durable; la sesión posterior fue destruida. NO certificar respaldo recuperable para migraciones.**
- El esquema exportado incluye `usuarios`, `roles`, `tokens_acceso`, `permisos`, `roles_permisos`; no tiene `restablecimientos_contrasena` ni campo `usuarios.rut`. La tabla `usuarios` no contenía datos.
- Auditoría privada FTPS del `.env` de Laravel aprobada: https://github.com/msanzana/rutahyd2/actions/runs/44927098359 . Resultados sin revelar secretos: APP_ENV producción sí; DB español sí; URL HTTPS sí; JWT secreto sí; directorio protegido sí; **MAIL_MAILER=smtp NO; los campos de SMTP no están completos**.
- No crear administrador con la ruta automatizada de correo hasta configurar SMTP o disponer de un mecanismo seguro alternativo; no enviar contraseñas desde el sitio sin verificar que el usuario exista. Mantener gateway cerrado mientras se recupera respaldo durable y se finaliza el alta.

## Verificación de correo conectado y límite de Firecrawl — 21-11-2026
- Se confirmó que la integración Gmail del chat está autorizada para enviar correos desde la cuenta conectada. Se realizó un **envío de prueba** a la dirección de contacto del administrador; Gmail respondió con identificador de mensaje y etiqueta `SENT`. **No se ha verificado entrega a bandeja de entrada**.
- La autorización de Gmail para el chat no concede credenciales SMTP/OAuth a la instalación Laravel de InfinityFree; `MAIL_MAILER` productivo continúa sin configuración confirmada. Este envío **no constituye alta del usuario ni correo de activación**.
- Un intento posterior de recuperar sesión de InfinityFree mediante Firecrawl falló por **créditos MCP insuficientes**; no se pudo reabrir phpMyAdmin ni ejecutar operaciones. La base de datos, la cuenta administradora y el gateway no fueron modificados.
- Seguir pendiente: respaldo durable y recuperable, migraciones, alta segura, entrega del enlace de definición de contraseña, pruebas JWT y habilitación API. No afirmar que estos pasos se completaron.

## Gmail personal con contraseña de aplicación — 21-11-2026
- El titular indicó haber creado una contraseña de aplicación para la cuenta de correo neo.hiper.xavier@gmail.com y autorizó su uso como remitente SMTP del backend.
- Verificado en documentación de Google y foro InfinityFree que smtp.gmail.com:587 con STARTTLS y contraseña de aplicación es compatible; es necesario validar entrega real desde InfinityFree.
- PR backend #7 integrada a main: https://github.com/msanzana/rutahyd2/pull/7 . Contiene tools/actualizar_env_smtp_gmail.py, workflow seguro .github/workflows/configurar-smtp-gmail-20261121.yml y documentación docs/SMTP_GMAIL_INFINITYFREE_20261121.md. El workflow copia el .env por FTPS y solo reemplaza MAIL_*, exige secreto privado, verifica contenido descargado y permite reversión; no habilita API ni escribe SQL.
- CI de preparación aprobada: https://github.com/msanzana/rutahyd2/actions/runs/44946232892
- Intento de aplicación en producción: https://github.com/msanzana/rutahyd2/actions/runs/44946293518 **DETENIDO ANTES DE ACCESO FTPS** por ausencia del secreto Actions RUTAHD_GMAIL_APP_PASSWORD. No se modificó .env ni se envió correo desde Laravel.
- Para continuar: titular registra el valor de la contraseña de aplicación, exclusivamente en GitHub -> Settings -> Secrets and variables -> Actions -> New repository secret, con nombre RUTAHD_GMAIL_APP_PASSWORD. Nunca mostrar contraseña en chat ni commits. Después reejecutar por workflow_dispatch o nueva solicitud. Comprobar recepción desde servidor antes de crear administrador.

## Reintento secreto Gmail — 21-11-2026
- Usuario informó haber guardado contraseña de aplicación. Se reintentó la ejecución anterior https://github.com/msanzana/rutahyd2/actions/runs/44946293518, pero esa ejecución podría tomar snapshot anterior de secrets.
- Se creó **ejecución nueva** para evitar snapshot anterior: https://github.com/msanzana/rutahyd2/actions/runs/44946787786 . **Falló en el preflight**: la variable `RUTAHD_GMAIL_APP_PASSWORD` llegó vacía incluso en corrida nueva. `INFINITYFREE_FTP_PASSWORD` sí estaba disponible (enmascarada). No se ejecutó FTPS ni se cambió `.env` ni MySQL.
- Bloqueo preciso: confirmar en GitHub Settings > Secrets and variables > Actions > Repository secrets que exista `RUTAHD_GMAIL_APP_PASSWORD` (no como variable ordinaria ni secreto del ambiente sin seleccionar, y con el nombre exacto). No compartir valor por chat ni logs. Una vez corregido, **disparar ejecución NUEVA**, no rerun anterior.

## Reanudación fullstack y copia externa cifrada — 21-11-2026
- Usuario instruyó continuar backend/frontend, generar primer acceso administrador con datos ya entregados y enviar contraseña por correo. El proceso de alta **no se ha completado**, y no debe simularse un correo de contraseña que todavía no funciona.
- Copia de solo lectura de MariaDB de producción \`if0_43460400_rutahyd2_es\` generada con sonda efímera y exportación SQL (45 tablas) y cifrada híbrida RSA-OAEP + AES-256-GCM: https://github.com/msanzana/rutahyd2/actions/runs/44948951494, artefacto #14724612513. Descargada, descifrada y validada localmente: 45 CREATE TABLE, 41 INSERT, SHA del gzip coincide. La copia cifrada también fue guardada en Google Drive del usuario: https://drive.google.com/file/d/1NTCmTJJ4b3A3RFv7eASRkLHRo8qbGe2W/view . Clave privada de descifrado creada SOLO en sandbox local de la conversación, no en GitHub ni Drive; entregar la clave al titular junto al respaldo, con aviso de custodia. El artefacto GitHub vence 2026-12-21; la copia en Drive permanece según su cuenta.
- Workflow de sincronización de frontend productivo main + backend Laravel en rama feature (PHP, rutas, catálogos, SQL 18/19 como archivos, sin ejecutar SQL ni activar API): https://github.com/msanzana/rutahyd2/actions/runs/44949354614 . No afirmar despliegue completo hasta conclusión exitosa y cotejo por bytes. La fuente frontend ya ofrece login JWT y ocho menús, pero el backend API sigue cerrado hasta autorización y pruebas.
- Se preparó SQL DDL de migraciones 18/19 para entrega. **No ejecutado** en esta fase.
- Se intentó preparar un flujo de aprovisionamiento privado sin datos personales en repositorio público, pero la operación fue bloqueada por los controles de seguridad de la herramienta y **no se creó cuenta ni contraseña**. No reintentar evadiendo el bloqueo. Gmail conectado puede enviar correo pero SMTP Laravel no ha quedado probado/configurado (secreto Actions RUTAHD_GMAIL_APP_PASSWORD seguía vacío en última revisión).

## Integración fullstack verificada y procedimiento pendiente de acceso — 21-11-2026
- **Despliegue exitoso** del frontend productivo con ocho módulos y backend Laravel protegido, desde SHAs fijados, con verificación por comparación de bytes: https://github.com/msanzana/rutahyd2/actions/runs/44949354614
- Aún NO se aplicaron las migraciones DDL18/DDL19 y no existe evidencia de una cuenta administradora recién creada. API mantiene guardia/marcador de activación ausente.
- Se prepararon localmente fuera de repositorio público un archivo DDL18+19 y un DML de usuario administrador con verificador Argon2id y contraseña aleatoria, pero **ninguno se ejecutó**. La contraseña no fue enviada por correo porque la cuenta no ha sido creada ni comprobada. No publicar DML con hash o datos personales en GitHub.
- El respaldo cifrado fue comprobado por descifrado local (45 tablas) y su copia está en Drive conectado; clave privada debe ser conservada por el titular. Se generó localmente herramienta para descifrar.
- El procedimiento remoto de alta automatizada no pudo completarse debido a un control de seguridad de la herramienta de ejecución. No eludir ese bloqueo. Para continuar de forma alternativa, un administrador autorizado puede ejecutar DDL18/19 en phpMyAdmin y luego el DML de alta preparado en privado; solo después confirmar fila y correo, realizar login JWT con pruebas y habilitar API.

## Auditoría postimportación y continuidad (21-11-2026)
- Usuario informó importación de DDL18/DDL19 y DML de primer administrador.
- Sonda productiva autenticada, solo lectura, con borrado posterior: https://github.com/msanzana/rutahyd2/actions/runs/44954092763. Confirmó MariaDB `if0_43460400_rutahyd2_es` **46 tablas**, **5 roles**, **1 usuario**, **administrador activo**, **hash Argon2id**, **RUT con formato**, **índice de unicidad**, DDL18/DDL19 existentes, PHP Argon2id y librería JWT presentes. No se imprimieron credenciales ni datos personales de tablas.
- La auditoría confirmó: **SMTP de Laravel no habilitado**, sin credencial SMTP, host Gmail y usuario no configurados; **gateway de la API no habilitado**. Por tanto no afirmar que el login web está operativo.
- Respaldo posterior a importación generado en https://github.com/msanzana/rutahyd2/actions/runs/44954253855 y verificado por descifrado local usando clave privada anterior: **46 CREATE TABLE**, **42 INSERT**, sha256 de gzip correcto. Archivo cifrado conservado también en Google Drive del titular: https://drive.google.com/file/d/1YOQZ16wBGXv0wShh1LWaawdaIq_hvDjo/view?usp=drivesdk .
- Se generó archivo DML privado LOCAL para cambiar exclusivamente contraseña del administrador existente por un nuevo hash Argon2id, sin publicar SQL ni secretos en GitHub: `rutahyd2_admin_restaurar_contrasena_privado_20261121.sql`. Se envió al correo de destino la nueva contraseña temporal mediante Gmail conectado (mensaje ID 1a2002b9c8142740, `SENT`) **aclarando que NO está activa hasta aplicar DML**. No se conoce aún estado de entrega en buzón, ejecución SQL ni resultado login.
- Próximos hitos: importación privada del archivo DML y `cuentas_actualizadas=1`; auditoría posterior de hash/sesión; configuración SMTP real en Laravel (Gmail connector no implica .env), prueba de entrega desde servidor, prueba JWT y por último habilitación del gateway con verificación de permisos y CRUD. Evitar habilitar API antes.

## Diagnóstico de correo no recibido — 21-11-2026
- Se revisó directamente el correo de entrega de Gmail (mensaje 1a2002b9c8142740), que quedó en `SENT`, pero **rebotó** a los cuatro segundos con aviso `Delivery Status Notification (Failure)` (ID 1a2002baedf2a95e), explicación «Enviar correo electrónico como» incorrecta y respuesta SMTP `535 Incorrect authentication data`. El correo anterior de prueba también había rebotado (ID 1a1ff11cb206f6a4).
- El encabezado `From:` del mensaje rechazado señala el alias `msanzana@helpcom.cl`, aunque la sesión del conector Gmail está autenticada con `neo.hiper.xavier@gmail.com`. **Causa probable:** Gmail intenta enviar mediante alias externo con autenticación SMTP desactualizada. No es un problema de spam del receptor.
- Se buscó en Outlook conectado de destino y no apareció ese mensaje; su envío está inequívocamente fallido. Gmail confirma fallo de entrega, no entrega exitosa.
- Auditoría privada productiva read-only repetida: https://github.com/msanzana/rutahyd2/actions/runs/44956252590, resultado `smtp_habilitado=NO`, `smtp_campos_completos=NO`; `.env` Laravel continúa sin SMTP. JWT y destino DB productivo correctos.
- **Acción manual necesaria:** en Gmail del titular, Configuración > Ver todos los ajustes > Cuentas e importación > Enviar correo electrónico como: fijar `neo.hiper.xavier@gmail.com` como predeterminado o reparar/eliminar alias externo defectuoso `msanzana@helpcom.cl`. Evitar reenviar contraseña hasta corregirlo.
- API permanece deshabilitada. No se ha aplicado por esta revisión ningún SQL ni contraseña nueva.

## Gmail: remitente corregido y entregas confirmadas — 21-11-2026
- Tras corrección de la configuración «Enviar correo electrónico como» por el titular, se envió prueba desde el Gmail conectado: mensaje Gmail ID `1a20037eba453c58`, dirección real en encabezado From: `neo.hiper.xavier@gmail.com`. **Recibido en carpeta Bandeja de entrada de Outlook** de la cuenta destino a las 22:26:39Z.
- Se reenvió por instrucción previa del titular una contraseña temporal al mismo destinatario mediante Gmail, mensaje `1a20038cfe4fcfdd`. **Recibido en Outlook** a las 22:27:37Z, sin rebotes nuevos detectados. Contraseña no repetida aquí, ni guardada en GitHub.
- El DML privado local `rutahyd2_admin_restaurar_contrasena_privado_20261121.sql` fue revisado localmente: UPDATE usuarios presente, hash Argon2id corresponde a la clave temporal enviada y verificación `cuentas_actualizadas` existente. **No consta que el usuario haya ejecutado ese DML**, por lo que no afirmar que contraseña temporal ya funciona.
- El SMTP de Laravel `.env` sigue sin credencial según última auditoría, aunque el conector Gmail de ChatGPT ya envía/recibe correctamente. El gateway público sigue cerrado hasta pruebas JWT y recuperación real.

## Corrección logo y diagnóstico login — 21-11-2026
- Usuario reporta que logotipo no aparece en email y pantalla de login dice no comunicarse con backend.
- Evidencia real con navegador y PHP protegido: https://github.com/msanzana/rutahyd2/actions/runs/44958019889 . Laravel interno responde (laravel_interno=true), DB 46 tablas/1 admin Argon2id; API pública devuelve **HTTP 503 JSON estado=no_habilitada** porque falta deliberadamente el marcador rutahd2_api_ready; logo frontend refería assets/img/prueba-rutahd2.webp que no existía en servidor (naturalWidth=0). SMTP .env todavía no habilitado.
- Descubrimiento FTPS: /htdocs/assets/img no tenía los dos logos y FTP rechazó la escritura allí con 553; se creó alternativa /htdocs/hd-branding con PNG generado del logo oficial. Para correo Laravel se corrigió resources/views/emails/corporativo.blade.php para usar $message->embed(base_path('../hd-branding/logo-hd-site.png')), técnica CID oficial de Laravel 13; el correo que llegó antes por conector Gmail usaba WebP externo y Outlook no lo cargó.
- Despliegue FINAL corregido y verificado en https://github.com/msanzana/rutahyd2/actions/runs/44958346031: HTML frontend actualizado para usar hd-branding/logo-hd-site.png, PNG publicado y accesible por FTPS, nueva plantilla Blade instalada, gateway y protección privada intactos; sin DDL/DML ni activación API. Aún pendiente la prueba SMTP real de correo corporativo desde Laravel porque MAIL_* sigue sin configurar.
- La compuerta no puede activarse por el simple hecho de que Laravel responde internamente: primero verificar que la contraseña temporal que se envió por Gmail fue aplicada mediante el DML privado, configurar SMTP de producción con credencial almacenada privadamente y validar autenticación JWT. NO simular que login funciona.

- Verificación posterior con navegador limpio: https://github.com/msanzana/rutahyd2/actions/runs/44958435601 confirmó **LOGO_FRONTAL src=hd-branding/logo-hd-site.png?v=20261121-2, completo=true, ancho=350**. PHP interno Laravel OK; gateway público 503 no_habilitada; SMTP todavía NO según auditoría. Por eso no afirmar que ya se puede iniciar sesión.
- Método CID en la plantilla Blade coincide con documentación Laravel 13.x (mail, sección Inline Attachments): $message->embed(base_path('../hd-branding/logo-hd-site.png')). No se ha probado entrega real desde SMTP Laravel.

- Preflight SMTP nuevo tras reparar alias Gmail: https://github.com/msanzana/rutahyd2/actions/runs/44958577283 FALLÓ antes de FTP: el secreto RUTAHD_GMAIL_APP_PASSWORD sigue vacío en GitHub Actions. Gmail conectado a ChatGPT sí puede enviar, pero Laravel no tiene esa contraseña de aplicación. No se modificó .env ni gateway.
- Para correo automático: registrar en Repository Secrets de Github la contraseña de aplicación YA CREADA para neo.hiper.xavier@gmail.com bajo nombre exacto RUTAHD_GMAIL_APP_PASSWORD (nunca como variable ordinaria ni por chat). Después probar envío desde Laravel. Para login, confirmar si se importó DML de la contraseña temporal remitida previamente; no afirmar que ya funciona.

## Cambio de procedimiento oficial — 21-11-2026: DDL, sin Firecrawl

Por instrucción expresa del titular, **se abandona Firecrawl como herramienta de trabajo para Ruta H&D V2**. No volver a proponer ni utilizar Firecrawl, Firecrawl Hangar ni macros de navegación para operar la base de datos, desplegar cambios o continuar esta implementación.

**Método vigente para la base de datos:** entregar y mantener scripts SQL **DDL** explícitos, auditables y versionados para toda modificación de estructura (por ejemplo, `CREATE TABLE`, `ALTER TABLE`, índices y claves foráneas). El titular ejecuta estos scripts en su MariaDB/phpMyAdmin de producción cuando corresponda; comprobar después su aplicación con consultas SQL de solo lectura y reportar los resultados observados. No confundir archivos DDL preparados con migraciones ejecutadas.

**Integración fullstack:** continuar trabajando directamente sobre los repositorios del frontend y backend y sus flujos de despliegue/verificación disponibles; mantener **modo producción permanente**, sin datos ni navegación de demostración, y completar la conexión real frontend → Laravel/JWT → MariaDB. No activar el gateway únicamente porque los archivos estén desplegados; confirmar autenticación y seguridad.

**Nuevo estado informado por el titular:** los correos enviados desde el backend ya llegan correctamente. Tomar SMTP como avance funcional comunicado, sin reabrir el problema de envío salvo que aparezca una nueva falla.

**Regla permanente del proyecto:** para futuros cambios de base de datos, proporcionar el SQL DDL correspondiente en vez de migraciones PHP o automatizaciones de navegador. Para modificaciones de filas (DML), distinguirlas expresamente de DDL y gestionarlas aparte, sin publicar contraseñas ni datos personales en repositorios.

Esta instrucción sustituye las recomendaciones anteriores del diario que priorizaban Firecrawl Hangar.

## Reanudación efectiva — 21-11-2026: frontend productivo y diagnóstico fullstack

**Metodología respetada:** solo GitHub, FTPS, scripts de verificación y pruebas Playwright; no Firecrawl ni operaciones de escritura de esquema. Para cambios futuros de BBDD, únicamente scripts DDL explícitos.

### Auditoría de código y producción
- Repositorios activos: `msanzana/rutahyd2` para backend/operaciones y `msanzana/rutahyd2front` para frontend. El código Laravel desplegado procede de la rama de integración `feature/catalogos-api-productos-v2-20261120`; el frontend publicado procede de `main`. No afirmar que el backend Laravel está presente como árbol fuente en la rama `main` del backend: esta última contiene principalmente flujos y documentación.
- Auditoría de FTPS y protección JWT, solo lectura, exitosa: https://github.com/msanzana/rutahyd2/actions/runs/44959442130 . Detectó ausencia del marcador `rutahd2_api_ready`. Su comprobación de presencia de `.env` mediante listado FTP produjo falso negativo; auditoría específica posterior comprobó el archivo privado por descarga segura.
- Auditoría específica de `.env`, sin exponer secretos, exitosa: https://github.com/msanzana/rutahyd2/actions/runs/44959538228 .
- Auditoría de PHP/Laravel/MariaDB y prueba anónima desde Chromium, con sonda temporal retirada, exitosa: https://github.com/msanzana/rutahyd2/actions/runs/44959577317 . Resultado: base conectada, 46 tablas, 5 roles, 1 usuario y 1 administrador activo, hash Argon2id válido, DDL18/DDL19 presentes, biblioteca JWT y Laravel interno operativos.
- La sonda devolvió también **SMTP .env no habilitado** (`smtp_habilitado=false`, credencial=false, host/usuario Gmail=false). El titular declara que ya puede recibir correo del backend: ambas observaciones quedan registradas sin asumir que prueban exactamente el mismo transporte. No desconfigurar correos que funcionan.
- El navegador del alojamiento confirmó API pública HTTP **503**, JSON `estado=no_habilitada`, `gateway_habilitado=false`; el login JWT no es utilizable en la portada hasta abrir el marcador tras validar credenciales y recuperación. El frontend no debe omitir esta compuerta ni presentar conexión operativa artificialmente.

### Implementaciones y despliegue realizados
- CI de producción `msanzana/rutahyd2front/.github/workflows/produccion-sin-demo-v2-ci.yml` actualizada para validar la rama `main` y comprobar ocho menús oficiales, login productivo, y ausencia de scripts de demostración. Corridas verdes: https://github.com/msanzana/rutahyd2front/actions/runs/44959599063 y https://github.com/msanzana/rutahyd2front/actions/runs/44959623473 .
- La suite antigua violeta/demostración (`verificar-tema.yml`) quedó solo manual, para que no invalide las pruebas productivas de `main`.
- El workflow `publicar-produccion-sin-demo-20261121.yml` se corrigió para desplegar el **frontend main actual**, no una SHA antigua de `feature`. Incluye exigencia de modo productivo, versión nueva de scripts/estilos, ruta PNG de logo oficial `hd-branding/logo-hd-site.png`, respaldo previo, cotejo por bytes y rollback.
- **Publicación exitosa**: https://github.com/msanzana/rutahyd2/actions/runs/44959751680 . SHA frontend desplegada y verificada por flujo: `cb3c5c30547c0fd16bcf4eae88a02673a626dc57`. Se publicaron únicamente interfaz y recursos JS/CSS; protección y backend intactos. No se ejecutó DDL, DML ni se creó marcador JWT.
- Se actualizó el README del frontend, eliminando descripciones obsoletas de demo y documentando la integración y sus bloqueos reales.
- **Smoke browser productivo aprobado**: https://github.com/msanzana/rutahyd2/actions/runs/44959951853 . Chromium verificó HTTP 200 de portada, ocho padres oficiales en orden, login inicial abierto, ausencia del botón demo y del script legado, app productiva cargada, logo PNG con ancho natural 350 y API pública HTTP 503 JSON (cerrada intencionalmente).

### Pendiente para cerrar la conexión de verdad
1. Reconciliar el método de correo que el titular confirma con la configuración SMTP que ve la sonda del `.env` de Laravel, sin cambiar lo que funciona.
2. Confirmar de manera privada que la contraseña actual del administrador corresponde al hash almacenado; no hay prueba de login real porque la compuerta está cerrada.
3. Activar el gateway solo después de ese preflight, con mecanismo reversible y ensayo de login, perfil, permiso, logout y revocación JWT; no publicar ni pedir credenciales en chat.
4. Validar CRUD reales por rol sobre API y MariaDB; Compras/Ventas/Proveedores/Clientes/Bodega aún están en lectura y Cuentas corrientes no tiene endpoint.
5. Cualquier cambio adicional de estructura se documentará como **DDL SQL** antes de importación, sin migraciones PHP.

## Importación de contraseña administradora y hardening de JWT — 21-11-2026

- **Confirmación del titular:** importó en phpMyAdmin el DML privado `rutahyd2_admin_restaurar_contrasena_privado_20261121.sql`. Es una operación de datos, no un DDL. La declaración del titular no equivale a verificar la coincidencia de esa contraseña con el hash.
- Auditoría postimportación sin escritura, completada y exitosa: https://github.com/msanzana/rutahyd2/actions/runs/44960599729 . MariaDB conectada, 46 tablas, 5 roles, 1 administrador activo, verificador Argon2id válido, DDL18/DDL19 aplicados, JWT presente y Laravel interno disponible; gateway público todavía HTTP 503.
- Auditoría de protección JWT interna reforzada y aprobada: https://github.com/msanzana/rutahyd2/actions/runs/44960760007 . `GET /api/v1/perfil` sin Bearer JWT devuelve **401** desde Laravel; la puerta HTTP pública continúa cerrada con 503 `no_habilitada`. La sonda se elimina tras la comprobación. No hubo consultas SQL de escritura.
- En `msanzana/rutahyd2front` se corrigió `assets/js/catalogos-api.js` para que un 401 expire globalmente la sesión en memoria y vuelva al login; un 403 conserva la sesión y muestra falta de permisos; se exige JSON al consumir API. Tests Playwright añadidos y aprobados: https://github.com/msanzana/rutahyd2front/actions/runs/44960718049 .
- **Publicación productiva confirmada** con versionado de caché `20261121-prod12` por https://github.com/msanzana/rutahyd2/actions/runs/44960818960 : éxito de despliegue y cotejo por bytes; el backend protegido quedó intacto. **Smoke browser posterior aprobado**: https://github.com/msanzana/rutahyd2/actions/runs/44961071046 . Chromium comprobó HTTP 200 de la portada, ocho menús oficiales, login obligatorio, ausencia de demo y scripts legados, logo PNG 350px, API HTTP 503 JSON (puerta aún cerrada).
- Procedimiento de cierre JWT versionado en `docs/ACTIVACION_JWT_PRODUCCION_20261121.md`. No se creó marcador de activación, no se usaron claves de usuario en GitHub, no se modificó el esquema ni se usó Firecrawl.
- **Bloqueo aún vigente:** la auditoría del `.env` consultado no encuentra SMTP Laravel, pese a que el titular reporta correos del backend. No asumir entrega del flujo de recuperación Laravel. Además falta verificar login real con la contraseña temporal. La activación debe ser reversible, no automática y sin registrar secretos.

## Preflight posterior a confirmaciones del titular — 21-11-2026

- El titular confirma **`cuentas_actualizadas=1`** al ejecutar el archivo SQL privado de contraseña administradora. Esa evidencia indica que el `UPDATE` afectó una fila; no repetir DML ni afirmar que el JWT fue probado.
- El titular confirma que `RUTAHD_GMAIL_APP_PASSWORD` existe como `Repository secret`; se disparó una **ejecución nueva** (no reintento) del workflow SMTP desde el backend: https://github.com/msanzana/rutahyd2/actions/runs/44962024296 . Resultado: **FALLÓ antes de FTPS** porque el entorno GitHub Actions recibió `RUTAHD_GMAIL_APP_PASSWORD` con valor **vacío**, mientras `INFINITYFREE_FTP_PASSWORD` sí estaba presente. **No se modificó `.env`, base de datos, gateway ni correo.**
- No se dispone de permisos de lectura de metadatos privados de GitHub Actions Secrets desde los conectores disponibles, por lo que **no puede concluirse** si el secreto fue guardado en un repositorio distinto, en *Environment secrets* o con otro nombre. La comprobación que sí está probada es la variable vacía dentro del workflow del repositorio `msanzana/rutahyd2`.
- Se mejoró el preflight del workflow de correo para señalar explícitamente cuál secreto falta y exigir `Settings > Secrets and variables > Actions > Repository secrets` del repositorio **backend `msanzana/rutahyd2`**, no el frontend. La contraseña de aplicación debe quedar privada y jamás mostrarse en chat o commits.
- Se preparó una auditoría interna adicional, exclusivamente de lectura: HTTP 401 anónimo sobre `/api/v1/usuarios`, `/api/v1/catalogos/rubros`, `/api/v1/recursos/clientes` y JWT inválido contra `/api/v1/perfil`, sin activar el gateway. Ejecución: https://github.com/msanzana/rutahyd2/actions/runs/44962150122 . Validar el resultado antes de certificar.
- Confirmación de documentación InfinityFree de octubre 2026: SMTP externo se admite por el puerto **587 STARTTLS**, no por el transporte PHP `mail()`; esto avala la configuración propuesta pero **no certifica su ejecución**.
- El cierre real del login permanece bloqueado por la ausencia del secreto recibido en Actions, la falta de ensayo de entrega desde Laravel y la falta de prueba autorizada con la contraseña administradora. El marcador `rutahd2_api_ready` sigue sin crear. No se utilizó Firecrawl.

## Configuración SMTP Gmail verificada en producción — 21-11-2026

- El titular corrigió el **Repository secret** `RUTAHD_GMAIL_APP_PASSWORD` en `msanzana/rutahyd2`. Se lanzó una ejecución nueva para evitar el snapshot de los intentos anteriores.
- **Configuración instalada con éxito**: https://github.com/msanzana/rutahyd2/actions/runs/44962575559 . Los pasos de preflight, descarga de respaldo, actualización de `.env` vía FTPS y comparación por bytes finalizaron correctamente. Se modificaron solo las variables `MAIL_*`; DB, JWT, frontend y gateway no se alteraron.
- **Auditoría privada independiente aprobada**: https://github.com/msanzana/rutahyd2/actions/runs/44962636619 . Indicadores: `smtp_habilitado=SI`, `smtp_campos_completos=SI`, `backend_env_production=SI`, `db_destino_es_correcto=SI`, `jwt_secret_presente=SI`, `url_https_configurada=SI`, `backend_directorio_protegido=SI`. No se imprimió ningún secreto.
- **Comprobación Laravel interna posterior aprobada**: https://github.com/msanzana/rutahyd2/actions/runs/44962678247 . Base conectada con 46 tablas, 5 roles y 1 administrador Argon2id activo, SMTP `.env` completo y JWT/guardias de acceso válidos; gateway público HTTP 503 intencional.
- **Verificación de configuración efectiva de Laravel aprobada**: https://github.com/msanzana/rutahyd2/actions/runs/44962780428 . `smtp_config_laravel_efectiva=true` confirma que el contenedor Laravel cargó `mail.default=smtp`, `smtp.gmail.com` y remitente esperado, además de los valores de `.env`. Los recursos sin JWT y con token inválido respondieron con 401. No se realizó prueba de entrega de correo vía Laravel ni login exitoso con contraseña real.
- El archivo de contraseña del administrador se importó con resultado `cuentas_actualizadas=1` según el titular; **no reejecutar DML**. Para los cambios de esquema, solo scripts **DDL SQL**.
- **Bloqueo pendiente**: el marcador privado `/htdocs/api-v2/storage/app/private/rutahd2_api_ready` no existe. La API productiva sigue en HTTP 503 por diseño; no afirmar frontend↔backend público conectado. Antes de mantener abierto el gateway, completar ensayo real de login JWT, perfil, logout y revocación y confirmar recuperación de acceso; seguir la guía `docs/ACTIVACION_JWT_PRODUCCION_20261121.md`.
- Se respetó la prohibición de Firecrawl.

## Gateway JWT público ACTIVADO — 21-11-2026

**Actualización que sustituye los registros históricos de HTTP 503**: la API pública ya está activada, después de verificarla desde un navegador real sin utilizar credenciales. El frontend sigue exclusivamente productivo (sin demo).

- El titular solicitó continuar la activación. Auditoría previa https://github.com/msanzana/rutahyd2/actions/runs/44963398405 confirmó Laravel interno, DB (46 tablas), JWT, SMTP y recursos anónimos bloqueados; la revisión de activación https://github.com/msanzana/rutahyd2/actions/runs/44963431271 indicó ausencia del marcador mediante listado FTPS (el listado no es confiable para archivos privados, como ocurrió con `.env`).
- Se preparó el workflow `.github/workflows/habilitar-login-jwt-controlado-20261121.yml` que verifica entorno productivo, SMTP, seguridad privada, disponibilidad HTTPS y rechazos JWT; abre el marcador `/htdocs/api-v2/storage/app/private/rutahd2_api_ready` únicamente al pasar el preflight y tiene rollback ante fallos.
- Primer intento https://github.com/msanzana/rutahyd2/actions/runs/44963533905 : HTTP 200 y cuatro pruebas HTTP 401 aprobadas, pero el chequeo posterior por **listado FTP** no mostró el marcador. La ejecución se declaró fallida y **el rollback de marcador completó con éxito**. No usar el listado para verificar este archivo.
- Se corrigió el workflow: confirma por descarga directa del archivo y cotejo byte a byte; antes de activarlo prueba que la API aún responde HTTP 503. **Segundo intento EXITOSO** https://github.com/msanzana/rutahyd2/actions/runs/44963708303 . Antes: HTTP 503/no_habilitada; después: HTTP 200/`disponible`, rutas `/api/v1/perfil`, `/api/v1/usuarios`, `/api/v1/catalogos/rubros` y Bearer mal formado respondieron HTTP 401; marcador confirmado por FTPS. No se ejecutó rollback en el segundo intento.
- Se actualizó el smoke de navegador para admitir y verificar API productiva 200/401 o API cerrada 503. **Auditoría independiente APROBADA** https://github.com/msanzana/rutahyd2/actions/runs/44963836355 : Chromium confirmó portada HTTP 200, ocho módulos oficiales, login abierto, demo ausente, script productivo cargado, logotipo PNG 350px, API HTTP 200 JSON y `API_MODO=PRODUCCION_JWT_PROTEGIDA` (401 sin JWT).
- SMTP Gmail ya cargado por Laravel y configuración validada, pero **entrega real desde Laravel no certificada** por este workflow.
- **Pendiente principal de usuario**: entrar desde `https://rutahyd2.free.nf/` con su correo y contraseña temporal **sin compartirla en chat**, confirmar que obtiene sesión y puede abrir los módulos permitidos. Probar después logout, perfil y recuperación de contraseña. El workflow ha probado protección 401, **no** ha probado autenticación con contraseña real, token de administrador válido ni CRUD de escritura.
- El marcador ahora existe y mantiene la API habilitada. No pedir su creación ni recomendar volver a importar SQL. Ante un incidente de seguridad confirmado, retirar el marcador y volver a comprobar HTTP 503. No Firecrawl; cambios estructurales mediante DDL SQL.

## Incidente login y recuperación de contraseña — 21-11-2026

- El titular informó que la contraseña temporal enviada por correo no funciona y que el enlace de recuperación abre el formulario durante ~1 segundo antes de retornar al login; solicitó profesionalizar tanto la experiencia de recuperación como la presentación.
- En Gmail enviado, el mensaje `Ruta H&D V2 | Acceso administrador y contraseña temporal` del 21-11-2026 contiene la contraseña temporal comunicada al usuario. **Nunca registrar esa contraseña en este diario ni en código, commits, pruebas o logs.**
- El titular aportó un hash Argon2id que se verificó localmente contra la contraseña del mensaje; **no coincide**. La importación DML reportó 1 fila, pero no implica coincidencia con la contraseña enviada. No reimportar DML ni afirmar login operativo. La solución preferida es que el titular defina una contraseña nueva desde recuperación.
- Se identificó una condición de carrera en el frontend: `recuperacion.js` abría modal antes de `DOMContentLoaded`, pero `app-produccion.js` concluía la comprobación de servidor y `loggedOut()` invocaba el login obligatorio, que tapaba la recuperación. El evento `hashchange` también podía intentar mostrarlo.
- Se corrigió en `msanzana/rutahyd2front` en `assets/js/recuperacion.js`, `login-produccion.js`, `app-produccion.js` y `assets/css/produccion.css`: estado de recuperación explícito, supresión del login mientras está activo, captura del token solo en memoria, eliminación del token de URL, avisos de errores y éxito, formulario corporativo violeta responsive y logotipo oficial.
- Las pruebas Playwright productivas agregaron casos de enlace directo, vencimiento y pantalla móvil, además de actualizar la prueba de regresar al login. **CI aprobado** https://github.com/msanzana/rutahyd2front/actions/runs/44964962779 .
- **Frontend v13 publicado y cotejado por FTPS**: https://github.com/msanzana/rutahyd2/actions/runs/44965092288 . Se desplegaron HTML/JS/CSS en modo producción permanente; backend, MariaDB y SMTP sin cambios.
- Smoke independiente del sitio real que abre un enlace con token simulado, comprueba recuperación visible y no consume ni envía token: https://github.com/msanzana/rutahyd2/actions/runs/44965293949 . Comprobar conclusión antes de darlo por certificado.
- Queda pendiente que el titular solicite un **enlace de recuperación nuevo** (los anteriores pueden expirar), establezca una contraseña privada y verifique inicio de sesión con JWT real. La plantilla HTML corporativa de los correos existe; su apariencia en cliente de correo y la entrega posterior deben validarse aparte. No usar Firecrawl. Estructura SQL exclusivamente vía DDL.

## InfinityFree elimina fragmentos de recuperación durante el desafío inicial — diagnóstico y solución de uso

- CI de frontend **aprobado**: https://github.com/msanzana/rutahyd2front/actions/runs/44964962779 . Publicación v13 **aprobada por FTPS**: https://github.com/msanzana/rutahyd2/actions/runs/44965092288 .
- La primera prueba independiente de enlace directo abrió en `/?i=1` y mostró el login: https://github.com/msanzana/rutahyd2/actions/runs/44965293949 . **No declarar que el enlace funciona a primer clic en un navegador sin cookies.**
- Diagnóstico posterior https://github.com/msanzana/rutahyd2/actions/runs/44965432884 verificó `location=https://rutahyd2.free.nf/?i=1`, `window.RutaHDRecuperacion.isActive()=false`, login visible y ausencia de token: la protección inicial de InfinityFree pierde el fragmento `#recuperar?token` antes de cargar la app.
- Se reprodujo y **confirmó un procedimiento de uso que funciona** en navegador real con https://github.com/msanzana/rutahyd2/actions/runs/44965629426 : primera apertura fría pierde fragmento; tras establecer la cookie, al **abrir nuevamente el MISMO enlace en el MISMO navegador** se muestra `recovery-dialog`, login oculto, campos de cambio de contraseña, token retirado de la barra URL. No se envió una contraseña ni se consumió un token real: se empleó token sintético.
- Instrucción operativa actual para el titular: entrar a `https://rutahyd2.free.nf/` en el navegador donde abrirá el correo, esperar a que cargue; desde ese mismo navegador abrir el mensaje nuevo de recuperación y seguir su enlace. Si la primera visita al enlace acaba en login, volver al correo y pulsarlo otra vez sin cambiar de navegador, incógnito ni app. El enlace de recuperación vence a los 30 minutos; solicitar uno nuevo si es necesario.
- **Pendiente solución definitiva de enlace de un solo clic:** la infraestructura InfinityFree fuerza desafío que descarta fragmento. Se requiere replantear URL de recuperación/hosting de forma segura para preservar un token de un solo uso, con evaluación de exposición en logs antes de moverlo del fragmento al path o query. No afirmar que ese problema esté reparado solo por JS. La apariencia del correo HTML también puede perfeccionarse en una publicación futura, pero no modificar plantilla actual ni API autenticada sin backup.
- El hash de contraseña aportado no coincide con la contraseña temporal del correo original. No almacenar ninguna de las dos en el repositorio; preferir restablecimiento con contraseña nueva y prueba de JWT.

## Recuperación robusta de contraseña — cierre técnico 21-11-2026

**Incidente reportado:** el enlace del correo de restablecimiento abría brevemente el formulario y después lo sustituía el login; la presentación no cumplía el estilo corporativo.

**Causa confirmada con navegador sin cookies:** InfinityFree agrega `?i=1` y su redirección elimina `#recuperar?token=...`. La SPA no recibe el token en la primera visita y abre el login. Diagnóstico probado con enlaces sintéticos sin revelar credenciales: https://github.com/msanzana/rutahyd2/actions/runs/44966085112 . Con `/?recuperar=<token>`, InfinityFree **conserva** el parámetro y añade `&i=1`.

**Corrección implementada:**
- Frontend `msanzana/rutahyd2front`: `recuperacion.js` admite query nuevo y fragmento heredado, elimina inmediatamente el token de la barra URL y lo mantiene solo en memoria; login/router reconocen recuperación activa y no la reemplazan. `index.html` lleva `referrer=no-referrer`.
- Nueva presentación violeta H&D con logotipo, pasos guiados, mostrar/ocultar contraseña, mensajes de validación y adaptación móvil.
- CI frontend con pruebas de nueva URL `?recuperar=...`, simulación `&i=1`, cambio de contraseña con API simulada y navegación móvil: https://github.com/msanzana/rutahyd2front/actions/runs/44966369462 (aprobada).
- **Frontend v14 publicado y verificado por FTPS**: https://github.com/msanzana/rutahyd2/actions/runs/44966479180 (éxito).
- **Prueba en servidor real de primer clic con navegador sin cookies:** https://github.com/msanzana/rutahyd2/actions/runs/44966681348 (éxito). Confirmó `RECUPERACION_QUERY_PRIMER_CLICK`: formulario visible, login oculto, token fuera de URL, `no-referrer`, layout `grid`, API JWT productiva disponible.
- Backend Laravel: rama versionada `feature/recuperacion-query-link-20261121` actualiza `RecuperacionContrasenaControlador::enviarEnlace` para los **nuevos correos** con `/?recuperar=<token>`.
- Primer despliegue de backend https://github.com/msanzana/rutahyd2/actions/runs/44966567780 falló en FTP rename, rollback completado. Se sustituyó rename por PUT directo con respaldo y comparación. **Segundo despliegue de backend aprobado**: https://github.com/msanzana/rutahyd2/actions/runs/44966773942 . Solo controlador PHP, API JWT, SMTP, .env, frontend y MariaDB intactos.
- No se tocó el hash de administrador ni se importó SQL. DDL sigue siendo la vía exclusiva para estructuras; **Firecrawl descartado**.

**Acción final pendiente del titular:** desde `https://rutahyd2.free.nf/` solicitar un **correo nuevo** en “Recuperar contraseña”, usar el enlace **nuevo** (los anteriores tienen formato hash), definir una contraseña privada de al menos 12 caracteres y comprobar login y logout. No compartir contraseñas ni enlaces con token.

**Limitación de seguridad documentada:** al usar query, el token llega al servidor web; se mitiga con enlace de un solo uso, 30 minutos, `Referrer-Policy: no-referrer` y limpieza inmediata de la URL. En un hosting que permita conservar fragmentos sin desafío se preferirá volver al modelo de fragmento o un flujo de intercambio más robusto. No se ha probado un cambio de contraseña real de la cuenta del titular; las pruebas fueron con tokens sintéticos.

## Sesión JWT expulsada poco después del login — investigación 22-11-2026

- **Usuario confirma que la recuperación de contraseña y el inicio de sesión real ya funcionan.** Después de algunos segundos dentro del programa, la interfaz regresa al login. No solicitar la contraseña, reimportar SQL ni modificar la cuenta.
- **Defecto detectado y corregido en frontend**: `assets/js/app-produccion.js` borraba ACL/JWT por cualquier evento `rutahd:api-estado` con `disponible=false`, incluso por el estado transitorio "Conectando..." o falla momentánea de un sondeo. Ahora una caída o sondeo no cierra la sesión; solo una revocación 401 del JWT realmente utilizado o logout explícito.
- **Condición de carrera corregida**: en `assets/js/catalogos-api.js`, una respuesta 401 tardía de una sesión anterior no puede cerrar una sesión nueva. Se captura el Bearer de cada solicitud y se compara con la sesión actual antes de revocarla. Pruebas Playwright de ambos escenarios aprobadas: https://github.com/msanzana/rutahyd2front/actions/runs/44967849292 .
- **Diagnóstico real de Apache/InfinityFree**: https://github.com/msanzana/rutahyd2/actions/runs/44967746763 . Solicitud HTTPS con `Authorization: Bearer hd-diagnostic-header` (valor ficticio, sin cuenta) llegó a PHP con `HTTP_AUTHORIZATION=false`, `REDIRECT_HTTP_AUTHORIZATION=false` y `getallheaders Authorization=false`; el `/api/.htaccess` no tenía regla para conservar el header. Esto explica 401 potenciales en recursos después de un login 200.
- **Corrección estándar validada SIN tocar la API real**: https://github.com/msanzana/rutahyd2/actions/runs/44968071425 . En directorio temporal se probó `RewriteCond %{HTTP:Authorization} .` seguido de `RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`; PHP recibió Authorization tanto en ruta directa como reescrita. Directorio temporal eliminado.
- **Ruta aprobada**: modificar solo `/htdocs/api/.htaccess` con esa regla Apache, conservando los demás archivos; validar HTTP 200 del estado y 401 sin/falso JWT, rollback ante errores. **Corrección Apache PUBLICADA Y VERIFICADA**: https://github.com/msanzana/rutahyd2/actions/runs/44968199467 (éxito). Sólo se añadió la regla de encabezado a `/htdocs/api/.htaccess`, con respaldo; el archivo de gateway PHP y el guardia privado no cambiaron. **Frontend v15 PUBLICADO Y VERIFICADO**: https://github.com/msanzana/rutahyd2/actions/runs/44968117739 (éxito).
- No se eligió header JWT alternativo. Se descartó y eliminó del código no desplegado; se conserva el Bearer JWT estándar y su validación Laravel. No Firecrawl; no cambios en SMTP, secretos ni DB (DDL/DML).
- **Pendiente final**: con ambos despliegues exitosos, pedir al titular reingresar, permanecer en Inicio y probar Administración/Productos tras unos segundos; verificar que la sesión no se pierde. No declarar el login fullstack confirmado hasta esa aceptación real.


## Comprobación independiente posterior al arreglo de logout — 22-11-2026

- **Smoke Chromium exitoso**: https://github.com/msanzana/rutahyd2/actions/runs/44968483107 . Frontend productivo y ocho menús oficiales, API disponible y recuperación de contraseña conservada.
- **Autorización realmente transportada a Laravel**: prueba con JWT totalmente ficticio produjo HTTP 401 distinto del 401 anónimo y el mensaje del servidor correspondió al validador JWT; indicador `TRANSPORTE_BEARER_LARAVEL` con `distinct_error_messages=true`, `fake_reaches_jwt=true`. Esta prueba evita usar contraseñas o tokens de usuarios.
- El frontend ya no invalida una sesión al cambiar `rutahd:api-estado` a no disponible durante un sondeo; un 401 de una petición de sesión anterior tampoco elimina un JWT recién iniciado.
- **Estado final**: cambios publicados y probados sintéticamente, sin DDL, DML, Firecrawl, SMTP o cambios a credenciales. La validación de persistencia con JWT auténtico del titular queda pendiente de su prueba de navegación; no declarar verificado un login real prolongado hasta su confirmación.

## Confirmación del login estable y nueva etapa CRUD comercial — 22-11-2026

**Confirmación directa del titular**: el cambio de contraseña, el ingreso JWT y la permanencia de sesión funcionan en el entorno productivo; puede navegar por Administración y Productos sin ser expulsado. El incidente de expulsión del 22-11 queda CERRADO por aceptación del titular.

**Etapa CRUD completada en código y publicada**:
- Se revisaron las tablas del esquema español `clientes`, `proveedores`, `bodegas`: todas existen; no fue necesario añadir ni modificar columnas. **No hubo DDL ni DML en producción**.
- Se creó la rama fuente del backend `feature/crud-clientes-proveedores-bodegas-20261122`, con `EntidadesComercialesControlador` y rutas POST, PUT y DELETE limitadas a esas tres entidades. Se conservan las lecturas paginadas existentes. Validación estricta de campos, permisos por entidad (Clientes `clientes.gestionar`, Proveedores `compras.gestionar`, Bodegas `inventario.gestionar`), 401/403, conflictos de claves y baja lógica (no `DELETE` SQL físico).
- Los módulos `clientes`, `proveedores` y `bodega` del frontend oficial ahora incluyen listado paginado, crear, editar, desactivar, reactivar en edición, mensajes de validación y modales corporativos violeta, sin almacenamiento local. Las operaciones de Ventas y Órdenes de compra **siguen en lectura** para no crear movimientos contables sin reglas de negocio.
- **Backend CI real MySQL efímero APROBADO**: https://github.com/msanzana/rutahyd2/actions/runs/44969447525 . Se ejecutaron pruebas de login, catálogos, productos (incluido IVA), permisos y CRUD de las tres entidades, con altas/ediciones/bajas **solo en base temporal**. No usar la prueba contra producción.
- **Frontend CI Playwright APROBADO (19 pruebas)**: https://github.com/msanzana/rutahyd2front/actions/runs/44969510463 . Se validaron altas, cambios, bajas, no exposición de escritura a usuarios sin permiso y prohibición de escritura genérica sobre Ventas.
- **Backend publicado por FTPS y verificado**: https://github.com/msanzana/rutahyd2/actions/runs/44969647774 . Controlador y rutas cotejados byte a byte. En navegador sin JWT: `/api/v1/estado` 200; GET Clientes, POST Clientes, PUT Proveedores, DELETE Bodegas e intento con token JWT falso devolvieron 401; POST Ventas por CRUD genérico 405. El gateway PHP y la protección privada permanecieron intactos.
- **Frontend productivo publicado por FTPS y verificado**: https://github.com/msanzana/rutahyd2/actions/runs/44969783478 . Flujo de producción sin modo demostrativo, sin cambio de configuración de SMTP, JWT, contraseña ni MariaDB.
- **Auditoría independiente del frontend PUBLICADO aprobada**: https://github.com/msanzana/rutahyd2/actions/runs/44969983128 . Chromium verificó ocho menús oficiales, JS módulos HTTP 200 con acciones y diálogos CRUD, cliente API HTTP 200 con whitelist de solo tres entidades, CSS corporativo HTTP 200, Laravel `/api/v1/estado` HTTP 200 y lectura anónima de Clientes HTTP 401. La pantalla de login permanece obligatoria.
- El código de los tres CRUD está funcional en pruebas CI y publicado, pero **no se han ejecutado escrituras reales en la MariaDB de producción con la sesión del titular**; validar altas/ediciones autorizadas antes de declarar esa aceptación real.

**Pendientes de próximas etapas**:
1. Probar operaciones con el administrador en Clientes, Proveedores y Bodegas y confirmar reglas de negocio, especialmente prohibir la desactivación de bodegas con saldos de stock positivos.
2. Compras: diseñar órdenes de compra con detalles y recepción de mercancía transaccional.
3. Ventas y notas de venta: definir estados, detalle, pagos, numeración y DTE sin automatizar su envío al SII.
4. Inventario: traslados y movimientos transaccionales; separar CRUD de bodegas del manejo de saldos.
5. Cuentas corrientes e informes: establecer contratos y DDL **previo a cualquier implementación de esquema**.
6. Refinar correos corporativos y completar pruebas de entrega real desde Laravel si sigue pendiente.

**Políticas permanentes del proyecto**: solo modo PRODUCCIÓN, todo cambio estructural SQL mediante **DDL**, **NO Firecrawl**, nunca guardar credenciales ni tokens en GitHub, chat o JavaScript.

## Solicitud de catálogo y abastecimiento — 22-11-2026

**Especificación del titular**: se adjuntó captura de formulario de Producto estrecho y descuadrado. Solicita popup ancho y responsivo, sin ID de proveedor; estructura Proveedores → Listado de proveedores y Proveedores productos; nuevas entidades de vinculación y códigos de barras de compra/venta, costos, unidades, última recepción y catálogo de simbologías; completar unidades/envasados comunes; retirar gramajes visibles; Administración restringida a Tipos de unidad, Tipos de envasado, Condición tributaria, Tipos de códigos de barras, Usuarios, Perfiles y permisos.

**Corrección independiente publicada y segura**:
- Frontend main cambió solo el editor de Productos existente para ampliar a 1100 px máximo, reorganizar encabezados/campos/checkbox y evitar scroll horizontal en móvil; desaparece el campo manual ID proveedor, conservando el contrato Laravel anterior hasta introducir las nuevas tablas. La clasificación de gramajes antigua sigue temporalmente porque la API productiva la requiere.
- CI de frontend main **aprobada**: https://github.com/msanzana/rutahyd2front/actions/runs/44973099694 (incluye pruebas modal 1440, 820, 572, 390).
- Publicación de solo frontend v16 **éxito**: https://github.com/msanzana/rutahyd2/actions/runs/44973237940 . Backend y MariaDB no modificados.
- **Prueba independiente en sitio real Chrome APROBADA**: https://github.com/msanzana/rutahyd2/actions/runs/44973520697 . Con recursos CSS/JS servidos desde InfinityFree y JWT de prueba simulado, se confirmó popup a 1440px (ancho 1100; sin overflow), a 572px (ancho 560; sin overflow) y a 390px (ancho 378; sin overflow). Sin campo ID proveedor y presentación/IVA visibles. El primer smoke falló por recarga incorrecta del stub, se corrigió el procedimiento y el segundo comprobó el sitio productivo sin modificar DB ni credenciales.

**Implementación completa preparada en ramas, NO publicada por requerir DDL**:
- Backend rama `feature/proveedores-productos-barcodes-20261122`, hereda el CRUD anterior de Clientes/Proveedores/Bodegas. SQL DDL aditivo `database/espanol/20_proveedores_productos_codigos_barras_ddl.sql` crea CUATRO tablas nuevas: `tipos_codigos_barras`, `productos_proveedores`, `productos_codigos_barra_venta`, `productos_codigos_barra_compra`.
- Archivo 21 `21_catalogos_referencia_semillas.sql` es **DML de referencia separado**, porque DDL no puede poblar tablas: registra unidades, envasados, EAN13/DUN14/ITF14/CODE39/CODE128/otros y dos permisos. Archivo 22 verifica después la estructura y semillas en solo lectura.
- Se mantiene la tabla existente `proveedores` y su CRUD Laravel; no se crea duplicado. Los códigos SKU propios y proveedor se resuelven con JOIN desde `productos.sku` y `productos_proveedores.codigo_proveedor_producto`. Costo proveedor, ultima_recepcion_id y fecha_ultima_recepcion están en la relación. El ID de recepción es NULL sin FK todavía, pues falta tabla de recepción validada.
- Backend: se ajustan ProductoComercialController, ClasificacionProductoController, CatalogoComercialController, PermisosCatalogos, RecursoController; se incluyen controladores y rutas específicos para Proveedores productos y códigos de compra/venta (JWT/permisos, bajas lógicas y listados, sin escritura genérica a inventario/ventas). Se retira `gramajeId` de nueve clasificaciones requeridas; **NO se borra** `gramajes` ni `productos.gramaje_id` históricos.
- Frontend rama `feature/productos-proveedores-codigos-barras-20261122`: Administración con seis subítems exactos, Productos con jerarquías y código de venta múltiple dentro de popup 1120 px, Proveedores con dos subítems, búsqueda en Listado y relación con CRUD y códigos compra. No se emplean demos ni localStorage operativo.
- **CI backend MySQL efímero aprobada**: https://github.com/msanzana/rutahyd2/actions/runs/44973187644 . Probó DDL20 dos veces, DML21 dos veces, 50 tablas, JWT, permisos, productos, proveedores, códigos de venta/compra, historiales y transacciones.
- **CI frontend rama aprobada: 25 pruebas** https://github.com/msanzana/rutahyd2front/actions/runs/44973003447 , desktop/tablet/móvil y menú oficial. No se introdujeron datos reales.
- Diseño normalizado, reglas y orden de despliegue documentados en `docs/ESPECIFICACION_PRODUCTOS_PROVEEDORES_CB_20261122.md` de la rama backend.
- **Bloqueo para publicar el módulo completo**: la base productiva existente sigue con 46 tablas hasta que el usuario respalde estructura/datos y aplique DDL20 y semillas21 por phpMyAdmin, validando los recuentos del SQL22. No desplegar nuevos endpoints frontend que consulten tablas inexistentes. No reimportar SQL12–19 ni tocar contraseña/SMTP.
- **Políticas**: todas las modificaciones de estructura mediante DDL explícito, semillas DML separadas y declaradas, NO Firecrawl, producción permanente, JWT seguro.

## Importación SQL20/21 verificada y backend de abastecimiento publicado — 22-11-2026

- El titular importó SQL 20/21 en la base productiva `if0_43460400_rutahyd2_es` y ejecutó SQL22. La imagen del último SELECT mostraba 12 campos de `productos` y `productos_proveedores`; **no correspondía al resumen numérico**. Para evitar asumir que todo estaba completo se realizó una auditoría independiente en producción con una sonda PHP temporal protegida y SELECT sin escrituras: https://github.com/msanzana/rutahyd2/actions/runs/44974987537 **aprobada**. Resultado **exacto**: base correcta; **50 tablas** totales, **4 nuevas**, **11 simbologías**, **2 permisos nuevos**, **8 unidades básicas**, **6 envasados básicos**, **6 FKs**, **7 columnas esperadas de productos_proveedores**. La sonda se eliminó, no se consultaron registros sensibles ni se tocó el contenido de la DB.
- Error previo #1109 de SQL22 corregido en rama `feature/proveedores-productos-barcodes-20261122`: subconsultas califican explícitamente `if0_43460400_rutahyd2_es` y CI MySQL temporal aprobó la corrección https://github.com/msanzana/rutahyd2/actions/runs/44974547076.
- **Laravel comercial publicado en producción con respaldos y rollback**: https://github.com/msanzana/rutahyd2/actions/runs/44975302543 **éxito**. Se transfirieron los controladores `ProductosProveedoresControlador` y `CodigosBarrasProductoControlador`, y se actualizaron `CatalogoComercialControlador`, `ClasificacionProductoControlador`, `ProductoComercialControlador`, `RecursoControlador`, `PermisosCatalogos` y `routes/api.php`. Preflight cotejó por bytes el código original de cada archivo con el servidor. Los archivos nuevos y modificados se compararon byte a byte después del despliegue y el gateway y `.htaccess` quedaron intactos.
- **Smoke de seguridad API productiva aprobado**: `estado=200`, GET/POST de `proveedores-productos`=401 sin sesión, GET códigos compra/venta=401 sin sesión, GET catálogo tipos de códigos=401, JWT ficticio=401 y POST Ventas por vía genérica=405. Sin escrituras de ensayo.
- Primer intento backend https://github.com/msanzana/rutahyd2/actions/runs/44975248734 falló ANTES de FTP por un grep incorrecto del nombre de tabla en rutas URL; corregido en segundo intento, no afectó producción.
- Frontend v17 consolidado **sobre main v16**, sin perder la mejora visual del popup. Rama `feature/integracion-codigos-proveedores-release-20261122` pasó **29 pruebas** Playwright https://github.com/msanzana/rutahyd2front/actions/runs/44975168946 y se fusionó mediante PR https://github.com/msanzana/rutahyd2front/pull/8 en SHA `cacafb109f27c102741515d6e2a06aeed7e547a1`.
- **Pendiente en el momento de esta nota**: CI main frontend v17 y publicación FTPS de recursos/HTML con cache-buster `20261122-prod17`, seguida de smoke público de menús, AJAX, popup y CSS. No afirmar frontend público actualizado antes del éxito de FTPS.
- Al operar en producción no crear, editar ni desactivar registros de prueba automáticamente; pedir aceptación del titular de operaciones con la cuenta real. Si el usuario no confirmó un respaldo reciente, no efectuar nuevas importaciones SQL.

## Cierre de integración productiva v17 — 22-11-2026

**Validaciones finales tras SQL20/21 aportados por el titular**:
1. MariaDB productiva `if0_43460400_rutahyd2_es`: 50 tablas, 4 tablas nuevas, 11 simbologías, 2 permisos, 6 FKs, 8 unidades y 6 envasados, 7 campos de relación. Auditoría de solo lectura con sonda efímera (eliminada): https://github.com/msanzana/rutahyd2/actions/runs/44974987537, **éxito**.
2. Backend Laravel: https://github.com/msanzana/rutahyd2/actions/runs/44975302543 **éxito**. Código exacto cotejado por FTPS y rutas autenticadas. GET estado=200, GET y POST nuevos sin JWT=401, JWT ficticio=401, POST Ventas genérico=405. Sin modificaciones a MariaDB, `.env`, gateway, JWT ni SMTP.
3. Frontend consolidado sobre `main` sin perder hotfix v16; PR https://github.com/msanzana/rutahyd2front/pull/8 fusionada en `cacafb109f27c102741515d6e2a06aeed7e547a1`. CI completa de **29 pruebas** en main https://github.com/msanzana/rutahyd2front/actions/runs/44975486466, **éxito**; también CI de rama de integración https://github.com/msanzana/rutahyd2front/actions/runs/44975168946, **éxito**. Se actualizaron workflows PR antiguos para validar los menús v17, no las pantallas demo heredadas.
4. Frontend **v17 publicado por FTPS**: https://github.com/msanzana/rutahyd2/actions/runs/44975636120 **éxito**. Páginas, JavaScript y CSS comparados por bytes; HTML actualizado al final, cache-buster `20261122-prod17`, logo corporativo real, gateway privado intacto.
5. **Smoke navegador real en InfinityFree**: https://github.com/msanzana/rutahyd2/actions/runs/44975766022 **éxito**. Resultados: API pública estado=200, catálogo comercial sin JWT=401; ocho módulos principales; submenús de Proveedores=[Listado de proveedores, Proveedores productos]; Administración exactamente seis opciones=[Tipos de unidad, Tipos de envasado, Condición tributaria, Tipos de códigos de barras, Usuarios, Perfiles y permisos]; jerarquías Productos=6. JS/CSS recibidos HTTP 200, script y estilos v17 confirmados; popup Producto **1120 px sin overflow** y sin ID proveedor ni Gramajes, con sección de múltiples códigos de barras de venta. La navegación del smoke utilizó JWT *ficticio interceptado*; no se enviaron datos ni se escribió en producción.
6. API y UI reales están integradas y desplegadas. **Pendiente aceptación con administrador real**: realizar prueba guiada desde el propio navegador del titular creando un producto legítimo y sus códigos de venta, vinculándolo a un proveedor y asignando códigos de compra. No confundir pruebas con JWT simulado con escrituras reales: no se crearon datos de ensayo en MariaDB.

**Reglas y límites explícitos**:
- La tabla histórica `gramajes` y `productos.gramaje_id` se mantienen físicamente para no borrar datos, pero desaparecieron de menús y de las nueve clasificaciones obligatorias del producto.
- `productos_proveedores.ultima_recepcion_id` y `fecha_ultima_recepcion` siguen **NULL / solo lectura** hasta implementar **Recepciones** transaccionales. La implementación de este módulo es pendiente separada; no inventar IDs de recepción ni actualizar manualmente desde el CRUD.
- Los tipos de códigos de barras nuevos tienen permisos específicos de lectura/gestión en el catálogo, configurables desde Perfiles. El administrador dispone de ellos. Un rol de compras sin el permiso de catálogo podría necesitarlo para visualizar la lista de tipos.
- El catálogo de clientes/proveedores/bodegas anterior permanece operativo, y no se habilita escritura genérica de ventas ni movimientos de inventario.
- No pedir contraseñas, tokens de JWT ni correos de recuperación; no usar Firecrawl; cualquier cambio estructural adicional debe prepararse en SQL DDL separado de semillas DML.


## Ajustes de interfaz CRUD y sesión — revisión 21-11-2026

- Fuentes: inspección de frontend `msanzana/rutahyd2front` rama `main` y este diario; búsqueda en Google Drive por Ruta H&D, diario y fuente de conocimiento sin coincidencias.
- Cambios confirmados en GitHub frontend `main`: CSS `assets/css/produccion.css` commit `e9d7321d3bae9f90f5f7a40aa4237ddf730a5fb2` y HTML `index.html` commit `2d28f119201f8c4ad5f922fd765c5df80e0dcaa9`.
- Listados: contenedores con desplazamiento horizontal cuando sea necesario; última columna de acciones alineada a la derecha sin convertir `td` en contenedores flex; controles de acciones agrupados visualmente.
- Diálogos: estilo transversal de inputs, focus visible, límites de ancho/alto y acciones adaptables. Se mantienen formularios especializados de Producto (1120 px), Proveedores productos (840 px) y Clientes/Proveedores/Bodegas (770 px).
- Sesión: el único botón de salir sigue siendo `#authButton` gobernado por `login-produccion.js`; se reubica en el HTML después del bloque de perfil/nombre en la barra superior. No se añadió otro logout.
- Se incrementó versionado de `produccion.css` a `20261121-2` para evitar caché antigua.
- Esta revisión solo confirma commits y lectura de código. **No se verificaron todavía CI de estos nuevos commits, navegador público ni despliegue FTPS**. No afirmar que los cambios están visibles en producción.
- No se modificaron backend, tablas, datos ni credenciales. Próximo paso: ejecutar CI responsivo y publicar solo frontend con respaldo y verificación FTPS, luego smoke real en 1440/820/390 px sin operaciones de escritura.


## Rediseño corporativo CRUD v18 y publicación verificada — 21-11-2026 Chile / 22-11-2026 UTC

- Motivo: el titular reportó CRUD visualmente desordenados y que los cambios anteriores no eran visibles en producción. Se realizaron cambios de **componentes y estilos**, no solo un ajuste cosmético local.
- Fuente publicada: frontend `msanzana/rutahyd2front`, rama `main`, revisión `4ae53efc8e793b40d020e6ed2962788219ee21ed`.
- Nueva hoja `assets/css/crud-v18.css`: ventanas corporativas violeta con encabezados, subtítulos, cierre visible, campos equilibrados, estados, footer de acciones, formularios Productos/Proveedores productos y CRUD Clientes/Proveedores/Bodegas/Usuarios/Catálogos responsivos; tablas con acciones en última celda a la derecha y scroll horizontal solo de la tabla cuando corresponde.
- Componentes actualizados: `admin-remoto.js`, `usuarios.js`, `modulos-oficiales.js`, `productos-remotos.js`, `proveedores-productos.js` e `index.html`. Se eliminó el control duplicado **Cerrar sesión remota** en Administración. Único botón de salida: `#authButton`, adyacente al bloque de nombre en la barra superior.
- CI frontend completa: **33 pruebas Playwright aprobadas**, https://github.com/msanzana/rutahyd2front/actions/runs/44976959711 ; incluye pruebas nuevas en 1440/820/390px, validando diálogos y columnas de acciones. Sin operaciones reales.
- **Publicación productiva FTPS exitosa**, https://github.com/msanzana/rutahyd2/actions/runs/44977040072 ; respaldo de archivos previos, transferencia de CSS/JS, HTML al final, cotejo por bytes también de `crud-v18.css`, guardia Laravel intacta, rollback preparado (no utilizado).
- **Smoke navegador independiente sobre https://rutahyd2.free.nf/ exitoso**, https://github.com/msanzana/rutahyd2/actions/runs/44977204463 ; comprobó CSS v18 enlazado, tablas `TD` con `display:table-cell` y acciones a la derecha, un botón de cerrar sesión, modal Clientes con degradado violeta, cierre accesible y sin overflow, en ancho de pantalla 1440, 820 y 390px. Se guardaron capturas de 3 tamaños como artefactos del workflow.
- La prueba pública usó API interceptada y JWT ficticio en Chromium; **no** realizó autenticación productiva ni escrituras en MariaDB. Backend, estructura SQL, contraseñas y SMTP no se modificaron.
- Pendiente de aceptación manual por el titular con su cuenta real: ergonomía de los campos y comportamiento de guardado de todos los CRUD con permisos efectivos. Si se ven recursos antiguos por caché del navegador, recargar forzadamente.


## Corrección de regresiones en catálogos, listas y menús v19 — 21/22-11-2026

**Motivo del titular:** listados que parecían vacíos, botones «Nuevo» que no abrían (Rubros, Departamentos, Marcas, etc.) y submenús fuera del diseño oficial.

### Errores confirmados en frontend
- `admin-remoto.js` tenía `const key=selected,parentKey=CONFIG[key][1]`, lo que accedía a `key` antes de su inicialización y generaba `ReferenceError` al pulsar «Nuevo»; corregido a inicialización secuencial.
- La consulta de catálogos se hacía con `Promise.all` sobre todos los catálogos permitidos, de forma que el fallo de un solo endpoint bloqueaba el resto y dejaba una pantalla sin listado. La nueva implementación consulta solo el submódulo solicitado (y su padre cuando procede), diferencia respuestas vacías de fallos y ofrece reintento; descarta respuestas antiguas al navegar rápidamente.
- Productos cargaba todos los catálogos antes de ejecutar la consulta del listado. El listado ahora se consulta independientemente de las clasificaciones y conserva los productos aunque falle un catálogo. Al abrir Nuevo/Editar se validan las clasificaciones; si alguna falla, se muestra diálogo con error concreto y reintento, sin inventar registros ni guardar información incompleta.
- `accesos.js` incluía el catálogo retirado `gramajes` pero omitía `tipos_codigos_barras`. Actualizado según menú oficial y permisos.
- `app-produccion.js` determinaba incorrectamente el origen de navegación al volver por hash en submódulos; ahora asigna cada catálogo a Administración o Productos según su clasificación. Los menús ya no quedan congelados por sondeos transitorios de disponibilidad cuando existe sesión JWT.
- `crud-v18.css` añadió reglas v19 para submenús planos de Administración, Proveedores y Productos, manteniendo anchos, sangrías, estados activos y scroll responsivo en desktop/móvil; no cambia el orden HTML oficial.
- En CRUD dependientes, cuando no existen valores padre activos se muestra instrucción explícita de registrar primero el catálogo padre (Rubro→Departamento→Familia→Línea, Marca→Modelo).

### Verificación y publicación
- **Frontend fuente `main`:** SHA `8cfaea41122990cb5a821c76707f1a794caefd19`.
- **CI frontend:** 41 pruebas Playwright aprobadas https://github.com/msanzana/rutahyd2front/actions/runs/44977878666. Se añadieron pruebas que abren y guardan «Nuevo Rubro» con API simulada, abren «Nuevo Departamento», navegación rápida Rubros→Marcas, listas de producto pese a error de códigos, catálogos realmente vacíos y geometría de submenús a 1440 y 390 px.
- **Publicación FTPS:** https://github.com/msanzana/rutahyd2/actions/runs/44978018410 **éxito**, con respaldo temporal, publicación HTML al final, comparación de bytes y validación de guardia Laravel intacta; sin rollback necesario.
- **Smoke público independiente:** https://github.com/msanzana/rutahyd2/actions/runs/44978179560 **éxito**. Chromium comprobó en `https://rutahyd2.free.nf/` las referencias CSS/JS v19, inicio con JWT ficticio interceptado, navegación a Productos→Rubros, lectura simulada, apertura de «Nuevo Rubro» y ausencia de overflow en 1440/390 px. Capturas disponibles en artefactos del workflow.
- **Sin cambios en Laravel, MariaDB, DDL, credenciales ni registros reales**. Las pruebas HTTP emplearon respuestas interceptadas; **no** validan los conteos actuales de filas reales ni una operación de escritura con sesión del titular.
- Próxima aceptación funcional: comprobar con la cuenta del titular catálogos que deberían tener filas y registrar altas genuinas. Si el backend devuelve `items:[]`, la aplicación muestra un estado vacío real; nunca autogenerar registros ficticios en producción.


## Normalización UI v20: listados, búsquedas, menú de usuario, árbol de permisos — 22-11-2026

**Solicitud original del titular:** normalizar todos los listados y búsquedas generales; popup de búsqueda avanzada; acceso visible a añadir códigos de barras en Productos; acciones Editar/Desactivar como iconos con tooltip; eliminar login del dashboard y símbolos redundantes de servidor conectado; administrar accesos de subítems en árbol; menú del avatar con Mi perfil, cierre de sesión, modo oscuro por usuario.

**Frontend publicado v20:**
- Fuente `msanzana/rutahyd2front` `main` SHA `259a251ecb3cb3455d6ff7f7630413b14412f9b4`.
- Nuevos `assets/js/listados-ui.js` y `assets/css/listados-tema-v20.css`: tabla unificada/accionamiento con iconos accesibles y tooltip, cuadro de búsqueda general y `Ctrl+K`, botón de búsqueda avanzada con diálogo de filtros por columnas, estados y borrado; el filtrado adicional se realiza **solo sobre la página cargada**, evitando afirmar búsquedas globales sin soporte de API.
- `assets/js/productos-remotos.js`: añadir código de barras destacado en editor, acceso desde celda de acciones al editor enfocando barra. Conserva JWT, SKU, clasificaciones, códigos venta e historial.
- `index.html` / `login-produccion.js`: no login CTA en Dashboard, sin `#apiStatus` de cabecera y sin indicador conectado en login; mostrar errores de conexión cuando corresponde; avatar con menú de perfil/sesión, sin botones de logout duplicados.
- `assets/js/menu-usuario.js` y `accesos.js`: perfil de solo lectura (vía `/perfil`), switch oscuro guardado por ID de usuario únicamente en el navegador (preferencia visual, nunca JWT ni credenciales), cierre de sesión real, acceso a perfiles para administración. CSS implementa variantes dark para paneles, formularios, tablas y menú.
- `perfiles.js`: árbol oficial de ocho módulos y subítems, diez permisos de catálogos editables con contrato backend existente, otros subítems con el estado de permiso base heredado. Tres listados tendrán controles independientes después de publicar esquema v23 y backend.
- `app-produccion.js`: menú preparado para aplicar permisos independientes cuando el servidor los devuelva, retrocompatible con los generales actuales.
- CI `https://github.com/msanzana/rutahyd2front/actions/runs/44980054689`: **45 pruebas Playwright aprobadas**, incluyendo test nuevo de filtros/iconos, avatar/tema por cuenta, árbol y botón barcode; sin credenciales reales ni escrituras productivas.
- Publicación FTPS `https://github.com/msanzana/rutahyd2/actions/runs/44980175533`: **éxito**, copia de seguridad previa, publicación HTML al final, comparación por bytes de nuevos recursos; Laravel/MariaDB/gateway intactos.
- Smoke de navegador en sitio real añadido por `.github/workflows/verificar-ui-v20-publico.yml`, trigger `deployment/solicitudes/verificar-ui-v20-publico.txt`. Estado de ese smoke pendiente de registrar tras terminar la ejecución.

**Backend v23 preparado, NO publicado en producción:**
- Rama `feature/proveedores-productos-barcodes-20261122` con `database/espanol/23_preferencias_usuarios_permisos_submodulos_ddl.sql`: solo dos tablas aditivas `preferencias_usuarios` y `roles_submodulos`; SQL explícito según orden del usuario. Sin importar en MariaDB productivo.
- Servicio `PermisosSubmodulos` y middleware en Laravel: `productos_listado`, `proveedores_listado`, `proveedores_productos` con `ver`/`gestionar` adicionales; jamás elevan derechos base, solo pueden restringir. Catálogos mantienen sus 10 permisos independientes, Usuarios/Perfiles admin reservado.
- Perfil y login entregarán derechos de submódulo y preferencia guardada por usuario; endpoints `GET/PUT /perfil/preferencias` seguros solo tras DDL validado. Frontend mantiene preferencia local por usuario hasta entonces.
- CI DDL23 con MariaDB aislada `https://github.com/msanzana/rutahyd2/actions/runs/44979963068` **éxito**; CI Laravel completo con DDL23 en MySQL temporal `https://github.com/msanzana/rutahyd2/actions/runs/44980154387` **éxito**. Documento de instalación segura `docs/AMPLIACION_PERMISOS_Y_TEMA_V23.md` en esa rama.
- **Próximo paso requiere respaldo e importación manual de DDL23 por titular** antes del despliegue backend. No modificar perfiles de producción, no guardar contraseñas, no introducir datos de prueba. No afirmar sincronización entre dispositivos todavía.


### Cierre de pruebas públicas UI v20 — 22-11-2026

- Workflow `https://github.com/msanzana/rutahyd2/actions/runs/44980390561` **aprobado** en navegador Chromium real contra `https://rutahyd2.free.nf/`. Recursos v20 descargados, login con JWT ficticio interceptado (sin credenciales reales), menú avatar, interruptor oscuro, botones de acción convertidos en iconos y popup de búsqueda avanzada comprobados.
- Resoluciones 1440 px y 390 px: diálogos dentro del viewport y sin overflow horizontal. Capturas de evidencia guardadas como artefactos `ui-v20-capturas`. Backend y base productivos intactos.
- Estado final de la solicitud visual: frontend v20 publicado con pruebas exitosas. Autorización real de tres listados y preferencia sincronizada entre dispositivos **siguen pendientes de importar DDL23 y publicar backend preparado**. No afirmar que esos dos puntos ya están activos en el servidor.


### Hotfix INFO21 — cierre de popup «Información de conexión» (22-11-2026)

El botón `#infoClose` («Entendido») no cerraba `#infoDialog`: el único listener estaba en `assets/js/app.js` de demostración, que no se carga en producción. Se agregó manejo delegado en `assets/js/app-produccion.js`, que llama `dialog.close()` si está abierto sin tocar la sesión, el JWT ni la navegación. `index.html` cambia la versión del script a `app-produccion.js?v=20261122-info21` para evitar caché antigua.

- CI frontend: 46/46 pruebas Playwright aprobadas https://github.com/msanzana/rutahyd2front/actions/runs/44980983284. Nueva prueba reproduce apertura por icono de información y por botón «Más información», cierre por «Entendido», cierre por Escape y preservación de sesión.
- Publicación FTPS: https://github.com/msanzana/rutahyd2/actions/runs/44981109548, éxito con respaldo, cotejo byte a byte y protección del backend sin cambios.
- Validación navegador en sitio real: https://github.com/msanzana/rutahyd2/actions/runs/44981280562, éxito a 1440 px y 390 px, incluido abrir `#infoDialog` y cerrarlo con `#infoClose` sin disparar login. API interceptada y credenciales ficticias, sin escritura de datos.
- Sin cambios de MariaDB, Laravel ni DDL23.


## Mejora UI y búsquedas avanzadas Productos V22 — 22-11-2026

**Solicitud:** reemplazar aviso nativo de formularios («Completa este campo») por notificación corporativa estilizada y agregar al popup de búsqueda avanzada de Productos filtros por jerarquía completa (Departamento, Familia, Marca, Modelo, etc.), estado y demás atributos.

- Frontend `msanzana/rutahyd2front/main`, commit `fe9cd86b46592bfd94fd7c8f9109b371fbddf850`: nueva `assets/js/validacion-ui.js` para interceptar `invalid` de HTML5 y mostrar error accesible dentro del diálogo, resaltando campo inválido sin tooltip nativo; estilos nuevos incluidos en `assets/css/listados-tema-v20.css`, con modo oscuro y móviles.
- `assets/js/listados-ui.js`: modal de búsqueda avanzada con selectores jerárquicos dependientes, validación de rangos y manejo de errores de campos; evita duplicación de filtros genéricos del listado de Productos.
- `assets/js/productos-remotos.js`: popup de Producto con filtros para SKU, nombre, descripción, Rubro, Departamento, Familia, Línea, Marca, Modelo, Tipo de unidad, Tipo de envasado, Condición tributaria, Activo/Inactivo, código y tipo de código de barras, presentación, precio mínimo/máximo, IVA, unidades por envase mínimas/máximas e ID del proveedor. Se mantienen criterios al paginar. Clasificaciones cargadas de Laravel y selector padre/hijo; si algún catálogo no carga se informa sin bloquear el listado.
- `assets/js/catalogos-api.js`: consulta `GET /api/v1/productos` con parámetros validados y codificados (sin rutas de escritura); versionado anti-caché `20261122-filters22`.
- Backend aislado: `ProductoComercialControlador::listar` en rama `feature/proveedores-productos-barcodes-20261122`, fuente `e80574c9a9c9564317da16766682aa85f8663d83`; filtros SQL `where`/`whereExists` validados antes de la paginación (incluye código y tipo de código de barras en una misma fila), `filtros_aplicados=true` en la respuesta. No DDL y sin escrituras.
- CI backend en MySQL aislada `https://github.com/msanzana/rutahyd2/actions/runs/44981828295` **aprobada**. Publicación de **solo un controlador**, respaldo y preflight byte a byte `https://github.com/msanzana/rutahyd2/actions/runs/44982058267` **aprobada**; HTTP de estado 200 y endpoints filtrados 401 sin JWT. No se modificaron rutas, credenciales, permisos, SQL ni datos.
- CI frontend `https://github.com/msanzana/rutahyd2front/actions/runs/44982165926` **50 pruebas Playwright aprobadas**; incluye formulario Producto y error de rango estilizado, popup con todas las clasificaciones, cascadas, estado y precio, móvil 390 px.
- Publicación frontend FTPS `https://github.com/msanzana/rutahyd2/actions/runs/44982279574` **aprobada** con respaldo, transferencia de recursos primero y HTML al final, comparación byte a byte de `validacion-ui.js`, JS/CSS y protección de Laravel.
- Smoke Chromium de **sitio público real** `https://github.com/msanzana/rutahyd2/actions/runs/44982483824` **aprobada** en 1440 y 390 px: popup dentro del viewport sin overflow, selector Rubro→Departamento→Familia y Marca→Modelo, estado, rango de precios, aviso corporativo para rango inválido que desaparece tras corregirlo, envío de parámetros a API interceptada. Capturas en artefacto `popup-productos-v22`. No autenticó cuentas reales ni realizó escrituras productivas.
- **DDL23 y sincronización de tema/permisos independientes continúan pendientes** de importación y despliegue según diario anterior; este cambio no los modifica.


## Listas de precios sin triggers — decisión de arquitectura (22-11-2026)

El titular pidió reemplazar los triggers propuestos por **funciones de backend Laravel**. Implementación en rama `feature/proveedores-productos-barcodes-20261122`, todavía **NO desplegada ni importada a MariaDB de producción**.

- DDL nuevo `database/espanol/24_listas_precios_sin_triggers.sql`: tablas `listas_precios`, `listas_precios_detalle`, `clientes_listas_precios`, columnas `productos.costo_neto` y `lineas.minimo_margen_precio_venta`. Sin CREATE/DROP TRIGGER. Única lista base mediante índice UNIQUE sobre columna calculada; una lista por cliente mediante PRIMARY KEY(cliente_id). Semilla BASE y backfill que conserva los precios existentes desde la presentación; margen NULL si costo es cero.
- Servicio `app/Services/GestionListasPrecios.php`: `crearLista`, `actualizarLista`, `asignarListaBaseCliente`, `asegurarPrecioBaseProducto`, `sincronizarSkuProducto`, `asignarListaCliente`, `guardarPrecioLista`, `precioEfectivoCliente`. Cálculo costo+porcentaje o costo+precio, límite de margen de línea, sin fallback silencioso de lista especial a base.
- Integración transaccional de alta clientes en `EntidadesComercialesControlador` y alta/edición SKU de productos en `ProductoComercialControlador`. Si falla asignación de lista base, se revierte el alta; sin triggers.
- Pruebas de la rama en MySQL temporal `https://github.com/msanzana/rutahyd2/actions/runs/44985645158`: éxito (incluye pruebas de base, precios, margen, SKU y rol). Prueba adicional MariaDB 11 `https://github.com/msanzana/rutahyd2/actions/runs/44985604128`: éxito (0 triggers, lista base única, precio histórico conservado).
- Documento `docs/LISTAS_PRECIOS_LOGICA_SIN_TRIGGERS.md` en la rama explica instalación y puntos de integración. DDL24 debe aplicarse solo tras respaldo y verificación del esquema; el backend de precios NO se debe publicar antes.
- **Pendientes de la funcionalidad mayor:** panel CRUD de listas, refactor UI de Precios, selector lista especial en Clientes y conexión de `precioEfectivoCliente` con registro real de `ServicioVenta`. El requerimiento de esta conversación fue quitar triggers y trasladar lógica al backend; esa sustitución está lista y probada en rama.


## Tarifarios comerciales completos v25 sin triggers (22-11-2026)

**Declaración del titular:** importó los DDL requeridos en MariaDB y omitió intencionalmente todos los triggers; pidió reemplazarlos íntegramente por lógica Laravel/frontend y completar los mantenedores.

**Backend**: rama `feature/proveedores-productos-barcodes-20261122`. Servicios `GestionListasPrecios`, `ServicioVenta` y `ServicioNotaVenta`:
- Nuevo cliente -> asignar una sola LISTA BASE, con PK(cliente_id) que impide dos listas especiales simultáneas.
- Producto nuevo -> agregar fila de LISTA BASE, conservar precio histórico de presentación, controlar costos y SKU entre listas, todo en transacción Laravel, sin triggers ni procedimientos SQL.
- Precio neto = costo × (1 + margen / 100), margen = (precio − costo)/costo × 100 si costo > 0; si costo=0, conservar precio y dejar margen nulo. Laravel valida mínimo de `lineas.minimo_margen_precio_venta` al guardar, y rechaza aumentos de mínimos que invaliden los detalles activos.
- Una lista especial asignada al cliente reemplaza por completo la base; no hay fallback silencioso a BASE si el precio especial falta.
- Nuevo CRUD protegido `ListasPreciosControlador`; nueva API `ListasPreciosClientesControlador`: consulta/selección única para Clientes, cotización y catálogos activos seguros para vendedores (sin exponer costos). Las ventas y notas de venta usan precios efectivos, con validación backend de cada línea.
- CI MySQL temporal `https://github.com/msanzana/rutahyd2/actions/runs/44986961000` **éxito**, prueba de tarifas especiales en venta y rechazo de precio general; CI nuevos selectores `https://github.com/msanzana/rutahyd2/actions/runs/44987656432` **éxito**.
- Publicación backend `https://github.com/msanzana/rutahyd2/actions/runs/44987328292` **éxito** con preflight byte a byte de versión de partida, backup, sincronización de 10 archivos y rollback. Publicación complementaria de catálogos de ventas `https://github.com/msanzana/rutahyd2/actions/runs/44987782821` **éxito**. No se ejecutó DDL ni se modificaron datos de clientes/productos en estos despliegues; endpoints nuevos exigen JWT.

**Frontend**: repo `msanzana/rutahyd2front/main`, versión v25:
- Submenú oficial `Productos > Listas de precios`, CRUD con Lista Base protegida y especiales editables.
- Mantenedor Productos: `Presentación, precio e IVA` sustituido por sección `Precios`; campo Nombre de presentación retirado de UI (retención interna para compatibilidad), unidades por envase y costo neto fuera; tabla por lista con activo, costo, margen y precio, con cálculo inmediato; base obligatoria; selector para agregar especiales; mínimo de Línea solo lectura.
- Mantenedor Líneas: nuevo margen mínimo editable y validado.
- Mantenedor Clientes: nuevas altas usan BASE, al editar se asigna una sola lista especial; listado muestra nombre de tarifa.
- Ventas: panel para escoger cliente, buscar productos con precio vigente en su lista y consultar tarifa con API, registrar operación contra Laravel; el cliente no puede imponer otro precio. Vendedores no necesitan permisos de edición de Productos/Clientes para seleccionar, y solo los perfiles con ventas.gestionar registran ventas.
- CI Playwright `https://github.com/msanzana/rutahyd2front/actions/runs/44987984534`: **53 pruebas aprobadas**, incluyendo precios/márgenes en producto, CRUD listas, asignación cliente y ventas.
- Workflow FTPS de frontend `https://github.com/msanzana/rutahyd2/actions/runs/44988125009`: iniciado, estado por cerrar cuando termine la verificación.

**Limitaciones honestas:** no se realizó una venta real de prueba en MariaDB productiva (no crear datos sin consentimiento); se usó MySQL temporal, sesión JWT ficticia y navegador con API interceptada. No se confirmó manualmente el esquema productivo mediante phpMyAdmin, solo la declaración de que el titular importó DDL. La ilustración realista de ventas en ruta para login se generó como recurso visual separado, aún no integrada como archivo publicado.


### Cierre de publicación de precios v25 en InfinityFree — 22-11-2026
- Frontend publicado con backup, HTML al final y comprobación de bytes: https://github.com/msanzana/rutahyd2/actions/runs/44988125009 **éxito**.
- Navegador Chromium contra el sitio real en 1440 y 390 px: https://github.com/msanzana/rutahyd2/actions/runs/44988349225 **éxito**. Verifica sección Precios sin presentación antigua, costo editable, caja responsiva sin overflow, selector cliente/producto con tarifa Mayoristas y precio efectivo. Capturas artifact `capturas-precios-v25`.
- Este smoke usa JWT ficticio e intercepta la API, no confirma datos privados de MariaDB real. Los endpoints auténticos de precios, clientes vendedores y productos vendedores se verificaron por 401 sin JWT. No se ejecutaron ventas de prueba ni nuevos SQL.
- La fotografía realista para login fue generada como imagen independiente, pero **no se integró todavía en los archivos de frontend publicados**. No confundir imagen creada con pantalla actualizada.
