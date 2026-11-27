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
