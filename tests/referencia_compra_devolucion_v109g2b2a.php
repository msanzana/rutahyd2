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
check(config('app.env')==='testing'&&DB::getDatabaseName()===($mysql?'rutahyd_v109g2b2a_ci':':memory:'),'Solo CI aislado');
if($mysql)check(DB::selectOne('SELECT DATABASE() AS db')->db==='rutahyd_v109g2b2a_ci','Base incorrecta');
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
'CREATE TABLE inventario_operaciones(id BIGINT PRIMARY KEY,referencia_kardex_id BIGINT,estado_id BIGINT,confirmado_por BIGINT,token_confirmacion VARCHAR(60))',
'CREATE TABLE inventario_operaciones_detalle(id BIGINT PRIMARY KEY,operacion_id BIGINT,kardex_id BIGINT)',
'CREATE TABLE kardex(id BIGINT PRIMARY KEY,sucursal_id BIGINT,producto_id BIGINT,bodega_id BIGINT,tipo_movimiento INTEGER,origen_tipo VARCHAR(40),origen_detalle_id BIGINT,operacion_codigo VARCHAR(60),tipo_documento_id BIGINT,folio_documento VARCHAR(60),token_idempotencia VARCHAR(60),movimiento_reversado_id BIGINT,cantidad_documento DECIMAL(28,8),costo_unitario_neto DECIMAL(28,8),cantidad_anterior DECIMAL(28,8),cantidad_actual DECIMAL(28,8),transito_anterior DECIMAL(28,8),transito_actual DECIMAL(28,8),comprometido_anterior DECIMAL(28,8),comprometido_actual DECIMAL(28,8),fisico_anterior DECIMAL(28,8),fisico_actual DECIMAL(28,8))'
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
DB::table('compras')->where('id',1)->update(['sucursal_id'=>99]);reject($read);DB::table('compras')->where('id',1)->update(['sucursal_id'=>4]);
DB::table('inventario_operaciones')->insert(['id'=>1,'referencia_kardex_id'=>11,'estado_id'=>3]);reject($read);
DB::table('inventario_operaciones')->where('id',1)->update(['estado_id'=>4]);check($read()['compra_id']===1,'Anulada sin movimiento');
DB::table('kardex')->where('id',11)->delete();
DB::table('kardex')->insert(movimiento(10,'ENTRADA_TRANSITO',3,'5','5','0','2.125','6','8.125'));
DB::table('kardex')->insert(movimiento(11,'LIBERAR_TRANSITO_DIRECTO',1,'5','7.125','2.125','0','8.125','8.125'));
check($read()['compra_id']===1,'Liberación de tránsito acreditada');
reject(fn()=>DB::transaction(fn()=>$svc->verificar(10,4,602)));
DB::table('kardex')->where('id',10)->update(['costo_unitario_neto'=>'125']);reject($read);DB::table('kardex')->where('id',10)->update(['costo_unitario_neto'=>'123.4567']);
DB::table('recepciones_consolidadas')->insert(['id'=>1,'sucursal_id'=>4,'bodega_id'=>3,'orden_compra_id'=>1,'proveedor_id'=>5,'estado_id'=>2,'token_confirmacion'=>'CI-consolida','consolidado_por'=>7,'numero_interno'=>'CON-CI']);
DB::table('recepciones_consolidadas_detalle')->insert(['id'=>1,'recepcion_consolidada_id'=>1,'producto_id'=>2,'detalle_orden_compra_id'=>1,'cantidad_aceptada'=>'2.125','costo_unitario_neto_ponderado'=>'123.4567']);
DB::table('recepciones')->where('id',1)->update(['recepcion_consolidada_id'=>1]);
DB::table('kardex')->where('id',11)->delete();
DB::table('kardex')->insert(movimiento(11,'CONSOLIDAR_TRANSITO',1,'5','7.125','2.125','0','8.125','8.125','CONSOLIDACION_RECEPCION',2,'CON-CI'));
check($read()['consolidacion_id']===1&&$read()['folio']==='000045','Consolidación atribuible a compra individual');
$d=(array)DB::table('recepcion_detalle')->where('id',1)->first();$d['id']=2;DB::table('recepcion_detalle')->insert($d);reject($read);DB::table('recepcion_detalle')->where('id',2)->delete();
DB::table('kardex')->insert(['id'=>12,'movimiento_reversado_id'=>11]);reject($read);DB::table('kardex')->where('id',12)->delete();
check(DB::table('kardex')->count()===2,'Verificación sin movimientos adicionales');
echo "V109G2B2A_COMPRA_OK\n";
