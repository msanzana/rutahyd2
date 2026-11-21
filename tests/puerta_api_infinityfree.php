<?php
declare(strict_types=1);

/** Comprueba el URI exacto que Laravel recibe detrás del gateway /htdocs/api. */
require dirname(__DIR__).'/vendor/autoload.php';
use Illuminate\Http\Request;

$casos=['/api/v1/estado','/api/v1/sesion','/api/v1/catalogos/rubros','/api/v1/recursos/clientes'];
foreach($casos as $uri) {
    $_SERVER['REQUEST_METHOD']='GET';
    $_SERVER['REQUEST_URI']=$uri;
    $_SERVER['SCRIPT_FILENAME']='/var/www/htdocs/api/index.php';
    $_SERVER['SERVER_NAME']='rutahyd2.free.nf';
    $_SERVER['SERVER_PORT']='443';
    $_SERVER['HTTPS']='on';
    $_SERVER['SCRIPT_NAME']='/index.php';
    $_SERVER['PHP_SELF']='/index.php';
    $request=Request::capture();
    if($request->getPathInfo()!==$uri || $request->getBaseUrl()!=='') {
        fwrite(STDERR, "Fallo ruta: {$uri} => ".$request->getPathInfo()." base:".$request->getBaseUrl()."\n");
        exit(1);
    }
}
echo "OK: gateway conserva rutas /api/v1 ante Symfony/Laravel\n";
