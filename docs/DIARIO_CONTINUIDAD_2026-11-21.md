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
