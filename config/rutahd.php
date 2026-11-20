<?php
declare(strict_types=1);

// El secreto de firma vive exclusivamente en entorno privado del servidor.
// No reutilizar la contraseña de administrador como secreto JWT.
return [
    'jwt_secret' => env('RUTAHD_JWT_SECRET', ''),
    'jwt_issuer' => 'rutahd2-api',
    'jwt_audience' => 'rutahd2-web',
    'jwt_ttl_seconds' => 3600,
];
