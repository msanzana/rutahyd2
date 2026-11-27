<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\ReferenciaDevolucionCompra;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
function reject(callable $f,int $status=409):void{try{$f();}catch(HttpExceptionInterface $e){check($e->getStatusCode()===$status,'HTTP inesperado: '.$e->getMessage());return;}throw new RuntimeException('No rechazado');}
$mysql=DB::connection()->getDriverName()==='mysql';
check(config('app.env')==='testing'&&DB::getDatabaseName()===($mysql?'rutahyd_v109g2b2b_ci':':memory:'),'Solo CI aislado');
if($mysql)check(DB::selectOne('SELECT DATABASE() AS db')->db==='rutahyd_v109g2b2b_ci','Base incorrecta');
foreach([
'CREATE TABLE productos(id BIGINT PRIMARY KEY,activo INTEGER)',
'CREATE TABLE bodegas(id BIGINT PRIMARY KEY,activo INTEGER)',
'CREATE TABLE inventario_bodegas_sucursales(bodega_id BIGINT,sucursal_id BIGINT)',
'CREATE TABLE estados(id BIGINT PRIMARY KEY,codigo VARCHAR(40))',
'CREATE TABLE tipo_documentos(id BIGINT PRIMARY KEY,codigo VARCHAR(60),codigo_sii INTEGER,documento_compra INTEGER,activo INTEGER,familia VARCHAR(30))',
'CREATE TABLE ordenes_compra(id BIGINT PRIMARY KEY,sucursal_id BIGINT,proveedor_id BIGINT,estado_id BIGINT)',
'CREATE TABLE detalles_ordenes_compra(id BIGINT PRIMARY KEY,orden_compra_id BIGINT,producto_id BIGINT)',
'CREATE TABLE compras(id BIGINT PRIMARY KEY,sucursal_id BIGINT,orden_compra_id BIGINT,proveedor_id BIGINT,estado_id BIGINT,documento_id BIGINT,folio VARCHAR(60))',
'CREATE TABLE recepciones(id BIGINT PRIMARY KEY,sucursal_id BIGINT,bodega_id BIGINT,compra_id BIGINT,orden_compra_id BIGINT,proveedor_id BIGINT,estado_id BIGINT,stock_aplicado INTEGER,token_confirmacion VARCHAR(60),recepcion_consolidada_id BIGINT,recepcion_tipo INTEGER)',
'CREATE TABLE recepcion_detalle(id BIGINT PRIMARY KEY,recepcion_id BIGINT,producto_id BIGINT,detalle_orden_compra_id BIGINT,presentacion_id BIGINT,unidades_por_presentacion_snapshot DECIMAL(28,8),cantidad_aceptada DECIMAL(28,8),cantidad_recibida DECIMAL(28,8),cantidad_rechazada DECIMAL(28,8),costo_unitario_neto DECIMAL(28,8))',
'CREATE TABLE recepciones_consolidadas(id BIGINT PRIMARY KEY,sucursal_id BIGINT,bodega_id BIGINT,orden_compra_id BIGINT,proveedor_id BIGINT,estado_id BIGINT,token_confirmacion VARCHAR(60),consolidado_por BIGINT,numero_interno VARCHAR(60))',
'CREATE TABLE recepciones_consolidadas_detalle(id BIGINT PRIMARY KEY,recepcion_consolidada_id BIGINT,producto_id BIGINT,detalle_orden_compra_id BIGINT,cantidad_aceptada DECIMAL(28,8),costo_unitario_neto_ponderado DECIMAL(28,8))',
'CREATE TABLE inventario_operaciones(id BIGINT PRIMARY KEY,referencia_kardex_id BIGINT,estado_id BIGINT,confirmado_por BIGINT,token_confirmacion VARCHAR(60),sucursal_id BIGINT,clase VARCHAR(30),motivo_codigo_snapshot INTEGER,regla_snapshot VARCHAR(60),politica_cpp_snapshot VARCHAR(30),referencia_tipo_documento_id BIGINT,referencia_folio VARCHAR(60),creado_por BIGINT,aprobado_por BIGINT,anulado_por BIGINT,token_creacion VARCHAR(60),numero_interno VARCHAR(60))',
'CREATE TABLE inventario_operaciones_detalle(id BIGINT PRIMARY KEY,operacion_id BIGINT,kardex_id BIGINT,kardex_referencia_id BIGINT,producto_id BIGINT,bodega_id BIGINT,cantidad DECIMAL(28,8),costo_unitario_snapshot DECIMAL(28,8),delta_disponible DECIMAL(28,8),delta_fisico DECIMAL(28,8),delta_transito DECIMAL(28,8),delta_comprometido DECIMAL(28,8))',
'CREATE TABLE kardex(id BIGINT PRIMARY KEY,sucursal_id BIGINT,producto_id BIGINT,bodega_id BIGINT,tipo_movimiento INTEGER,origen_tipo VARCHAR(40),origen_detalle_id BIGINT,operacion_codigo VARCHAR(60),tipo_documento_id BIGINT,folio_documento VARCHAR(60),token_idempotencia VARCHAR(60),movimiento_reversado_id BIGINT,cantidad_documento DECIMAL(28,8),costo_unitario_neto DECIMAL(28,8),cantidad_anterior DECIMAL(28,8),cantidad_actual DECIMAL(28,8),transito_anterior DECIMAL(28,8),transito_actual DECIMAL(28,8),comprometido_anterior DECIMAL(28,8),comprometido_actual DECIMAL(28,8),fisico_anterior DECIMAL(28,8),fisico_actual DECIMAL(28,8),cpp_anterior DECIMAL(28,8))'
] as $sql){
 // SQLite conserva decimales como texto para simular el retorno exacto de MySQL DECIMAL.
 if(!$mysql)$sql=str_replace('DECIMAL(28,8)','TEXT',$sql);
 DB::statement($sql);
}
DB::table('productos')->insert(['id'=>2,'activo'=>1]);DB::table('bodegas')->insert(['id'=>3,'activo'=>1]);
DB::table('inventario_bodegas_sucursales')->insert(['bodega_id'=>3,'sucursal_id'=>4]);
DB::table('estados')->insert([['id'=>1,'codigo'=>'RECEPCIONADA'],['id'=>2,'codigo'=>'CONSOLIDADA'],['id'=>3,'codigo'=>'INV_APROBADA'],['id'=>4,'codigo'=>'INV_ANULADA']]);
DB::table('tipo_documentos')->insert([['id'=>1,'codigo'=>'FACTURA_CI','codigo_sii'=>33,'documento_compra'=>1,'activo'=>1,'familia'=>'TRIBUTARIO'],['id'=>2,'codigo'=>'CONSOLIDACION_RECEPCION','codigo_sii'=>0,'documento_compra'=>1,'activo'=>1,'familia'=>'INTERNO']]);
DB::table('ordenes_compra')->insert(['id'=>1,'sucursal_id'=>4,'proveedor_id'=>5,'estado_id'=>1]);
DB::table('detalles_ordenes_compra')->insert(['id'=>1,'orden_compra_id'=>1,'producto_id'=>2]);
DB::table('compras')->insert(['id'=>1,'sucursal_id'=>4,'orden_compra_id'=>1,'proveedor_id'=>5,'estado_id'=>1,'documento_id'=>1,'folio'=>'000045']);
DB::table('recepciones')->insert(['id'=>1,'sucursal_id'=>4,'bodega_id'=>3,'compra_id'=>1,'orden_compra_id'=>1,'proveedor_id'=>5,'estado_id'=>1,'stock_aplicado'=>1,'token_confirmacion'=>'CI-recepcion','recepcion_tipo'=>1]);
DB::table('recepcion_detalle')->insert(['id'=>1,'recepcion_id'=>1,'producto_id'=>2,'detalle_orden_compra_id'=>1,'presentacion_id'=>null,'unidades_por_presentacion_snapshot'=>'1','cantidad_aceptada'=>'2.125','cantidad_recibida'=>'2.125','cantidad_rechazada'=>'0','costo_unitario_neto'=>'123.4567']);
function movimiento(int $id,string $rule,int $tipo,string $a0,string $a1,string $t0,string $t1,string $f0,string $f1,string $origin='RECEPCION',int $doc=1,string $folio='000045'):array{
 return ['id'=>$id,'sucursal_id'=>4,'producto_id'=>2,'bodega_id'=>3,'tipo_movimiento'=>$tipo,'origen_tipo'=>$origin,'origen_detalle_id'=>1,'operacion_codigo'=>$rule,'tipo_documento_id'=>$doc,'folio_documento'=>$folio,'token_idempotencia'=>'CI-'.$id,'cantidad_documento'=>'2.125','costo_unitario_neto'=>'123.4567','cantidad_anterior'=>$a0,'cantidad_actual'=>$a1,'transito_anterior'=>$t0,'transito_actual'=>$t1,'comprometido_anterior'=>'1','comprometido_actual'=>'1','fisico_anterior'=>$f0,'fisico_actual'=>$f1];
}
DB::table('kardex')->insert(movimiento(11,'ENTRADA_DIRECTA',1,'5','7.125','0','0','6','8.125'));
$svc=new ReferenciaDevolucionCompra();$read=fn()=>DB::transaction(fn()=>$svc->verificar(11,4,602));reject($read);
config(['devoluciones.referencias_verificadas'=>true,'devoluciones.integracion_comercial_verificada'=>true,'devoluciones.aislamiento_verificado'=>true,'devoluciones.origenes_verificados'=>[602=>['origen'=>'COMPRA_DISPONIBLE']]]);
$x=$read();check($x['cantidad_original']==='2.12500000'&&$x['costo_especifico']==='123.45670000'&&$x['folio']==='000045'&&$x['nota_credito_acreditada']===false,'Compra directa acreditada');

DB::table('estados')->insert([['id'=>5,'codigo'=>'INV_CONFIRMADA'],['id'=>6,'codigo'=>'INV_PEND_APROBACION']]);
config(['devoluciones.historial_verificado'=>true,'devoluciones.clase_operacion'=>'AJUSTE','devoluciones.tipo_documento_interno'=>2,
    'devoluciones.contratos'=>[602=>['regla'=>'DEVOLUCION_CI','indicador'=>7]]]);
$x=$read();check($x['historial']['historial_acreditado']===true&&$x['saldo_solicitable']==='2.12500000','Historial vacío acreditado');
function devolucion(int $id,int $estado,string $cantidad,?int $kardex=null):void{
 DB::table('inventario_operaciones')->insert(['id'=>$id,'referencia_kardex_id'=>11,'estado_id'=>$estado,'sucursal_id'=>4,'clase'=>'AJUSTE',
 'motivo_codigo_snapshot'=>602,'regla_snapshot'=>'DEVOLUCION_CI','politica_cpp_snapshot'=>'ESPECIFICA','referencia_tipo_documento_id'=>1,
 'referencia_folio'=>'000045','creado_por'=>7,'aprobado_por'=>in_array($estado,[3,5],true)?8:null,'anulado_por'=>$estado===4?8:null,
 'token_creacion'=>'CREA-'.$id,'numero_interno'=>'DEV-CI-'.$id,'confirmado_por'=>$estado===5?8:null,'token_confirmacion'=>$estado===5?'CONF-'.$id:null]);
 DB::table('inventario_operaciones_detalle')->insert(['id'=>$id,'operacion_id'=>$id,'kardex_id'=>$kardex,'kardex_referencia_id'=>11,
 'producto_id'=>2,'bodega_id'=>3,'cantidad'=>$cantidad,'costo_unitario_snapshot'=>'123.4567','delta_disponible'=>'-'.$cantidad,
 'delta_fisico'=>'-'.$cantidad,'delta_transito'=>'0','delta_comprometido'=>'0']);
}
devolucion(20,5,'1',12);
DB::table('kardex')->insert(array_replace(movimiento(12,'DEVOLUCION_CI',2,'7.125','6.125','0','0','8.125','7.125','AJUSTE_INVENTARIO',2,'DEV-CI-20'),['origen_detalle_id'=>20,'cantidad_documento'=>'1','cpp_anterior'=>'150']));
devolucion(21,3,'0.5');devolucion(22,4,'2');
$x=$read();check($x['historial']['aplicada']==='1.00000000'&&$x['historial']['solicitada_pendiente']==='0.50000000'&&$x['saldo_solicitable']==='0.62500000','Parciales y anuladas');
$own=DB::transaction(fn()=>$svc->verificar(11,4,602,21));
check($own['historial']['cantidad_propia']==='0.50000000'&&$own['historial']['disponible_para_propia']==='1.12500000','Revalidar propia sin duplicar cantidad');
reject(fn()=>DB::transaction(fn()=>$svc->verificar(11,4,602,20)));
devolucion(23,6,'0.62500001');reject($read);DB::table('inventario_operaciones_detalle')->where('id',23)->update(['cantidad'=>'0.625','delta_disponible'=>'-0.625','delta_fisico'=>'-0.625']);
check($read()['saldo_solicitable']==='0.00000000','Límite exacto');
DB::table('inventario_operaciones')->where('id',23)->update(['estado_id'=>4,'anulado_por'=>8]);check($read()['saldo_solicitable']==='0.62500000','Anulación libera saldo documental');
foreach([['inventario_operaciones',20,'referencia_folio','OTRO'],['inventario_operaciones',20,'sucursal_id',99],
 ['inventario_operaciones_detalle',20,'delta_fisico','0'],['inventario_operaciones_detalle',21,'kardex_referencia_id',99],
 ['kardex',12,'cantidad_actual','7.125'],['kardex',12,'costo_unitario_neto','999']] as [$t,$id,$field,$bad]){
 $old=DB::table($t)->where('id',$id)->value($field);DB::table($t)->where('id',$id)->update([$field=>$bad]);reject($read);DB::table($t)->where('id',$id)->update([$field=>$old]);
}
DB::table('inventario_operaciones_detalle')->insert(['id'=>99,'operacion_id'=>99,'kardex_referencia_id'=>11]);reject($read);DB::table('inventario_operaciones_detalle')->where('id',99)->delete();
DB::table('kardex')->insert(['id'=>99,'movimiento_reversado_id'=>12]);reject($read);DB::table('kardex')->where('id',99)->delete();
check(DB::table('kardex')->count()===2,'Lectura no genera movimientos');
echo "V109G2B2B_HISTORIAL_OK\n";
