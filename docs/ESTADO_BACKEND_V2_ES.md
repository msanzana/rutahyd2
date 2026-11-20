# Situación del backend Laravel RutaH&D2 V2

- Rama: `feature/backend-v2-es`.
- Fuente SQL: `database/espanol/01_esquema_completo_34_tablas_es.sql` (34 tablas).
- Mapeo Repository: 34 modelos, 34 contratos, 34 implementaciones.
- Se añade tabla técnica `tokens_acceso` (35ª tabla) **solo para autenticación**, mediante `database/espanol/12_tokens_acceso_opcional.sql` o migración Laravel en local.
- Autenticación propia de token con hash en base de datos, sin Sanctum (evita convenciones SQL inglesas de vendor).
- Servicios: ventas, notas, pagos, traslado stock y payload DTE.
- DTE: **preparación de JSON sin envío** al servidor externo ni al SII.
- Despliegue FTP: **NO confirmado**. Solo publicar tras comprobar redirecciones .htaccess, vendor, PHP, seguridad y credenciales.
- DB española existente en InfinityFree: `if0_43460400_rutahyd2_es`.
- Sin usuario administrador operativo: debe crearse de forma segura tras instalar tokens.
- Sin frontend conectado aún.
