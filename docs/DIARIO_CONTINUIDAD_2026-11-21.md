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
