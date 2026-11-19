# Instalación segura en InfinityFree

Esta carpeta se usa en una nueva base vacía, diferente a `if0_43460400_rutahyd2`.

1. Crear la base nueva en el panel InfinityFree.
2. Seleccionar exclusivamente esa nueva base en phpMyAdmin.
3. Ejecutar archivos `01_estructura.sql` a `04_estructura.sql` secuencialmente; no ejecutar dos veces sin revisar.
4. Ejecutar opcionalmente `05_semillas.sql` (roles/bodegas/permisos de configuración).
5. Verificar 34 tablas + 19 registros de configuración.

`00_ddl_completo_sin_create_database.sql` equivale a todos los bloques de estructura juntos. No ejecutarlo además de los bloques.

**No utilizar los scripts de renombrado sobre la base original sin respaldo verificado.**
