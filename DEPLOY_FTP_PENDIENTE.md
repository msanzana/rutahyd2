# RutaH&D2 — preparación de transferencia FTP
**Estado: NO transferido / NO publicado.**
Hosting: InfinityFree `ftp.infinityfree.com:21` (FTPS explícito), usuario `if0_43460400`, directorio web `/htdocs`.
La aplicación requiere `vendor/` y PHP >=8.3. Aún NO se verificó la versión PHP del hosting ni se generó `vendor/` en el despliegue.
En InfinityFree gratuito no hay SSH, Composer remoto, cron ni workers. La carpeta web es `htdocs`; NUNCA publicar `.env`, `database/sql`, código fuente privado, ni `storage` en una ruta web sin bloqueo de acceso.
**No habilitar GitHub Actions FTP hasta tener una estrategia probada de document root, auditoría de exposición, SSL y manejo de APP_KEY y DB_PASSWORD como secretos.** Un workflow con FTP a producción mal orientado puede exponer credenciales.
Se recomienda Oracle Cloud con SSH para backend Laravel. Para InfinityFree, solo habilitar transferencia tras prueba aislada de PHP y montaje seguro con compilación local/CI.
