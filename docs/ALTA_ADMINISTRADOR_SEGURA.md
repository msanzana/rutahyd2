# Alta inicial de administrador Ruta H&D V2

Este procedimiento es **productivo**, no es una demostración. Debe ejecutarse únicamente contra la base española de Ruta H&D, nunca contra una base DTE distinta.

## Comprobaciones antes de ejecutar

1. Exportar y verificar un respaldo íntegro de la base fuera de InfinityFree.
2. Confirmar con acceso privado que la base seleccionada es `if0_43460400_rutahyd2_es`; verificar que `usuarios`, `roles`, `tokens_acceso` y `restablecimientos_contrasena` existen.
3. Verificar que SMTP real está configurado, puede entregar correos y el remitente está autorizado.
4. Importar `database/espanol/18_recuperacion_contrasenas.sql` si aún falta, e importar `database/espanol/19_rut_usuarios.sql` si aún no existe la columna `rut`.
5. Verificar el rol `administrador` y ausencia de duplicados por correo y RUT.

## Aprovisionamiento seguro

En consola privada del backend, ejecutar el comando `php artisan rutahd:crear-administrador` con el correo del titular y las opciones `--nombre` y `--rut`, usando los datos recibidos mediante un canal privado. **No colocar nombres, correo, RUT ni contraseñas en commits, scripts públicos ni archivos de registro.**

El comando verifica el RUT chileno, evita duplicados y genera una contraseña inicial aleatoria cuyo valor no se conserva ni se entrega. Crea la cuenta y envía mediante SMTP un **enlace de un solo uso, válido por 30 minutos**, para que el titular defina su contraseña. Si falla el envío, la operación se revierte.

El correo debe recibirse y el titular debe establecer su clave. Confirmar un login JWT real con el perfil administrador y consultar `/api/v1/perfil` y permisos de Productos, Usuarios y módulos comerciales. Solo después considerar la activación de `rutahd2_api_ready`.

## Estado de esta preparación

Se preparó la migración y el comando en una rama, **no se importó el SQL ni se creó el usuario**. El conector SQL disponible para este chat apunta a una base DTE distinta y es de solo lectura. No confundir esa conexión con Ruta H&D. No enviar contraseñas de acceso por email ordinario ni confirmar cuentas inexistentes.
