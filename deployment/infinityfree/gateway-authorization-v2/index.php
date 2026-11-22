<?php
declare(strict_types=1);

/**
 * Gateway productivo de Ruta H&D V2. El directorio api-v2 permanece privado.
 * Resuelve el transporte de JWT cuando InfinityFree elimina Authorization.
 * Nunca recibe JWT por parámetros URL ni cookies. Solo headers HTTPS.
 */
ini_set('display_errors', '0');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$backend = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'api-v2';
$envFile = $backend . DIRECTORY_SEPARATOR . '.env';
$marker = $backend . DIRECTORY_SEPARATOR . 'storage/app/private/rutahd2_api_ready';
$autoload = $backend . DIRECTORY_SEPARATOR . 'vendor/autoload.php';
$appFile = $backend . DIRECTORY_SEPARATOR . 'bootstrap/app.php';

if (!is_file($envFile) || !is_readable($envFile) ||
    !is_file($marker) || !is_file($autoload) || !is_file($appFile)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['servicio'=>'Ruta H&D2 API','estado'=>'no_habilitada'],JSON_UNESCAPED_UNICODE);
    exit;
}

// InfinityFree puede eliminar el header Authorization estándar antes de llegar
// a PHP. El frontend envía además X-RutaHD-Token en HTTPS al mismo origen.
// Se normaliza como Bearer, SIN alterar la validación JWT de Laravel.
$secondary = $_SERVER['HTTP_X_RUTAHD_TOKEN'] ?? '';
if ($secondary !== '') {
    if (!is_string($secondary) || strlen($secondary) > 4096 ||
        preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D', $secondary) !== 1) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"mensaje":"Token inválido"}';
        exit;
    }
    $expected = 'Bearer ' . $secondary;
    $standard = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($standard !== '' && !hash_equals($expected, $standard)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"mensaje":"Encabezados de autenticación incompatibles"}';
        exit;
    }
    $_SERVER['HTTP_AUTHORIZATION'] = $expected;
}

define('LARAVEL_START', microtime(true));
require $autoload;
$app = require $appFile;
$app->handleRequest(\Illuminate\Http\Request::capture());
