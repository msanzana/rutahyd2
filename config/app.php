<?php
return [
    'name'=>env('APP_NAME','RutaHD2 API'),
    'env'=>env('APP_ENV','production'),
    'debug'=>(bool)env('APP_DEBUG',false),
    'url'=>env('APP_URL','http://localhost'),
    'timezone'=>'America/Santiago',
    'locale'=>'es','fallback_locale'=>'es',
    'key'=>env('APP_KEY'), 'cipher'=>'AES-256-CBC',
    'maintenance'=>['driver'=>'file'],
];
