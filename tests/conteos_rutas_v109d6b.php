<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Middleware\AutorizarConteos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

function verify(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
verify(config('app.env')==='testing','Solo pruebas aisladas');
$routes=fn()=>array_values(array_filter(Route::getRoutes()->getRoutes(),fn($r)=>str_starts_with($r->uri(),'api/v1/inventario/conteos')));
$posts=fn()=>array_values(array_filter($routes(),fn($r)=>in_array('POST',$r->methods(),true)));
verify(!AutorizarConteos::habilitado()&&count($posts())===0,'Configuración de fábrica sin POST');
$gate=new AutorizarConteos();$r=Request::create('/','POST');
$blocked=function()use($gate,$r){
    try{$gate->handle($r,fn()=>response('No debe pasar'));}catch(HttpExceptionInterface $e){verify($e->getStatusCode()===409,'Bloqueo esperado');return;}
    throw new RuntimeException('Faltó bloqueo');
};
$blocked();
$verified=['conteos.escrituras'=>true,'conteos.alcance_selectivo_verificado'=>true,'conteos.conteo_ciego_verificado'=>true,'conteos.estado_confirmado'=>'CONFIRMADO','conteos.estado_anulado'=>'ANULADO'];
foreach($verified as $key=>$value){
    config($verified);config([$key=>null]);
    verify(!AutorizarConteos::habilitado(),'Requisito omitido: '.$key);$blocked();
}
config($verified);config(['conteos.escrituras'=>'true']);verify(!AutorizarConteos::habilitado(),'No aceptar string como booleano');$blocked();
config($verified);
verify(AutorizarConteos::habilitado(),'Contrato simulado completo');
verify($gate->handle($r,fn()=>response('OK'))->getContent()==='OK','Middleware permite delegar');
Route::prefix('api')->middleware('api')->group(base_path('routes/api.php'));
$expected=[
 ''=>['crear','gestionar'],
 '/{id}/lineas/{line}/contar'=>['contar','gestionar'],
 '/{id}/lineas/{line}/recontar'=>['recontar','gestionar'],
 '/{id}/revisar'=>['revisar','aprobar'],
 '/{id}/previsualizar'=>['previsualizarAprobacion','ver'],
 '/{id}/autorizar'=>['autorizarDiferencias','aprobar'],
 '/{id}/preparar'=>['prepararAjustes','aprobar'],
 '/{id}/confirmar'=>['confirmar','confirmar'],
 '/{id}/anular'=>['anular','anular'],
];
verify(count($posts())===count($expected),'Nueve rutas de escritura');
foreach($posts() as $route){
    $suffix=substr($route->uri(),strlen('api/v1/inventario/conteos'));
    verify(isset($expected[$suffix]),'Ruta inesperada');
    [$method,$permission]=$expected[$suffix];
    verify($route->getActionMethod()===$method,'Método incorrecto');
    $middlewares=$route->gatherMiddleware();
    foreach(['token.rutahd','permiso.rutahd:inventario.operaciones.'.$permission,AutorizarConteos::class,'throttle:20,1'] as $m)verify(in_array($m,$middlewares,true),'Falta protección: '.$m);
    unset($expected[$suffix]);
}
verify($expected===[],'Rutas completas');
config(['conteos.escrituras'=>false]);$blocked(); // También impide llamadas con rutas previamente registradas.
echo "V109D6B_RUTAS_OK\n";
