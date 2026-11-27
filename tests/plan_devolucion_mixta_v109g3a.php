<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('app.env')!=='testing')throw new RuntimeException('Solo CI aislado');
use App\Services\PlanDevolucionMixta;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function reject(callable $f,int $status=409):void{try{$f();}catch(HttpExceptionInterface $e){check($e->getStatusCode()===$status,'Estado inesperado: '.$e->getMessage());return;}throw new RuntimeException('No bloqueado');}
$m=(object)['codigo'=>603,'grupo_codigo'=>600,'regla'=>'MIXTO_CI','naturaleza'=>'MIXTO','politica_cpp'=>'ESPECIFICA','indicador_traslado_sii'=>null,'activo'=>1,'ajuste_rapido'=>0,'requiere_referencia'=>1,'requiere_aprobacion'=>1,'requiere_respaldo'=>1,'revision_tributaria'=>1,'evaluar_aviso_sii'=>0];
$cfg=[603=>['regla'=>'MIXTO_CI','indicador'=>null,'naturaleza'=>'MIXTO','valorizacion'=>'ESPECIFICA_NETO_POR_PRODUCTO']];
function stock(int $pid,int $bid,string $a,string $t,string $c,string $f):object{return (object)['sucursal_id'=>1,'producto_id'=>$pid,'bodega_id'=>$bid,'disponible'=>$a,'transito'=>$t,'comprometido'=>$c,'cantidad_fisica'=>$f];}
function ref(int $pid,int $bid,string $dir,string $saldo,string $cost):array{return ['acreditada'=>true,'sucursal_id'=>1,'producto_id'=>$pid,'bodega_id'=>$bid,'direccion'=>$dir,'saldo_solicitable'=>$saldo,'costo_especifico'=>$cost,'grupo_documental'=>'OPERACION-CI-1'];}
function linea(int $pid,int $bid,int $ref,string $dir,string $q):array{return ['producto_id'=>$pid,'bodega_id'=>$bid,'referencia_kardex_id'=>$ref,'direccion'=>$dir,'cantidad'=>$q];}
$p=new PlanDevolucionMixta();
$products=[1=>['base_acreditada'=>true,'base_valorizada'=>'8','cpp'=>'600'],2=>['base_acreditada'=>true,'base_valorizada'=>'0','cpp'=>'0']];
$stocks=['1:1'=>stock(1,1,'5','2','3','10'),'2:1'=>stock(2,1,'0','0','0','0')];
$refs=[10=>ref(1,1,'SALIDA','2','400'),20=>ref(2,1,'ENTRADA','3','300')];
$lines=[linea(2,1,20,'ENTRADA','3'),linea(1,1,10,'SALIDA','2')];
$calc=fn($l=$lines,$ps=$products,$ss=$stocks,$rr=$refs,$cc=$cfg)=>$p->calcular($m,$cc,1,$l,$ps,$ss,$rr);
$v=$calc();
check($v['valorizaciones'][0]['cpp_actual']==='666.66666667'&&$v['valorizaciones'][1]['cpp_actual']==='300.00000000','CPP independiente por SKU');
check($v['stocks'][0]['disponible_actual']==='3.00000000'&&$v['stocks'][1]['disponible_actual']==='3.00000000','Saldos por producto');
check($v['lineas'][0]['direccion']==='SALIDA'&&$v['documento_emitido']===false&&$v['requiere_transaccion_atomica']===true,'Orden y contrato');
check($calc(array_reverse($lines))===$v,'Orden determinista');
reject(fn()=>$calc([$lines[0],$lines[0]]),422);
reject(fn()=>$calc([linea(1,1,10,'SALIDA','2.00000001'),$lines[0]]));
reject(fn()=>$calc($lines,$products,$stocks,array_replace($refs,[20=>array_replace($refs[20],['grupo_documental'=>'OTRO'])])));
reject(fn()=>$calc($lines,$products,$stocks,array_replace($refs,[20=>array_replace($refs[20],['acreditada'=>false])])));
reject(fn()=>$calc($lines,array_replace($products,[1=>array_replace($products[1],['cpp'=>'0'])])));
reject(fn()=>$calc($lines,$products,$stocks,array_replace($refs,[10=>ref(1,1,'SALIDA','2','2500')])));
$mix=[linea(1,1,10,'SALIDA','2'),linea(1,1,21,'ENTRADA','1')];
$refs2=[10=>$refs[10],21=>ref(1,1,'ENTRADA','1','200')];
$x=$calc($mix,$products,$stocks,$refs2);
check($x['valorizaciones'][0]['cpp_actual']==='600.00000000'&&$x['stocks'][0]['disponible_actual']==='4.00000000','Mismo SKU cálculo neto');
reject(fn()=>$calc([linea(1,1,10,'SALIDA','6'),linea(1,1,21,'ENTRADA','6')],$products,$stocks,[10=>ref(1,1,'SALIDA','6','400'),21=>ref(1,1,'ENTRADA','6','400')]));
reject(fn()=>$calc($lines,$products,$stocks,$refs,[]));
echo "V109G3A_MIXTO_OK\n";
