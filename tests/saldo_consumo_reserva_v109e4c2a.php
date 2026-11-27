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

$d=array_replace($base,['operacion_id'=>12,'kardex_id'=>22,'motivo_codigo'=>712,'cantidad'=>'2.12345678','delta_disponible'=>'0.00000000','delta_comprometido'=>'-2.12345678','delta_fisico'=>'-2.12345678']);
$e=array_replace($d,['operacion_id'=>13,'kardex_id'=>23,'cantidad'=>'0.87654322','delta_comprometido'=>'-0.87654322','delta_fisico'=>'-0.87654322']);
$read=fn(array $rows,mixed $stock='10')=>$m->conciliar(1,2,3,4,$rows,$stock,true);
reject(fn()=>$m->conciliar(1,2,3,4,[$a,$b,$d],'10')); // Lector real todavía no habilitado.
$x=$read([$a,$b,$d]);
check($x['reservada']==='4.12345678'&&$x['liberada']==='1.12345678'&&$x['despachada']==='2.12345678'&&$x['pendiente']==='0.87654322','Conciliación combinada');
check($read([$a,$b,$d,$e],'0')['pendiente']==='0.00000000','Consumo final');
reject(fn()=>$read([$d]));
reject(fn()=>$read([$a,$b,$d,$d]));
reject(fn()=>$read([$a,$b,$d,$e,$c]));
reject(fn()=>$read([$a,$b,$d],'0.87654321'));
foreach(['delta_disponible'=>'-2.12345678','delta_fisico'=>'0.00000000','delta_comprometido'=>'0.00000000','delta_transito'=>'1.00000000','sucursal_id'=>99,'aprobado_por'=>5,'estado_operacion'=>'INV_APROBADA'] as $key=>$v)reject(fn()=>$read([$a,$b,array_replace($d,[$key=>$v])]));
echo "V109E4C2A_SALDO_CONSUMO_OK\n";
