<?php
declare(strict_types=1);

/**
 * Puerta pública ÚNICA a Laravel V2. Mantener /api-v2/ íntegramente protegido.
 * ACTIVACIÓN EXPLÍCITA: solo funciona cuando el backend dispone de .env
 * y el marcador de habilitación fue creado después de las pruebas productivas.
 * Nunca se entrega configuración ni estado interno en la respuesta pública.
 */
ini_set('display_errors', '0');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$backend = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'api-v2';
$envFile = $backend . DIRECTORY_SEPARATOR . '.env';
$marker = $backend . DIRECTORY_SEPARATOR . 'storage/app/private/rutahd2_api_ready';
$autoload = $backend . DIRECTORY_SEPARATOR . 'vendor/autoload.php';
$appFile = $backend . DIRECTORY_SEPARATOR . 'bootstrap/app.php';

if (
    !is_file($envFile) ||
    !is_readable($envFile) ||
    !is_file($marker) ||
    !is_file($autoload) ||
    !is_file($appFile)
) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'servicio' => 'Ruta H&D2 API',
        'estado' => 'no_habilitada'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Mantener el punto de entrada desacoplado del directorio de frontend.
define('LARAVEL_START', microtime(true));
require $autoload;
$app = require $appFile;
$app->handleRequest(\Illuminate\Http\Request::capture());
