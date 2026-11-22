# Ruta H&D V2 — Recuperación de acceso y desafío de InfinityFree

Fecha de diagnóstico: 22-11-2026.

## Causa comprobada

El enlace antiguo incluía un secreto temporal de 64 caracteres en el fragmento `/#recuperar?token=...`. El desafío de navegador de InfinityFree realiza una redirección `?i=1` **antes de cargar el frontend** y elimina ese fragmento. Por eso la página de cambio de contraseña desaparecía y se abría el login incluso después de corregir la condición de carrera dentro de la SPA.

La auditoría de URLs con un navegador nuevo y datos sintéticos comprobó:

- Formato antiguo `/#recuperar?token=[FICTICIO]`: termina en `/?i=1` y pierde el fragmento.
- Formato nuevo `/?recuperar=[FICTICIO]`: termina conservando el parámetro `recuperar` y añadiendo `&i=1`.
- Rutas inexistentes `/recuperar/` sin desplegar: el hosting las devuelve a `/errors/404/`.

Evidencia: https://github.com/msanzana/rutahyd2/actions/runs/44966085112

## Solución versionada

- En el controlador de Laravel `RecuperacionContrasenaControlador::enviarEnlace`, los NUEVOS enlaces usan `/?recuperar=<token>` y mantienen caducidad de 30 minutos, longitud aleatoria de 256 bits, hash SHA-256 en base, unicidad por usuario y consumo único.
- El frontend reconoce tanto la forma nueva por `query` como la antigua por `fragment`, toma el token exclusivamente en memoria y elimina el token de la barra de direcciones mediante `history.replaceState` antes de abrir el formulario.
- La portada lleva `<meta name="referrer" content="no-referrer">`. No hay enlaces de terceros en el formulario; no almacenar el token en localStorage ni registrar tokens en logs de CI.
- Se mantiene la pantalla de recuperación separada del login mientras está activa, con estética violeta H&D, indicación del paso, mostrar/ocultar contraseña, validación de al menos 12 caracteres y visualización responsive.
- La cadena frontend → API pública Laravel → MariaDB sigue en modo producción, sin demostraciones.
- El despliegue del frontend precede obligatoriamente al cambio del controlador; ambos cuentan con respaldo y reversión por FTPS, sin modificaciones DDL/DML ni de .env, SMTP o JWT.

## Seguridad y limitaciones

El formato de query hace que el token llegue al servidor web y potencialmente a los registros de acceso del hosting. Se mitiga con TTL corto, un solo uso, eliminación inmediata de la URL visible y `Referrer-Policy: no-referrer`; no debe compartirse el enlace ni integrarse analítica de terceros. Para un hosting que permita controlar totalmente redirecciones y cabeceras, se recomienda recuperar el formato de fragmento o implementar una ruta de un solo uso más apropiada. Esta solución es una adaptación específica a InfinityFree.

Los enlaces anteriores enviados por correo siguen siendo del formato fragmento; **es indispensable solicitar un nuevo correo** después de publicar el cambio del controlador. No basta con volver a abrir un mensaje antiguo.

## Pruebas y cierre

- CI del frontend (JWT, recuperación móvil, enlaces antiguos y nuevos, sin demo): revisar https://github.com/msanzana/rutahyd2front/actions
- **Publicación frontend APROBADA:** https://github.com/msanzana/rutahyd2/actions/runs/44966479180
- **Publicación backend APROBADA:** https://github.com/msanzana/rutahyd2/actions/runs/44966773942 . Primera ejecución FTPS rename falló y se revirtió: https://github.com/msanzana/rutahyd2/actions/runs/44966567780
- **Smoke externo primer clic en navegador sin cookies APROBADO:** https://github.com/msanzana/rutahyd2/actions/runs/44966681348 . Formulario visible; login oculto; token borrado de la URL; diseño grid y meta no-referrer presentes.
- **La contraseña del usuario no ha sido cambiada desde estos workflows**. La validación de recuperación REAL corresponde al usuario por un nuevo correo de la aplicación; nunca revelar la contraseña.

Regla del proyecto: **no Firecrawl**, y cambios estructurales únicamente con SQL **DDL**.
