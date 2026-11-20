# Ruta H&D2 — Auditoría de recuperación de habilitaciones
Fecha: 2026-11-20. Registro forense consolidado, no procedimiento de despliegue.

## Identidades de las dos bases
- **V2 español**: `if0_43460400_rutahyd2_es`, documentada como creada e importada por phpMyAdmin el 19 de noviembre: 34 tablas, semillas 5 roles + 2 bodegas + 12 permisos. Fuente: [ESTADO_DESPLIEGUE_BD_ES](../docs/ESTADO_DESPLIEGUE_BD_ES_2026-11-19.md). Esta evidencia es histórica, NO una nueva consulta SQL directa.
- **Original en inglés**: `if0_43460400_rutahyd2`, documentada como preservada sin migración ni borrado. No confundir ambas bases.
- **Conector BlazeSQL disponible en esta sesión**: corresponde a `dte-sii / db_sii_dte`, **otro proyecto**; no utilizarlo para Ruta H&D2.

## Instalación real del backend
- [Workflow 44646889205](https://github.com/msanzana/rutahyd2/actions/runs/44646889205) terminó **success**, y el log confirma la transferencia por FTPS de backend y vendor a `/htdocs/api-v2` con protección previa y posterior de `.htaccess`, sin `.env` y sin activar API.
- El workflow tomó el backend de `feature/backend-v2-es` commit `6cea5ebd7740759ebbccbce090d1eb2686b61c2b`, según su log. **No** desplegó los últimos cambios SQL/API.
- [Auditoría 44662383155](https://github.com/msanzana/rutahyd2/actions/runs/44662383155) y [nueva auditoría 44678417961](https://github.com/msanzana/rutahyd2/actions/runs/44678417961) confirmaron por FTPS 34 modelos PHP, `vendor/autoload.php`, `public/index.php`, `.htaccess` de denegación y ausencia de `.env` en raíz inspeccionada.
- El diagnóstico HTTP de la última auditoría devolvió `000`, lo cual significa **sin respuesta HTTP utilizable**, y no permite inferir código 403/404 o estado del proceso Laravel.

## Acceso FTP habilitado
- Las ejecuciones anteriores fallaron por ausencia de `INFINITYFREE_FTP_PASSWORD`; después se configuró la credencial en GitHub Actions y el workflow FTPS terminó **success**. No registrar ni compartir el valor del secreto.
- [Workflow frontend 44668449078](https://github.com/msanzana/rutahyd2/actions/runs/44668449078) terminó success con comparación de 8 archivos por FTPS en sitio principal y `violeta-demo`. La UI publicada es la demostración con catálogos `localStorage`.

## Prueba SQL: separar local de real
- [Run 44650223401](https://github.com/msanzana/rutahyd2/actions/runs/44650223401): MySQL **efímero de CI**, base `rutahyd2_es_local`, 34 tablas, 5 roles, 12 permisos, conexión/select/desconexión PDO correctos. **No** se conectó a InfinityFree.
- No existe aquí acceso conectado a la base `if0_43460400_rutahyd2_es` para revisar datos en vivo; la evidencia de instalación es el documento anterior de phpMyAdmin.
- `database/espanol/12_tokens_acceso_opcional.sql`: no se confirmó ejecución en producción; tampoco alta segura del administrador ni `.env`.

## Clasificación comercial y cronología
- `database/espanol/13_catalogos_comerciales_aditivos.sql` y `14_productos_clasificacion_campos.sql` entraron en commit `532ac321ffa5` a las 03:24 UTC del 20 de noviembre, **después** de la transferencia del backend 6cea5ebd; no constan ejecutados en MySQL real.
- El frontend violeta con diez CRUD y jerarquías pasó pruebas y fue publicado después (run 44668449078); pero `assets/js/catalogos.js` persiste en `localStorage`.
- [PR backend #4](https://github.com/msanzana/rutahyd2/pull/4): controladores CRUD autenticados y clasificación de productos, solo código en rama.
- [PR frontend #1](https://github.com/msanzana/rutahyd2front/pull/1): cliente API y administración MySQL bajo login, solo código en rama; **no publicado** ni conectado a una API productiva.

## Conclusión operativa
**No volver a crear las 34 tablas españolas ni subir indiscriminadamente el backend viejo.** Conservar ambas bases y la versión estática actual.
La próxima tarea es verificar mediante canal autenticado el estado **actual** de `if0_43460400_rutahyd2_es` (respaldo, tablas, usuarios, tokens, columnas 13/14), comprobar viabilidad del hospedaje Laravel protegido y solo después migrar y desplegar de modo reversible.
Nunca usar la base DTE para estas operaciones. No dar por ejecutadas las SQL 13/14 por existir en GitHub.
