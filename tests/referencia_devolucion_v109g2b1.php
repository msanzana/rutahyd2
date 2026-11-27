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
check(config('app.env')==='testing'&&DB::getDatabaseName()===($mysql?'rutahyd_v109g2b1_ci':':memory:'),'Solo CI aislado');
if($mysql)check(DB::selectOne('SELECT DATABASE() AS db')->db==='rutahyd_v109g2b1_ci','Base incorrecta');
foreach([
'CREATE TABLE productos(id BIGINT PRIMARY KEY,activo INTEGER)',
'CREATE TABLE inventario_bodegas_sucursales(bodega_id BIGINT,sucursal_id BIGINT)',
'CREATE TABLE inventario_reservas(id BIGINT PRIMARY KEY,estado VARCHAR(30),nota_venta_id BIGINT,sucursal_id BIGINT)',
'CREATE TABLE inventario_reservas_detalle(id BIGINT PRIMARY KEY,reserva_id BIGINT,producto_id BIGINT,bodega_id BIGINT,cantidad_reservada TEXT,cantidad_despachada TEXT,cantidad_liberada TEXT)',
'CREATE TABLE inventario_reservas_operaciones(reserva_id BIGINT,operacion_id BIGINT,PRIMARY KEY(reserva_id,operacion_id))',
'CREATE TABLE stock(id BIGINT PRIMARY KEY,producto_id BIGINT,bodega_id BIGINT,disponible TEXT,transito TEXT,comprometido TEXT,cantidad_fisica TEXT)',
'CREATE TABLE estados(id BIGINT PRIMARY KEY,codigo VARCHAR(30))',
'CREATE TABLE inventario_operaciones(id BIGINT PRIMARY KEY,clase VARCHAR(30),sucursal_id BIGINT,estado_id BIGINT,motivo_codigo_snapshot INTEGER,regla_snapshot VARCHAR(60),politica_cpp_snapshot VARCHAR(30),creado_por BIGINT,aprobado_por BIGINT,confirmado_por BIGINT,token_confirmacion VARCHAR(60),referencia_tipo_documento_id BIGINT,referencia_folio VARCHAR(60),referencia_kardex_id BIGINT,anulado_por BIGINT)',
'CREATE TABLE inventario_operaciones_detalle(id BIGINT PRIMARY KEY,operacion_id BIGINT,producto_id BIGINT,bodega_id BIGINT,cantidad TEXT,delta_disponible TEXT,delta_comprometido TEXT,delta_transito TEXT,delta_fisico TEXT,costo_unitario_snapshot TEXT,kardex_id BIGINT)',
'CREATE TABLE kardex(id BIGINT PRIMARY KEY,producto_id BIGINT,bodega_id BIGINT,sucursal_id BIGINT,origen_tipo VARCHAR(30),origen_detalle_id BIGINT,operacion_codigo VARCHAR(60),cantidad_documento TEXT,costo_unitario_neto TEXT,cpp_anterior TEXT,cpp_actual TEXT,cantidad_anterior TEXT,cantidad_actual TEXT,comprometido_anterior TEXT,comprometido_actual TEXT,transito_anterior TEXT,transito_actual TEXT,fisico_anterior TEXT,fisico_actual TEXT,tipo_movimiento INTEGER,tipo_documento_id BIGINT,folio_documento VARCHAR(60),movimiento_reversado_id BIGINT)'
] as $sql){
 if($mysql)$sql=str_replace(' TEXT',' DECIMAL(28,8)',$sql);
 DB::statement($sql);
}
DB::table('productos')->insert(['id'=>2,'activo'=>1]);
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

foreach([
'CREATE TABLE notas_venta(id BIGINT PRIMARY KEY,sucursal_id BIGINT)',
'CREATE TABLE guias_despacho(id BIGINT PRIMARY KEY,folio_interno VARCHAR(60),nota_venta_id BIGINT,estado VARCHAR(30),bodega_origen_id BIGINT,bodega_destino_id BIGINT)',
'CREATE TABLE presentaciones_productos(id BIGINT PRIMARY KEY,producto_id BIGINT,unidades_por_envase DECIMAL(15,3))',
'CREATE TABLE detalles_guias_despacho(id BIGINT PRIMARY KEY,guia_despacho_id BIGINT,presentacion_id BIGINT,cantidad DECIMAL(28,8))'
] as $sql)DB::statement($sql);
DB::table('notas_venta')->insert(['id'=>8,'sucursal_id'=>4]);
DB::table('presentaciones_productos')->insert(['id'=>1,'producto_id'=>2,'unidades_por_envase'=>'1']);
function dispatch(int $oid,int $kid,int $did,int $guide,string $folio,string $q,string $c0,string $c1,string $f0,string $f1):void{
 DB::table('guias_despacho')->insert(['id'=>$guide,'folio_interno'=>$folio,'nota_venta_id'=>8,'estado'=>'DESPACHADA_CI','bodega_origen_id'=>3,'bodega_destino_id'=>null]);
 DB::table('detalles_guias_despacho')->insert(['id'=>$guide,'guia_despacho_id'=>$guide,'presentacion_id'=>1,'cantidad'=>$q]);
 DB::table('inventario_reservas_operaciones')->insert(['reserva_id'=>1,'operacion_id'=>$oid]);
 DB::table('inventario_operaciones')->insert(['id'=>$oid,'clase'=>'RESERVA','sucursal_id'=>4,'estado_id'=>1,'motivo_codigo_snapshot'=>712,'regla_snapshot'=>'DESPACHAR','politica_cpp_snapshot'=>'VIGENTE','creado_por'=>5,'aprobado_por'=>6,'confirmado_por'=>7,'token_confirmacion'=>'ci-'.$oid,'referencia_tipo_documento_id'=>42,'referencia_folio'=>$folio]);
 $neg=(string)\Brick\Math\BigDecimal::of($q)->negated();
 DB::table('inventario_operaciones_detalle')->insert(['id'=>$did,'operacion_id'=>$oid,'producto_id'=>2,'bodega_id'=>3,'cantidad'=>$q,'delta_disponible'=>'0','delta_comprometido'=>$neg,'delta_transito'=>'0','delta_fisico'=>$neg,'costo_unitario_snapshot'=>'125','kardex_id'=>$kid]);
 DB::table('kardex')->insert(['id'=>$kid,'producto_id'=>2,'bodega_id'=>3,'sucursal_id'=>4,'origen_tipo'=>'AJUSTE_INVENTARIO','origen_detalle_id'=>$did,'operacion_codigo'=>'DESPACHAR','cantidad_documento'=>$q,'costo_unitario_neto'=>'125','cpp_anterior'=>'125','cpp_actual'=>'125','cantidad_anterior'=>'7','cantidad_actual'=>'7','comprometido_anterior'=>$c0,'comprometido_actual'=>$c1,'transito_anterior'=>'3','transito_actual'=>'3','fisico_anterior'=>$f0,'fisico_actual'=>$f1,'tipo_movimiento'=>6,'tipo_documento_id'=>42,'folio_documento'=>$folio]);
}
dispatch(12,22,32,1,'GD-CI-1','2','3','1','13','11');
DB::table('inventario_reservas_detalle')->where('id',1)->update(['cantidad_despachada'=>'2']);
DB::table('stock')->where('id',1)->update(['comprometido'=>'1','cantidad_fisica'=>'11']);
$m=new LectorSaldoReservaInventario();$read=fn()=>DB::transaction(fn()=>$m->leer(1,2,3,4));
$agg=fn()=>DB::transaction(fn()=>(new \App\Services\ConciliadorComprometidoReservas())->verificar(2,3,4));
reject($read);reject($agg);
config(['reservas.consumo_lectura_verificado'=>true,'reservas.estado_consumida'=>'CONSUMIDA','reservas.tipo_kardex_despachar'=>6,'reservas.tipo_documento_guia'=>42,'reservas.estado_guia_despachada'=>'DESPACHADA_CI']);
$x=$read();check($x['despachada']==='2.00000000'&&$x['pendiente']==='1.00000000','Consumo parcial acreditado');
check($agg()['pendiente_acreditado']==='1.00000000','Agregado exacto');

DB::statement('CREATE TABLE bodegas(id BIGINT PRIMARY KEY,activo INTEGER)');
DB::table('bodegas')->insert(['id'=>3,'activo'=>1]);
$svc=new \App\Services\ReferenciaDevolucionDespacho();
$get=fn()=>DB::transaction(fn()=>$svc->verificar(22,4,601));
reject($get);
config(['devoluciones.referencias_verificadas'=>true,'devoluciones.integracion_comercial_verificada'=>true,'devoluciones.aislamiento_verificado'=>true,'devoluciones.origenes_verificados'=>[601=>['origen'=>'DESPACHO_RESERVA_712']]]);
$x=$get();check($x['cantidad_original']==='2.00000000'&&$x['costo_historico']==='125.00000000'&&$x['guia_id']===1&&$x['nota_venta_id']===8,'Origen histórico acreditado');
check($x['documento_tributario_acreditado']===false&&$x['recepcion_devolucion_acreditada']===false,'No presume DTE ni devolución física');
foreach([['guias_despacho',1,'estado','BORRADOR'],['kardex',22,'cpp_anterior','124'],['inventario_operaciones',12,'aprobado_por',5],['notas_venta',8,'sucursal_id',99],['presentaciones_productos',1,'unidades_por_envase','2']] as [$t,$id,$field,$bad]){
 $old=DB::table($t)->where('id',$id)->value($field);
 DB::table($t)->where('id',$id)->update([$field=>$bad]);reject($get);DB::table($t)->where('id',$id)->update([$field=>$old]);
}
DB::table('estados')->insert(['id'=>3,'codigo'=>'INV_ANULADA']);
DB::table('inventario_operaciones')->insert(['id'=>99,'sucursal_id'=>4,'estado_id'=>2,'referencia_kardex_id'=>22]);reject($get);
DB::table('inventario_operaciones')->where('id',99)->update(['estado_id'=>3]);check($get()['saldo_solicitable']==='2.00000000','Anulada no consume');
DB::table('kardex')->insert(['id'=>99,'movimiento_reversado_id'=>22]);reject($get);DB::table('kardex')->where('id',99)->delete();
check(DB::table('stock')->where('id',1)->value('comprometido')=='1'&&DB::table('kardex')->count()===3,'Lectura sin movimiento');
echo "V109G2B1_ORIGEN_OK\n";
