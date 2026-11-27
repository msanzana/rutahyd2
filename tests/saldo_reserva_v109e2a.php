<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\SaldoReservaInventario;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function reject(callable $f):void{try{$f();}catch(HttpExceptionInterface $e){check($e->getStatusCode()===409,'Estado inesperado');return;}throw new RuntimeException('Faltó rechazo');}
check(config('app.env')==='testing','Solo CI');
$m=new SaldoReservaInventario();
$base=['reserva_id'=>1,'producto_id'=>2,'bodega_id'=>3,'sucursal_id'=>4,'estado_operacion'=>'INV_CONFIRMADA','creado_por'=>5,'aprobado_por'=>6,'delta_transito'=>'0.00000000','delta_fisico'=>'0.00000000'];
$a=$base+['operacion_id'=>10,'kardex_id'=>20,'motivo_codigo'=>710,'cantidad'=>'4.12345678','delta_disponible'=>'-4.12345678','delta_comprometido'=>'4.12345678'];
$b=$base+['operacion_id'=>11,'kardex_id'=>21,'motivo_codigo'=>711,'cantidad'=>'1.12345678','delta_disponible'=>'1.12345678','delta_comprometido'=>'-1.12345678'];
$c=$base+['operacion_id'=>12,'kardex_id'=>22,'motivo_codigo'=>711,'cantidad'=>'3','delta_disponible'=>'3.00000000','delta_comprometido'=>'-3.00000000'];
$read=fn(array $rows,mixed $stock='10')=>$m->conciliar(1,2,3,4,$rows,$stock);
check($read([])['pendiente']==='0.00000000','Sin alta no existe saldo propio');
check($read([$a])['pendiente']==='4.12345678','Alta exacta');
$p=$read([$a,$b]);
check($p['reservada']==='4.12345678'&&$p['liberada']==='1.12345678'&&$p['pendiente']==='3.00000000'&&$p['operaciones']===[10,11],'Parcial exacta');
check($read([$a,$b,$c],'0')['pendiente']==='0.00000000','Liberación total');
reject(fn()=>$read([$b]));
reject(fn()=>$read([$a,$a]));
reject(fn()=>$read([$a,$b,$b]));
reject(fn()=>$read([$a,$b], '2.99999999'));
foreach(['reserva_id'=>9,'producto_id'=>9,'bodega_id'=>9,'sucursal_id'=>9,'estado_operacion'=>'INV_APROBADA','aprobado_por'=>5,'motivo_codigo'=>712,'delta_fisico'=>'1.00000000','delta_comprometido'=>'-2.00000000','kardex_id'=>20,'operacion_id'=>10,'cantidad'=>'4.12345679'] as $k=>$v)reject(fn()=>$read([$a,array_replace($b,[$k=>$v])]));
reject(fn()=>$read([$a,$b,$c,array_replace($c,['operacion_id'=>13,'kardex_id'=>23])]));
reject(fn()=>$read([$a,array_replace($a,['operacion_id'=>13,'kardex_id'=>23])]));
echo "V109E2A_SALDO_RESERVA_OK\n";
