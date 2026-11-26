<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
function ok(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
ok(config('app.env')==='testing'&&DB::getDatabaseName()===':memory:','Solo entorno CI aislado');
foreach([
 'CREATE TABLE sucursales(id INTEGER PRIMARY KEY,activo INTEGER,codigo TEXT)',
 'CREATE TABLE roles(id INTEGER PRIMARY KEY,codigo TEXT)',
 'CREATE TABLE usuarios(id INTEGER PRIMARY KEY,rol_id INTEGER,activo INTEGER)',
 'CREATE TABLE tokens_acceso(id INTEGER PRIMARY KEY,usuario_id INTEGER,expira_en TEXT)',
 'CREATE TABLE sesiones_sucursales(token_acceso_id INTEGER PRIMARY KEY,sucursal_id INTEGER)',
 'CREATE TABLE usuarios_sucursales(usuario_id INTEGER,sucursal_id INTEGER,activo INTEGER)',
 'CREATE TABLE inventario_bodegas_sucursales(bodega_id INTEGER PRIMARY KEY,sucursal_id INTEGER)',
 'CREATE TABLE bodegas(id INTEGER PRIMARY KEY,nombre TEXT,activo INTEGER)',
 'CREATE TABLE productos(id INTEGER PRIMARY KEY,sku TEXT,nombre TEXT)',
 'CREATE TABLE estados(id INTEGER PRIMARY KEY,codigo TEXT)',
 'CREATE TABLE proveedores(id INTEGER PRIMARY KEY,nombre TEXT,rut TEXT)',
 'CREATE TABLE tipo_documentos(id INTEGER PRIMARY KEY,nombre TEXT)',
 'CREATE TABLE ordenes_compra(id INTEGER PRIMARY KEY,sucursal_id INTEGER,numero TEXT)',
 'CREATE TABLE compras(id INTEGER PRIMARY KEY,sucursal_id INTEGER,estado_id INTEGER,proveedor_id INTEGER,documento_id INTEGER,orden_compra_id INTEGER,folio TEXT,fecha_documento TEXT,total TEXT,total_informado TEXT,signo_contable INTEGER)',
 'CREATE TABLE recepciones(id INTEGER PRIMARY KEY,sucursal_id INTEGER,estado_id INTEGER,proveedor_id INTEGER,bodega_id INTEGER,orden_compra_id INTEGER,compra_id INTEGER,recepcion_consolidada_id INTEGER,numero_interno TEXT,fecha_recepcion TEXT,total TEXT,subtotal_neto TEXT,total_descuentos TEXT,total_exento TEXT,total_iva TEXT,otros_impuestos TEXT,observaciones TEXT)',
 'CREATE TABLE recepciones_consolidadas(id INTEGER PRIMARY KEY,sucursal_id INTEGER,estado_id INTEGER,proveedor_id INTEGER,bodega_id INTEGER,orden_compra_id INTEGER,numero_interno TEXT,fecha_consolidacion TEXT,total TEXT,subtotal_neto TEXT,total_descuentos TEXT,total_exento TEXT,total_iva TEXT,otros_impuestos TEXT,observaciones TEXT)',
 'CREATE TABLE solicitudes_regularizacion(id INTEGER PRIMARY KEY,recepcion_id INTEGER,recepcion_consolidada_id INTEGER,estado TEXT)',
 'CREATE TABLE stock(id INTEGER PRIMARY KEY,producto_id INTEGER,bodega_id INTEGER,disponible TEXT,transito TEXT,comprometido TEXT,cantidad_fisica TEXT)',
 'CREATE TABLE kardex(id INTEGER PRIMARY KEY,sucursal_id INTEGER,producto_id INTEGER,bodega_id INTEGER,fecha_movimiento TEXT,operacion_codigo TEXT,tipo_documento_id INTEGER,folio_documento TEXT,cantidad_documento TEXT,fisico_anterior TEXT,fisico_actual TEXT,costo_unitario_neto TEXT)',
 'CREATE TABLE libro_compras(id INTEGER PRIMARY KEY,compra_id INTEGER,documento_id INTEGER,periodo_tributario TEXT,folio TEXT,fecha_documento TEXT,rut_emisor TEXT,razon_social_emisor TEXT,monto_neto TEXT,monto_exento TEXT,monto_iva_recuperable TEXT,monto_iva_no_recuperable TEXT,monto_iva_uso_comun TEXT,monto_impuestos_adicionales TEXT,monto_retenciones TEXT,monto_total TEXT,signo_contable INTEGER,estado_rcv TEXT)',
 'CREATE TABLE recepcion_detalle(id INTEGER PRIMARY KEY,recepcion_id INTEGER,producto_id INTEGER,numero_linea INTEGER)',
 'CREATE TABLE recepcion_diferencias(id INTEGER PRIMARY KEY,recepcion_id INTEGER)'
] as $sql)DB::statement($sql);
DB::table('sucursales')->insert(['id'=>1,'activo'=>1,'codigo'=>'S01']);
DB::table('roles')->insert(['id'=>1,'codigo'=>'operador']);
DB::table('usuarios')->insert(['id'=>1,'rol_id'=>1,'activo'=>1]);
DB::table('tokens_acceso')->insert(['id'=>1,'usuario_id'=>1,'expira_en'=>now()->addHour()]);
DB::table('sesiones_sucursales')->insert(['token_acceso_id'=>1,'sucursal_id'=>1]);
DB::table('usuarios_sucursales')->insert(['usuario_id'=>1,'sucursal_id'=>1,'activo'=>1]);
foreach([1,2,3] as $id)DB::table('bodegas')->insert(['id'=>$id,'nombre'=>'CI '.$id,'activo'=>$id===3?0:1]);
foreach([1,3] as $id)DB::table('inventario_bodegas_sucursales')->insert(['bodega_id'=>$id,'sucursal_id'=>1]);
DB::table('productos')->insert(['id'=>1,'sku'=>'CI-1','nombre'=>'Producto CI']);
DB::table('estados')->insert([['id'=>1,'codigo'=>'RECEPCIONADA'],['id'=>2,'codigo'=>'NULA']]);
DB::table('proveedores')->insert(['id'=>1,'nombre'=>'Proveedor CI','rut'=>'CI']);
DB::table('tipo_documentos')->insert(['id'=>1,'nombre'=>'Factura']);
foreach([1,2,3,4,5] as $id){
 $sid=match($id){2=>null,3=>99,default=>1};
 $bid=$id===4?2:1;
 $state=$id===5?2:1;
 DB::table('ordenes_compra')->insert(['id'=>$id,'sucursal_id'=>$sid,'numero'=>'OC-CI-'.$id]);
 DB::table('compras')->insert(['id'=>$id,'sucursal_id'=>$sid,'estado_id'=>$state,'proveedor_id'=>1,'documento_id'=>1,'orden_compra_id'=>$id,'folio'=>'F'.$id,'fecha_documento'=>'2026-01-01','total'=>'119','total_informado'=>'119','signo_contable'=>1]);
 $header=['id'=>$id,'sucursal_id'=>$sid,'estado_id'=>$state,'proveedor_id'=>1,'bodega_id'=>$bid,'orden_compra_id'=>$id,'numero_interno'=>'CI-'.$id,'total'=>'119','subtotal_neto'=>'100','total_descuentos'=>'0','total_exento'=>'0','total_iva'=>'19','otros_impuestos'=>'0'];
 DB::table('recepciones')->insert($header+['compra_id'=>$id,'fecha_recepcion'=>'2026-01-01']);
 DB::table('recepciones_consolidadas')->insert($header+['fecha_consolidacion'=>'2026-01-01']);
 DB::table('libro_compras')->insert(['id'=>$id,'compra_id'=>$id,'documento_id'=>1,'folio'=>'F'.$id,'fecha_documento'=>'2026-01-01','razon_social_emisor'=>'CI','monto_total'=>'119','signo_contable'=>1]);
 if($id<=4)DB::table('kardex')->insert(['id'=>$id,'sucursal_id'=>$sid,'producto_id'=>1,'bodega_id'=>$bid,'fecha_movimiento'=>'2026-01-01','cantidad_documento'=>'1']);
}
foreach([1,2,3] as $id)DB::table('stock')->insert(['id'=>$id,'producto_id'=>1,'bodega_id'=>$id,'disponible'=>'1','transito'=>'0','comprometido'=>'0','cantidad_fisica'=>'1']);
$c=$app->make(\App\Http\Controllers\ComprasConsultasControlador::class);
function req(array $data=[],bool $session=true):Request{
 $r=Request::create('/','GET',$data);$r->setUserResolver(fn()=>(object)['id'=>1]);
 if($session)$r->attributes->set('id_token_acceso',1);return $r;
}
function ids($res):array{$v=$res->getData(true);return array_map('intval',array_column($v['data'],'id'));}
function denied(callable $f,int $status):void{try{$f();}catch(HttpException $e){ok($e->getStatusCode()===$status,'HTTP esperado '.$status.' obtenido '.$e->getStatusCode());return;}throw new RuntimeException('Acceso no bloqueado');}
ok(ids($c->listar(req(),'facturas'))===[4,1],'facturas solo sucursal no anuladas');
ok(ids($c->listar(req(),'libro'))===[4,1],'libro depende de compra');
ok(ids($c->listar(req(['exportar'=>1]),'libro'))===[4,1],'exportación sin fuga');
foreach(['recepciones','consolidaciones'] as $m)ok(ids($c->listar(req(),$m))===[1],'documentos por sucursal y bodega');
ok(ids($c->listar(req(),'stock'))===[3,1],'stock bodega asignada incluso histórica');
$k=$c->listar(req(),'kardex')->getData(true);
ok(array_column($k['bodegas'],'id')===[1],'selector solo bodega activa autorizada');
ok(ids($c->listar(req(['producto_id'=>1,'bodega_id'=>1]),'kardex'))===[1],'kardex excluye nulos y ajenos');
foreach(['stock','kardex','recepciones','consolidaciones'] as $m)denied(fn()=>$c->listar(req(['producto_id'=>1,'bodega_id'=>2]),$m),404);
denied(fn()=>$c->listar(req([],false),'stock'),401);
denied(fn()=>$c->pdf(req(),'recepcion',2),404);
denied(fn()=>$c->pdf(req(),'recepcion',3),404);
denied(fn()=>$c->pdf(req(),'recepcion',4),404);
$valid=$c->pdf(req(),'recepcion',1);
ok(str_starts_with($valid->getContent(),'%PDF-'),'PDF autorizado válido');
DB::table('recepciones')->where('id',3)->update(['recepcion_consolidada_id'=>1]);
denied(fn()=>$c->pdf(req(),'consolidacion',1),409);
DB::table('sesiones_sucursales')->delete();
denied(fn()=>$c->listar(req(),'stock'),409);
DB::table('sesiones_sucursales')->insert(['token_acceso_id'=>1,'sucursal_id'=>1]);
DB::table('sucursales')->insert(['id'=>2,'codigo'=>'S02','activo'=>0]);
denied(fn()=>$c->listar(req(),'facturas'),409);
ok(DB::table('stock')->count()===3&&DB::table('kardex')->count()===4,'consultas sin escrituras');
echo "V108B1_CONSULTAS_OK\n";
