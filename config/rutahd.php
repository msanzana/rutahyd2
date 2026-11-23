<?php
declare(strict_types=1);

// El secreto de firma vive exclusivamente en entorno privado del servidor.
// No reutilizar la contraseña de administrador como secreto JWT.
return [
    'jwt_secret' => env('RUTAHD_JWT_SECRET', ''),
    'jwt_issuer' => 'rutahd2-api',
    'jwt_audience' => 'rutahd2-web',
    'jwt_ttl_seconds' => 3600,
    // Identidad del emisor para órdenes de compra (configuración privada).
    // RUT, giro y dirección nunca deben suponerse a partir de otra empresa.
    'oc_emisor' => [
        'nombre' => env('RUTAHD_OC_EMISOR_NOMBRE','Comercializadora H&D'),
        'rut' => env('RUTAHD_OC_EMISOR_RUT',''),
        'giro' => env('RUTAHD_OC_EMISOR_GIRO',''),
        'direccion' => env('RUTAHD_OC_EMISOR_DIRECCION',''),
        'correo' => env('RUTAHD_OC_EMISOR_CORREO',''),
    ],
];
