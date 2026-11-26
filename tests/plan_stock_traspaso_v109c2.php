<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\PlanStockTraspaso;
use Symfony\Component\HttpKernel\Exception\HttpException;
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function reject(callable $f):void{try{$f();}catch(HttpException $e){check($e->getStatusCode()===409,'Rechazo inesperado');return;}throw new RuntimeException('Rechazo omitido');}
check(config('app.env')==='testing','Solo CI');
$p=new PlanStockTraspaso();
$m=(object)['codigo'=>709,'grupo_codigo'=>700,'activo'=>1,'regla'=>'TRASPASO_INTERNO','politica_cpp'=>'CONSERVAR','naturaleza'=>'TRASLADO','ajuste_rapido'=>0,'requiere_referencia'=>0,'requiere_aprobacion'=>1,'requiere_respaldo'=>0,'revision_tributaria'=>0,'evaluar_aviso_sii'=>0,'indicador_traslado_sii'=>5];
$l=(object)['cantidad_solicitada'=>'4.25','cantidad_despachada'=>'0','cantidad_recibida'=>'0','cantidad_regularizada'=>'0'];
$a=(object)['disponible'=>'5','transito'=>'1','comprometido'=>'2','cantidad_fisica'=>'8'];
$b=(object)['disponible'=>'3','transito'=>'2','comprometido'=>'1','cantidad_fisica'=>'6'];
$x=$p->calcular($m,$l,'INMEDIATO','INMEDIATO','4.25',$a,$b,'500.1234');
check($x['origen']['disponible']['posterior']==='0.75000000'&&$x['destino']['disponible']['posterior']==='7.25000000'&&$x['cpp_actual']==='500.12340000'&&$x['indicador_traslado_sii']===5,'Inmediato');
check($x['valor_movimiento']==='2125.5244500000000000','Valor exacto');
$x=$p->calcular($m,$l,'DESPACHO_RECEPCION','DESPACHO','4.25',$a,$b,'500.1234');
check($x['destino']['transito']['posterior']==='6.25000000'&&$x['destino']['disponible']['posterior']==='3.00000000'&&$x['origen']['comprometido']['posterior']==='2.00000000','Despacho');
function after(array $x):object{return (object)array_map(fn($v)=>$v['posterior'],$x);}
$la=(object)($x['cantidades']['posterior']+['costo_cpp_snapshot'=>$x['costo_cpp_snapshot']]);
$aa=after($x['origen']);$bb=after($x['destino']);
$x=$p->calcular($m,$la,'DESPACHO_RECEPCION','RECEPCION','1.25',$aa,$bb,'500.1234');
check($x['destino']['transito']['posterior']==='5.00000000'&&$x['destino']['disponible']['posterior']==='4.25000000'&&$x['destino']['cantidad_fisica']['delta']==='0.00000000','Parcial');
$lb=(object)($x['cantidades']['posterior']+['costo_cpp_snapshot'=>$x['costo_cpp_snapshot']]);
$bb=after($x['destino']);
$x=$p->calcular($m,$lb,'DESPACHO_RECEPCION','RECEPCION','3',$aa,$bb,'500.1234');
check($x['destino']['transito']['posterior']==='2.00000000'&&$x['destino']['disponible']['posterior']==='7.25000000'&&$x['cantidades']['recepcion_completa'],'Final preserva tránsito previo ajeno');
reject(fn()=>$p->calcular($m,$lb,'DESPACHO_RECEPCION','RECEPCION','3',$aa,$bb,'501'));
$bad=clone $bb;$bad->transito='2';$bad->cantidad_fisica='7.25';reject(fn()=>$p->calcular($m,$lb,'DESPACHO_RECEPCION','RECEPCION','3',$aa,$bad,'500.1234'));
$bad=clone $a;$bad->disponible='4';$bad->cantidad_fisica='7';reject(fn()=>$p->calcular($m,$l,'INMEDIATO','INMEDIATO','4.25',$bad,$b,'500'));
$bad=clone $m;$bad->requiere_aprobacion=0;reject(fn()=>$p->calcular($bad,$l,'INMEDIATO','INMEDIATO','4.25',$a,$b,'500'));
reject(fn()=>$p->calcular($m,$l,'INMEDIATO','INMEDIATO','4.25',$a,$b,'0'));
echo "V109C2_STOCK_OK\n";
