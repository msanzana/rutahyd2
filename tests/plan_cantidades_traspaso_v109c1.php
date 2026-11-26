<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\PlanCantidadesTraspaso;
use Symfony\Component\HttpKernel\Exception\HttpException;
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function reject(callable $f,int $status):void{try{$f();}catch(HttpException $e){check($e->getStatusCode()===$status,'Rechazo inesperado');return;}throw new RuntimeException('Rechazo omitido');}
check(config('app.env')==='testing','Solo CI');
$p=new PlanCantidadesTraspaso();
$l=(object)['cantidad_solicitada'=>'12.12345678','cantidad_despachada'=>'0','cantidad_recibida'=>'0','cantidad_regularizada'=>'0'];
$x=$p->calcular($l,'INMEDIATO','INMEDIATO','12.12345678');
check($x['posterior']['pendiente_recepcion']==='0.00000000'&&$x['recepcion_completa'],'Inmediato completo');
$x=$p->calcular($l,'DESPACHO_RECEPCION','DESPACHO','12.12345678');
check($x['posterior']['pendiente_recepcion']==='12.12345678'&&!$x['recepcion_completa'],'Despacho en tránsito');
$dispatched=(object)$x['posterior'];
$x=$p->calcular($dispatched,'DESPACHO_RECEPCION','RECEPCION','5.12345678');
check($x['posterior']['pendiente_recepcion']==='7.00000000'&&!$x['recepcion_completa'],'Recepción parcial');
$partial=(object)$x['posterior'];
$x=$p->calcular($partial,'DESPACHO_RECEPCION','RECEPCION','7');
check($x['posterior']['pendiente_recepcion']==='0.00000000'&&$x['recepcion_completa'],'Recepción final');
reject(fn()=>$p->calcular($partial,'DESPACHO_RECEPCION','RECEPCION','7.00000001'),409);
reject(fn()=>$p->calcular($dispatched,'DESPACHO_RECEPCION','DESPACHO','12.12345678'),409);
reject(fn()=>$p->calcular($l,'DESPACHO_RECEPCION','RECEPCION','1'),409);
reject(fn()=>$p->calcular($l,'INMEDIATO','DESPACHO','12.12345678'),409);
reject(fn()=>$p->calcular($l,'DESPACHO_RECEPCION','DESPACHO','1'),409);
reject(fn()=>$p->calcular($partial,'DESPACHO_RECEPCION','REGULARIZACION','1'),422);
foreach(['0','-1','1e2','1.123456789'] as $q)reject(fn()=>$p->calcular($partial,'DESPACHO_RECEPCION','RECEPCION',$q),422);
$bad=clone $partial;$bad->cantidad_regularizada='8';reject(fn()=>$p->resumen($bad),409);
$regularized=clone $partial;$regularized->cantidad_regularizada='2';check($p->resumen($regularized)['pendiente_recepcion']==='5.00000000','Descontar regularización histórica sin crearla');
echo "V109C1_CANTIDADES_OK\n";
