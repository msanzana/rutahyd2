<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('app.env')!=='testing')throw new RuntimeException('Solo CI aislado');

use App\Services\PlanDevolucionInventario;
use App\Services\SaldoDevolucionReferencia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function reject(callable $f,int $status=409):void{try{$f();}catch(HttpExceptionInterface $e){check($e->getStatusCode()===$status,'Rechazo esperado');return;}throw new RuntimeException('No bloqueado');}
function motivo(int $code,string $nature,string $policy,?int $indicator):object{return (object)['codigo'=>$code,'grupo_codigo'=>600,'regla'=>'DEVOLUCION_CI','naturaleza'=>$nature,'politica_cpp'=>$policy,'indicador_traslado_sii'=>$indicator,'activo'=>1,'ajuste_rapido'=>0,'requiere_referencia'=>1,'requiere_aprobacion'=>1,'requiere_respaldo'=>1,'revision_tributaria'=>1,'evaluar_aviso_sii'=>0];}
function stock(string $a,string $t,string $c,string $f):object{return (object)['disponible'=>$a,'transito'=>$t,'comprometido'=>$c,'cantidad_fisica'=>$f];}
$p=new PlanDevolucionInventario();$s=stock('5','2','3','10');
$contracts=[601=>['regla'=>'DEVOLUCION_CI','indicador'=>7],602=>['regla'=>'DEVOLUCION_CI','indicador'=>7],604=>['regla'=>'DEVOLUCION_CI','indicador'=>null],605=>['regla'=>'DEVOLUCION_CI','indicador'=>null],606=>['regla'=>'DEVOLUCION_CI','indicador'=>7],607=>['regla'=>'DEVOLUCION_CI','indicador'=>6],608=>['regla'=>'DEVOLUCION_CI','indicador'=>6],609=>['regla'=>'DEVOLUCION_CI','indicador'=>null]];
foreach([601,604,606,609] as $code){
 $m=motivo($code,'ENTRADA',in_array($code,[601,606],true)?'HISTORICO':'ESPECIFICA',$contracts[$code]['indicador']);
 $v=$p->calcular($m,$contracts,'2',$s,'600','8','3','1','400');
 check($v['cpp_actual']==='560.00000000'&&$v['lineas']['entrada']['disponible_actual']==='7.00000000'&&$v['documento_emitido']===false,'Entrada exacta');
}
$m=motivo(602,'SALIDA','ESPECIFICA',7);
$v=$p->calcular($m,$contracts,'2',$s,'600','8','3','1','400');
check($v['cpp_actual']==='666.66666667'&&$v['lineas']['salida']['fisico_actual']==='8.00000000','Salida específica');
$v=$p->calcular(motivo(605,'SALIDA','VIGENTE',null),$contracts,'2',$s,'600','8','3','1');
check($v['cpp_actual']==='600.00000000','Salida vigente conserva CPP');
foreach([607,608] as $code){
 $v=$p->calcular(motivo($code,'TRASLADO','CONSERVAR',6),$contracts,'2',$s,'600','10','3','1',null,stock('2','0','0','2'));
 check($v['lineas']['origen']['disponible_actual']==='3.00000000'&&$v['lineas']['destino']['disponible_actual']==='4.00000000'&&$v['base_actual']==='10.00000000'&&$v['cpp_actual']==='600.00000000','Traslado conserva total y CPP');
}
reject(fn()=>$p->calcular($m,$contracts,'2.00000001',$s,'600','8','3','1','400'));
reject(fn()=>$p->calcular($m,$contracts,'2',$s,'600','8','3','1','3000'));
reject(fn()=>$p->calcular($m,$contracts,'2',stock('2','0','0','2'),'600','2','2','0','400'));
reject(fn()=>$p->calcular(motivo(603,'MIXTO','ESPECIFICA',null),$contracts,'1',$s,'600','8','1','0','400'));
reject(fn()=>$p->calcular(motivo(601,'ENTRADA','HISTORICO',6),$contracts,'1',$s,'600','8','1','0','400'));
$zero=$p->calcular(motivo(601,'ENTRADA','HISTORICO',7),$contracts,'1',stock('0','0','0','0'),'0','0','1','0','400');
check($zero['cpp_actual']==='400.00000000','CPP inicial histórico');
$ledger=new SaldoDevolucionReferencia();
$first=['id'=>1,'sucursal_id'=>1,'producto_id'=>2,'referencia_kardex_id'=>10,'motivo_codigo'=>601,'referencia_acreditada'=>true,'estado'=>'INV_CONFIRMADA','cantidad'=>'1.25','kardex_id'=>11,'creado_por'=>1,'aprobado_por'=>2,'movimiento_acreditado'=>true];
$second=array_replace($first,['id'=>2,'estado'=>'INV_APROBADA','cantidad'=>'0.75','kardex_id'=>null,'movimiento_acreditado'=>false]);
$third=array_replace($second,['id'=>3,'estado'=>'INV_ANULADA','cantidad'=>'3']);
$r=$ledger->conciliar(1,2,10,'3',[$first,$second,$third],[601,606]);
check($r['aplicada']==='1.25000000'&&$r['solicitada_pendiente']==='0.75000000'&&$r['disponible_para_solicitar']==='1.00000000','Conciliación acumulada');
reject(fn()=>$ledger->conciliar(1,2,10,'1.99',[$first,$second],[601,606]));
reject(fn()=>$ledger->conciliar(1,2,10,'3',[$first,$first],[601,606]));
reject(fn()=>$ledger->conciliar(1,2,10,'3',[$first,array_replace($second,['sucursal_id'=>2])],[601,606]));
echo "V109G1_G2A_DEVOLUCIONES_OK\n";
