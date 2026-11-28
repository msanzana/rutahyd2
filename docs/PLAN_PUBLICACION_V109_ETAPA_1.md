# Ruta H&D — publicación V109, etapa 1

Estado: candidato empaquetado y validaciones previas exitosas. **No publicado, no autorizado para instalación directa sin comprobaciones productivas.**

## Versiones fijadas
- Última publicación visual confirmada: V108D, run 45885877592.
- Backend productivo de referencia V108A: run 45840030652, commit de workflow de78f242f5b339897563f17b48ff2b92c2ea8c53.
- Frontend productivo de referencia: b91579217d339ce7916d5257c655a17de40b1873.
- Paquete V109D6C: run 45927132714, artefacto 15166647801, SHA256 del ZIP bb3180722da670a07ab3501e43b53aeca9efac8a0741f206eb98a8a8679f8f26. Expira 2026-12-27.
- Backend candidato fijado: 41105da2a03b436336e0f71ff1aed5b4529f40e1.
- Frontend candidato fijado: ee55aa4fec4d8f6a3fed7c17d2110a6c6925e9d4.

## Alcance y guardas
Usar solamente los 24 PHP y 5 archivos frontend del manifiesto SHA256SUMS del artefacto. DDL49 ya aplicado; no repetir. No tocar .env, credenciales, vendor, base de datos ni documentos históricos. No fusionar ramas completas: backend diverge de main y frontend acumula cambios previos. ContextoCompras y OrdenesCompraControlador coinciden byte a byte entre las referencias GitHub del paquete y V108A; aún falta cotejar bytes del servidor.

Conservar config/conteos.php escrituras=false, alcance_selectivo_verificado=false, conteo_ciego_verificado=false, estados finales null. Conservar config/traspasos.php escrituras=false y pares_recinto_verificados vacío. Excluir V109E/F/G posteriores. No habilitar segunda sucursal.

## Secuencia de publicación autorizada
1. Obtener respaldo real de cada archivo afectado en InfinityFree y registrar archivos nuevos/ausentes. Cotejar hashes de archivos existentes contra referencia y verificar DDL49/atribución histórica mediante consultas permitidas, sin INFORMATION_SCHEMA. Si existe diferencia o dependencia ausente, detener.
2. Validar paquete, dependencias de rutas, sintaxis y permisos; no asumir que una prueba de un commit posterior valida esta entrega exacta.
3. Publicar backend primero, rutas al final de ese conjunto, después frontend y referencias de caché. Usar el canal de publicación autorizado del proyecto y rollback integral, sin reejecutar workflows antiguos de otras versiones.
4. Comprobar disponibilidad API, JWT no autenticado, selector y sesión de sucursal, consultas de compras, listado Kardex, listados de ajustes/traspasos/conteos y guardas de escritura. No generar movimientos reales de prueba. Realizar comprobación visual autenticada de menús y documentos.
5. Ante fallo, restaurar únicamente desde respaldo real y retirar archivos nuevos. Verificar recuperación. Registrar run, hashes, resultado y alcance efectivamente publicado.

## Evidencia
- Recepciones V108B2A: 45888640761 SUCCESS.
- Consolidaciones V108B2B: 45888966773 SUCCESS.
- Paquete V109A: 45887984339 SUCCESS.
- Paquete V109D6C: 45927132714 SUCCESS; empaquetado, lint, checksums y guardas, no despliegue.
- Regresiones posteriores: ajustes 45944878970, traspasos 45944878881, conteos 45944878790 SUCCESS; no sustituyen pruebas específicas de nuevos módulos.

Bloqueo: la creación de workflows de publicación/validación fue restringida anteriormente y no se eludirá. No existe ejecución de publicación V109 acreditada en esta revisión. Este documento es un plan operativo, no un mecanismo alternativo de publicación ni permiso para instalar sin las guardas.

## Preflight de referencias GitHub — 27 de noviembre de 2026

- Comparación estática de routes/api.php: referencia V108A 133 declaraciones Route::get/post/put/patch/delete, candidato 167; 34 nuevas declaraciones, ninguna línea anterior eliminada o modificada. No equivale a route:list productivo. Las 9 escrituras de conteo están condicionadas a AutorizarConteos; escrituras de traspaso protegidas por AutorizarTraspasoInterno. Configuraciones cerradas y controles frontend respetan escrituras_habilitadas.
- Dependencias críticas con blob SHA idéntico entre candidato y referencia: ContextoCompras f91a4051ae911692175539f45c94b1d5d1530d95, CostoPromedioPonderado 887a07dfd79217df9103b0cba54fc697e5515971, LibroComprasInterno d78b3f1100b0aae1a18195dcc2625b740aee176d, AutorizarPermiso f244bd55a9a1d517a4755113eb1b703c9ae27fdb y bootstrap/app.php 5d7e1150a7b4a0cdb611af4e6575c37bc1a07f2a. composer.json idéntico; composer.lock no se obtuvo (404), dependencias instaladas no acreditadas. Inspección masiva de 24 archivos alcanzó límite de llamadas; no afirmar auditoría exhaustiva.
- Cinco archivos frontend comparados con referencia V108D: app-produccion.js b0e72676a4c19f719db0e006699d82135e508f88, catalogos-api.js 4f2a0f2977125590217cf8e15f99c824df6018cd, compras-consultas-v66.js 82cfc0af3027c81fd76d70e6f50ad72a827a4757, inventario-v104.js 10fa8e7a95d4a284ef37b6bf94be9b8251e69348, index.html e450e4600d4362322186485d6d211b332b5f21d5. Referencias cache de JS V109D5, V108B4 y V109D4A presentes.
- Resultado: preflight parcial de repositorio conforme en comprobaciones ejecutadas; publicación NO ejecutada. Falta canal autorizado de publicación, respaldo real, contraste hashes servidor, dependencias instaladas, atribución y pruebas HTTP productivas. No confundir artefacto con despliegue ni usar workflows antiguos para forzar publicación.

## Actualización de continuidad — 28 de noviembre de 2026 (PUB01–PUB05)
El canal manual autorizado por el titular ya está operativo; la restricción anterior de edición directa de workflows se respeta mediante editor GitHub y Run workflow en main. El preflight FTPS y respaldo cifrado sí se han ejecutado, actualizando el estado anterior del plan. V109D6C sigue NO publicado.
- PUB01 46097659316 y PUB02 46098103271 SUCCESS; PUB03 46116180452 SUCCESS.
- PUB04 46117050395 bloqueó dos diferencias (routes/api.php e index.html) tras respaldo correcto. PUB04A 46118606576 confirmó coincidencia histórica exacta, merge textual backend limpio y conflicto HTML.
- PUB04B 46118938057 SUCCESS, commit 049512b676f6cd97764554a58131cb41d49919b7, 29 candidatos conformes o reconciliados, cero bloqueos; respaldo 15251964585, sin escritura FTPS. Rutas integradas SHA256 5a9fe4d01d929ef67e8e3117e6582abf3322b81c9b5b19c1fcc32c6217201780; HTML integrado SHA256 9d6dcf58387520070a93216d385b7b23470325625cfbcbb38d65f914fc71ebe5. No sustituir por versiones crudas.
- PUB05 preparado como simulación de transferencia y dependencias; a esta revisión no existe ejecución acreditada. PUB06 (verificación y rollback real) y PUB07 (primera publicación) pendientes.
- Antes de publicar: verificar dependencias productivas y autoload, DDL49/atribución sin repetir DDL, recuperación con clave privada, guardas de escritura, cotejo inmediato de producción, HTTP/JWT y aceptación autenticada. No habilitar segunda sucursal ni V109E/F/G.
- Evidencia, accesos permanentes y conceptos: [FUENTE_CONOCIMIENTO_PUBLICADOR_SEMIAUTOMATICO.md](FUENTE_CONOCIMIENTO_PUBLICADOR_SEMIAUTOMATICO.md).

- Actualización PUB05: run 46119415230 SUCCESS, commit d5d1c41d6e2fb7846f3457d62a81dcbf89216f50; siete dependencias productivas cotejadas, autoload presente (no runtime), 29 archivos y 29 restauraciones simuladas en memoria, cero bloqueos; respaldo 15252996403. No publicado. PUB06/PUB07 pendientes.

- PUB06A run 46119870191 SUCCESS (commit 61d7915c20e74f80d9761c58df3536c4e1ac3efc): 29 transferencias FTPS y 29 restauraciones exitosas en servidor aislado, cero bloqueos; respaldo 15253720642. Sin escritura real. PUB06B verificador local preparado y probado con datos sintéticos; falta descifrar respaldo real con clave RSA original en custodia del titular. No compartir clave ni contenidos. DDL49/atribución, runtime y verificación/rollback productivos siguen pendientes; PUB07 no autorizado hasta resolver guardas.

- Actualización: PUB06B-R1: recibida clave pública del titular, validada como SPKI RSA4096 (sin clave privada), SHA256 DER 42a6f13d14c18b848a1648bfca08cf1d708abaea26037fd20624c0a9c8090a6b. Incorporada como deployment/keys/rutahd-respaldo-public-20261128.pem, commit 31e6c63b18dba39599853bc315e89879c06d0bb5, blob 63b6e799a5d99fdc504a88e0e1a10d0fb1c076ca; lectura posterior confirmó bytes exactos. Clave pública histórica intacta. Preparado rutahd-publicacion-PUB06B-R1.zip sobre YAML PUB06A (blob b09c5ec6f6266ad2511098222f65b963897a373b), fijando commit de clave, RSA4096 y huella antes de FTPS, metadatos y nombre de artefacto rutahd-pub06b-r1-V109D6C-<run>. Se conservan guardas y ensayos previos; InfinityFree solo lectura. Validación local YAML, 8 scripts Bash, 4 Python, encapsulado OAEP con pública real y descifrado sintético con clave de prueba exitosos; no se dispone de privada real. Pendiente titular instala YAML manualmente y ejecuta, obtener nuevo respaldo y verificar localmente con nueva privada y run_id nuevo. No confundir con respaldo anterior ni publicar aún; DDL49/atribución y runtime pendientes.

- Actualización: PUB06B-R1 ejecutado y verificado: run 46124730824 SUCCESS en todos los pasos, commit 520c65829f091a80380f29517809ee4b5f55b55f, job 140916720176. Logs confirman PUB06B_R1_CLAVE_PUBLICA_OK SHA256=42a6f13d14c18b848a1648bfca08cf1d708abaea26037fd20624c0a9c8090a6b; 29 archivos y 29 rollback en memoria, 29 restauraciones FTPS aisladas, cero divergencias. Nuevo respaldo cifrado artefacto 15254639390: https://github.com/msanzana/rutahyd2/actions/runs/46124730824/artifacts/15254639390. Sin escritura en InfinityFree ni DDL. Pendiente descifrado local con verificador R1, privada nueva y run_id 46124730824; no solicitar privada ni frase. Pendientes DDL49/atribución y runtime productivo antes de autorizar publicación.

- Actualización: PUB06B recuperación real del respaldo completada según salida local aportada por titular el 28-11-2026: PUB06B_RECUPERACION_OK, run_id=46124730824, zip_sha256=12120ca85c1a0dc7f5afaeeffeabf95495faa94f1c2857387d779548d66fece5, existentes=12 ausentes=17 protecciones=2. Clave correspondiente, AES-GCM autenticado, ZIP y hashes verificados, sin extracción ni envío de datos. Evidencia local del titular (no ejecución independiente del asistente); cierra custodia/recuperabilidad del respaldo, no acredita restauración productiva FTPS. Siguiente PUB06C: comprobar estructura de DDL49 ya aplicado mediante SHOW CREATE TABLE y estados de conteos, y conteos agregados de atribución por sucursal/bodega en compras, recepciones, consolidaciones, Kardex y stock, sin INFORMATION_SCHEMA ni escrituras. Consultas fundamentadas en candidato fijado 41105da2a03b436336e0f71ff1aed5b4529f40e1 (DDL49, ConteosInventarioControlador, ComprasConsultasControlador, InventarioOperacionesControlador). Pendientes runtime y verificación/rollback productivo; no habilitar escrituras cerradas ni publicar antes de guardas.
