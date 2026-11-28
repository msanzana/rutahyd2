# Ruta H&D V2 — Fuente de conocimiento: publicador semiautomático

Actualización: 28-11-2026. Fuente de continuidad técnica; no constituye autorización de publicación.

## Accesos permanentes
- Editar YAML en main: https://github.com/msanzana/rutahyd2/edit/main/.github/workflows/rutahd-publicacion.yml
- Ejecutar manualmente: https://github.com/msanzana/rutahyd2/actions/workflows/rutahd-publicacion.yml
- Historial: https://github.com/msanzana/rutahyd2/actions
- Plan de alcance V109: [PLAN_PUBLICACION_V109_ETAPA_1.md](PLAN_PUBLICACION_V109_ETAPA_1.md)
- [Reglas operativas](REGLAS_OPERATIVAS_RUTA_HD_V2.md) y [diario](DIARIO_CONTINUIDAD_2026-11-21.md).

## Diseño y conceptos
El titular guarda el YAML mediante el editor de GitHub y dispara Run workflow en main; el asistente prepara cambios y revisa resultados reales. No eludir restricciones de permisos de workflows. Reutilizar un workflow central y los cuatro parámetros version, run_id, artifact_id, sha256. No asumir agotamiento de minutos de Actions sin evidencia. El runner Ubuntu dispone de Python; el equipo Windows del titular reportó PHP 8.4.22 y Node v26.13.1, sin Python accesible. No depender de Python local sin verificar.
- Validación: origen de ejecución exitosa, pertenencia y nombre del artefacto, vigencia, SHA256 ZIP, rutas permitidas, manifiesto exacto, lint PHP/JS sin ejecutar candidatos; excluir credenciales, vendor, datos, DDL y rutas ajenas.
- Preflight: FTPS explícito con TLS, certificado verificado y canal de datos protegido. Leer únicamente archivos previstos; error de listado o lectura no demuestra ausencia. Comparar hashes de bytes reales y conservar guardas del API.
- Respaldo: originales reales y registro de ausencias, no una referencia GitHub. ZIP cifrado AES-256-GCM, clave de sesión encapsulada RSA-OAEP-SHA256, con clave pública fijada en repositorio. No almacenar archivos productivos nuevos en claro ni registrar secretos. La recuperación con la clave privada correspondiente todavía no está acreditada.
- Reconciliación: una divergencia bloquea por defecto. Admitir solo transformaciones verificadas contra históricos exactos, revisar delta y conservar modificaciones productivas ajenas. Un merge textual limpio no acredita por sí solo semántica, permisos ni funcionamiento.
- Publicación futura: respaldo durable antes de escribir, recotejo de producción inmediatamente antes, backend (rutas al final), luego frontend (HTML al final). Verificación de bytes, HTTP/JWT, visual autenticada y rollback integral ante fallo; restaurar originales y retirar archivos nuevos intentados. No hacer movimientos de inventario de prueba ni DDL automático.

## Evidencia comprobada
| Etapa | Run | Resultado y alcance |
|---|---|---|
| PUB01 | 46097659316 | SUCCESS: ejecución manual y entorno |
| PUB02 | 46098103271 | SUCCESS: descarga y manifiesto |
| PUB03 | 46116180452 | SUCCESS: validación parametrizada, commit a072375019a07f03e65bddac3c32b101da0707cf |
| PUB04 | 46117050395 | FAILURE previsto: FTPS y respaldo correctos, 2 divergencias (routes/api.php, index.html); artefacto cifrado 15252262376 |
| PUB04A | 46118606576 | FAILURE previsto: ambos archivos iguales a históricos; rutas merge limpio, HTML conflicto; respaldo 15252951226 |
| PUB04B | 46118938057 | SUCCESS: reconciliación 2/2, 29/29 conformes o reconciliados, cero bloqueos; commit 049512b676f6cd97764554a58131cb41d49919b7, respaldo cifrado 15251964585 |
| PUB05 | Sin ejecución verificada | Entregado ZIP/YAML de simulación al titular; no confirmar instalación ni prueba hasta evidencia GitHub |
| PUB06 / PUB07 | Pendiente | Verificación/rollback real y primera publicación V109D6C |

Todos los runs se consultan en https://github.com/msanzana/rutahyd2/actions/runs/ID. Hasta PUB04B no hubo subida FTPS ni cambios de base de datos.

## Referencia exacta de reconciliación V109D6C
Artefacto candidato 15166647801 del run 45927132714, SHA256 ZIP bb3180722da670a07ab3501e43b53aeca9efac8a0741f206eb98a8a8679f8f26; vence 27-12-2026. 24 PHP + 5 frontend. Históricos V108A 45840030652/15127651143 y V108D 45885877592/15149122687.
- routes/api.php productivo SHA256 02e1a36575bab308d19501f183337a7e856f5a9455de41845b7a4d0e1aacba05 (coincide con ambos históricos). Integrado SHA256 5a9fe4d01d929ef67e8e3117e6582abf3322b81c9b5b19c1fcc32c6217201780, 33159 bytes. Delta candidato: 34 declaraciones nuevas, preservando rutas productivas.
- index.html productivo SHA256 847ca1adcc443505d6855e85f58b84b1ecd0f1fc8c1286b94c79c46906d7d508 (histórico V108D reconstruido). Integrado SHA256 9d6dcf58387520070a93216d385b7b23470325625cfbcbb38d65f914fc71ebe5, 29838 bytes. Mantener HTML real, añadir Ajustes generales, Traspasos, Conteos de inventario; actualizar cuatro referencias JS (catalogos-api, compras-consultas-v66, inventario-v104, app-produccion). No reemplazar por HTML crudo de repositorio.
- Si cambian los hashes productivos o el paquete, no reutilizar excepciones automáticamente: detener y diagnosticar. Estas huellas acreditan vista previa reconciliada, no despliegue.

## Pendientes bloqueantes
Verificar dependencias reales de Laravel (incluido autoload instalado y middleware/rutas), DDL49 ya ejecutado sin repetir y atribución histórica con consultas de solo lectura permitidas; comprobar disponibilidad de clave privada y restauración; implementar y probar verificación y rollback antes de habilitar subida real. Conservar flags cerrados de conteos y traspasos; excluir V109E/F/G y segunda sucursal. No afirmar publicación productiva V109 hasta éxito de la ejecución real y pruebas previstas. Mantener continuidad de módulos posteriores en diario.

## Mantenimiento de conocimiento
Por instrucción del titular del 28-11-2026: actualizar diario cronológico, reglas operativas y fuentes técnicas al cerrar cada avance, corrección o decisión relevante. Registrar evidencia, commit/run, alcance real, estado, restricciones, decisiones no validadas y pendientes. No duplicar pruebas cerradas. Incluir los accesos permanentes de edición y ejecución al entregar YAML nuevo.

## Actualización PUB05 — 28-11-2026
Run 46119415230 SUCCESS, commit d5d1c41d6e2fb7846f3457d62a81dcbf89216f50. 29 archivos planificados, 29 fallos inyectados restaurados en memoria, cero bloqueos. Siete dependencias de código coinciden byte a byte mediante Git blob; vendor/autoload.php comprobado solo presencia, no ejecución runtime. Respaldo cifrado https://github.com/msanzana/rutahyd2/actions/runs/46119415230/artifacts/15252996403. Sustituye el estado pendiente PUB05 anterior; no hubo escritura productiva. Mantener pendientes de PUB06/PUB07 y no afirmar restauración real a partir de simulación.

## PUB06A preparado
Ensayo de transporte TLS real pero aislado en 127.0.0.1 del runner mediante servidor efímero, credenciales independientes, certificado verificado y guardas de escritura por socket loopback. Verificación de bytes, rollback con nueva conexión tras fallos parciales, comprobación de restauración y borrado de nuevos. Producción sigue solo lectura. Validación estática local realizada; prueba FTPS integrada aún pendiente de ejecución GitHub. No sustituye prueba de recuperación con clave privada, DDL49/atribución, runtime y verificación productiva.

## Resultado PUB06A y preparación PUB06B
Run 46119870191 SUCCESS, commit 61d7915c20e74f80d9761c58df3536c4e1ac3efc. Logs confirman 29 transferencias y 29 restauraciones FTPS aisladas, cero divergencias, siete dependencias cotejadas, respaldo cifrado https://github.com/msanzana/rutahyd2/actions/runs/46119870191/artifacts/15253720642. No hubo escritura productiva. PUB06B: verificador local Node.js para recuperar en memoria el ZIP cifrado y cotejar huella de clave pública, autenticación AES-GCM, SHA256 ZIP y cada archivo/inventario. Validado con respaldo sintético y rechazo de hash alterado; falta prueba real con clave privada original del titular. No transmitir clave ni contenido descifrado, compartir solo estado, run, hash ZIP y conteos. No confundir esta recuperación con rollback productivo. Si clave no disponible, renovar custodia y respaldo antes de publicar; pendientes runtime, DDL49/atribución, verificaciones productivas y PUB07.
