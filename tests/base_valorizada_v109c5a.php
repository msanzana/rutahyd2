<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\BaseValorizadaInventario;
use App\Services\CostoPromedioPonderado;
use Symfony\Component\HttpKernel\Exception\HttpException;
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function reject(callable $f):void{try{$f();}catch(HttpException $e){check($e->getStatusCode()===409,'Rechazo inesperado');return;}throw new RuntimeException('Rechazo omitido');}
check(config('app.env')==='testing','Solo CI');
$svc=new BaseValorizadaInventario();
function stock(int $b,string $a,string $t,string $c,string $f):object{return (object)['producto_id'=>1,'bodega_id'=>$b,'disponible'=>$a,'transito'=>$t,'comprometido'=>$c,'cantidad_fisica'=>$f];}
$a=stock(1,'3','0','2','5');$b=stock(2,'2','7','0','9');
$l=(object)['id'=>1,'producto_id'=>1,'bodega_origen_id'=>1,'bodega_destino_id'=>2,'sucursal_origen_id'=>1,'sucursal_destino_id'=>1,'modalidad'=>'DESPACHO_RECEPCION','estado'=>'DESPACHADO','cantidad_solicitada'=>'3','cantidad_despachada'=>'3','cantidad_recibida'=>'0','cantidad_regularizada'=>'0','costo_cpp_snapshot'=>'100'];
$r=$svc->calcular(1,[$a,$b],[$l]);
check($r['stock_valorizado']==='10.00000000'&&$r['transito_interno']==='3.00000000'&&$r['transito_no_valorizado']==='4.00000000','Base incluye tránsito propio y excluye compras');
$svc->verificarLiberacionCompra($r,2,'4');
reject(fn()=>$svc->verificarLiberacionCompra($r,2,'4.00000001'));
$cpp=(new CostoPromedioPonderado())->calcular($r['stock_valorizado'],'100','4','800','1000');
check($cpp['cpp_posterior']==='128.57142857','Compra nueva sobre 10 valorizadas, no sobre 7 disponibles/comprometidas');
$p=clone $l;$p->estado='PARCIAL';$p->cantidad_recibida='1.12345678';
$b2=stock(2,'3.12345678','5.87654322','0','9');
$r2=$svc->calcular(1,[$a,$b2],[$p]);
check($r2['stock_valorizado']===$r['stock_valorizado']&&$r2['transito_interno']==='1.87654322'&&$r2['transito_no_valorizado']==='4.00000000','Recepción parcial no altera base valorizada');
$done=clone $l;$done->estado='RECIBIDO';$done->cantidad_recibida='3';
$r3=$svc->calcular(1,[$a,stock(2,'5','4','0','9')],[$done]);
check($r3['stock_valorizado']===$r['stock_valorizado']&&$r3['transito_interno']==='0.00000000','Recepción completa');
$r4=$svc->calcular(1,[$a,$b],[]);
check($r4['stock_valorizado']==='7.00000000','Sin traspasos se preserva regla histórica');
reject(fn()=>$svc->calcular(1,[$a,$b],[$l,$l]));
reject(fn()=>$svc->calcular(1,[$a,$a],[]));
$bad=clone $l;$bad->estado='BORRADOR';reject(fn()=>$svc->calcular(1,[$a,$b],[$bad]));
$bad=clone $l;$bad->sucursal_destino_id=null;reject(fn()=>$svc->calcular(1,[$a,$b],[$bad]));
$bad=clone $l;$bad->costo_cpp_snapshot=null;reject(fn()=>$svc->calcular(1,[$a,$b],[$bad]));
$bad=clone $l;$bad->cantidad_solicitada='8';$bad->cantidad_despachada='8';reject(fn()=>$svc->calcular(1,[$a,$b],[$bad]));
reject(fn()=>$svc->calcular(1,[$a],[$l]));
$bad=clone $b;$bad->cantidad_fisica='8';reject(fn()=>$svc->calcular(1,[$a,$bad],[$l]));
$bad=clone $b;$bad->transito=7.0;reject(fn()=>$svc->calcular(1,[$a,$bad],[$l]));
echo "V109C5A_BASE_CPP_OK\n";
