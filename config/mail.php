<?php
declare(strict_types=1);

// Solo SMTP real en producción: nunca registrar enlaces de recuperación en logs.
return [
    'default' => env('MAIL_MAILER', 'log'),
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => (int) env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 10,
        ],
        'log' => ['transport' => 'log'],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.invalid'),
        'name' => env('MAIL_FROM_NAME', 'Ruta H&D2'),
    ],
];
