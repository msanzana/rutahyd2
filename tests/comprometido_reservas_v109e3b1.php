<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\LectorSaldoReservaInventario;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function reject(callable $f,int $status=409):void{try{$f();}catch(HttpExceptionInterface $e){check($e->getStatusCode()===$status,'Estado inesperado: '.$e->getMessage());return;}throw new RuntimeException('Faltó rechazo');}
$mysql=DB::connection()->getDriverName()==='mysql';
check(config('app.env')==='testing'&&DB::getDatabaseName()===($mysql?'rutahyd_v109e3b1_ci':':memory:'),'Solo CI aislado');
if($mysql)check(DB::selectOne('SELECT DATABASE() AS db')->db==='rutahyd_v109e3b1_ci','Base incorrecta');
foreach([
'CREATE TABLE productos(id BIGINT PRIMARY KEY)',
'CREATE TABLE inventario_bodegas_sucursales(bodega_id BIGINT,sucursal_id BIGINT)',
'CREATE TABLE inventario_reservas(id BIGINT PRIMARY KEY,estado VARCHAR(30),nota_venta_id BIGINT,sucursal_id BIGINT)',
'CREATE TABLE inventario_reservas_detalle(id BIGINT PRIMARY KEY,reserva_id BIGINT,producto_id BIGINT,bodega_id BIGINT,cantidad_reservada TEXT,cantidad_despachada TEXT,cantidad_liberada TEXT)',
'CREATE TABLE inventario_reservas_operaciones(reserva_id BIGINT,operacion_id BIGINT,PRIMARY KEY(reserva_id,operacion_id))',
'CREATE TABLE stock(id BIGINT PRIMARY KEY,producto_id BIGINT,bodega_id BIGINT,disponible TEXT,transito TEXT,comprometido TEXT,cantidad_fisica TEXT)',
'CREATE TABLE estados(id BIGINT PRIMARY KEY,codigo VARCHAR(30))',
'CREATE TABLE inventario_operaciones(id BIGINT PRIMARY KEY,clase VARCHAR(30),sucursal_id BIGINT,estado_id BIGINT,motivo_codigo_snapshot INTEGER,regla_snapshot VARCHAR(60),politica_cpp_snapshot VARCHAR(30),creado_por BIGINT,aprobado_por BIGINT,confirmado_por BIGINT,token_confirmacion VARCHAR(60))',
'CREATE TABLE inventario_operaciones_detalle(id BIGINT PRIMARY KEY,operacion_id BIGINT,producto_id BIGINT,bodega_id BIGINT,cantidad TEXT,delta_disponible TEXT,delta_comprometido TEXT,delta_transito TEXT,delta_fisico TEXT,costo_unitario_snapshot TEXT,kardex_id BIGINT)',
'CREATE TABLE kardex(id BIGINT PRIMARY KEY,producto_id BIGINT,bodega_id BIGINT,sucursal_id BIGINT,origen_tipo VARCHAR(30),origen_detalle_id BIGINT,operacion_codigo VARCHAR(60),cantidad_documento TEXT,costo_unitario_neto TEXT,cpp_anterior TEXT,cpp_actual TEXT,cantidad_anterior TEXT,cantidad_actual TEXT,comprometido_anterior TEXT,comprometido_actual TEXT,transito_anterior TEXT,transito_actual TEXT,fisico_anterior TEXT,fisico_actual TEXT)'
] as $sql){
 if($mysql)$sql=str_replace(' TEXT',' DECIMAL(28,8)',$sql);
 DB::statement($sql);
}
DB::table('productos')->insert(['id'=>2]);
DB::table('inventario_bodegas_sucursales')->insert(['bodega_id'=>3,'sucursal_id'=>4]);
DB::table('inventario_reservas')->insert(['id'=>1,'estado'=>'PARCIAL','nota_venta_id'=>8,'sucursal_id'=>4]);
DB::table('inventario_reservas_detalle')->insert(['id'=>1,'reserva_id'=>1,'producto_id'=>2,'bodega_id'=>3,'cantidad_reservada'=>'4.12345678','cantidad_despachada'=>'0','cantidad_liberada'=>'1.12345678']);
DB::table('stock')->insert(['id'=>1,'producto_id'=>2,'bodega_id'=>3,'disponible'=>'7','transito'=>'3','comprometido'=>'5','cantidad_fisica'=>'15']);
DB::table('estados')->insert([['id'=>1,'codigo'=>'INV_CONFIRMADA'],['id'=>2,'codigo'=>'INV_APROBADA']]);
foreach([[10,20,30,710,'RESERVAR','4.12345678','-4.12345678','4.12345678','10','5.87654322','2','6.12345678'],[11,21,31,711,'LIBERAR_RESERVA','1.12345678','1.12345678','-1.12345678','5.87654322','7','6.12345678','5']] as [$oid,$kid,$did,$code,$rule,$q,$da,$dc,$a0,$a1,$c0,$c1]){
 DB::table('inventario_reservas_operaciones')->insert(['reserva_id'=>1,'operacion_id'=>$oid]);
 DB::table('inventario_operaciones')->insert(['id'=>$oid,'clase'=>'RESERVA','sucursal_id'=>4,'estado_id'=>1,'motivo_codigo_snapshot'=>$code,'regla_snapshot'=>$rule,'politica_cpp_snapshot'=>'CONSERVAR','creado_por'=>5,'aprobado_por'=>6,'confirmado_por'=>7,'token_confirmacion'=>'ci-'.$oid]);
 DB::table('inventario_operaciones_detalle')->insert(['id'=>$did,'operacion_id'=>$oid,'producto_id'=>2,'bodega_id'=>3,'cantidad'=>$q,'delta_disponible'=>$da,'delta_comprometido'=>$dc,'delta_transito'=>'0','delta_fisico'=>'0','costo_unitario_snapshot'=>'120','kardex_id'=>$kid]);
 DB::table('kardex')->insert(['id'=>$kid,'producto_id'=>2,'bodega_id'=>3,'sucursal_id'=>4,'origen_tipo'=>'AJUSTE_INVENTARIO','origen_detalle_id'=>$did,'operacion_codigo'=>$rule,'cantidad_documento'=>$q,'costo_unitario_neto'=>'120','cpp_anterior'=>'120','cpp_actual'=>'120','cantidad_anterior'=>$a0,'cantidad_actual'=>$a1,'comprometido_anterior'=>$c0,'comprometido_actual'=>$c1,'transito_anterior'=>'3','transito_actual'=>'3','fisico_anterior'=>'15','fisico_actual'=>'15']);
}

$m=new \App\Services\ConciliadorComprometidoReservas();
$read=fn()=>DB::transaction(fn()=>$m->verificar(2,3,4));
reject(fn()=>$m->verificar(2,3,4),500);
reject($read); // 5 comprometidos, solo 3 acreditados.
DB::table('stock')->where('id',1)->update(['comprometido'=>'3','cantidad_fisica'=>'13']);
$p=$read();check($p['pendiente_acreditado']==='3.00000000'&&$p['reservas']===[1],'Saldo agregado acreditado');
DB::table('inventario_reservas')->insert(['id'=>2,'estado'=>'PENDIENTE','nota_venta_id'=>9,'sucursal_id'=>4]);
DB::table('inventario_reservas_detalle')->insert(['id'=>2,'reserva_id'=>2,'producto_id'=>2,'bodega_id'=>3,'cantidad_reservada'=>'2','cantidad_despachada'=>'0','cantidad_liberada'=>'0']);
reject($read);
DB::table('estados')->insert(['id'=>3,'codigo'=>'INV_PEND_APROBACION']);
DB::table('inventario_operaciones')->insert(['id'=>12,'clase'=>'RESERVA','sucursal_id'=>4,'estado_id'=>3,'motivo_codigo_snapshot'=>710,'regla_snapshot'=>'RESERVAR','politica_cpp_snapshot'=>'CONSERVAR','creado_por'=>5]);
DB::table('inventario_reservas_operaciones')->insert(['reserva_id'=>2,'operacion_id'=>12]);
DB::table('inventario_operaciones_detalle')->insert(['id'=>32,'operacion_id'=>12,'producto_id'=>2,'bodega_id'=>3,'cantidad'=>'2','delta_disponible'=>'-2','delta_comprometido'=>'2','delta_transito'=>'0','delta_fisico'=>'0','costo_unitario_snapshot'=>'120']);
check($read()['reservas']===[1],'Solicitud pendiente no compromete stock');
DB::table('inventario_operaciones_detalle')->where('id',32)->update(['kardex_id'=>20]);reject($read);
DB::table('inventario_operaciones_detalle')->where('id',32)->update(['kardex_id'=>null]);
DB::table('inventario_reservas')->where('id',2)->update(['sucursal_id'=>99]);reject($read);
DB::table('inventario_reservas')->where('id',2)->update(['sucursal_id'=>4,'estado'=>'CONSUMIDA']);reject($read);
DB::table('inventario_reservas')->where('id',2)->update(['estado'=>'PENDIENTE']);
check($read()['comprometido']==='3.00000000','Conciliación reproducible');
echo "V109E3B1_COMPROMETIDO_OK\n";
