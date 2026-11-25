<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
function check(bool $ok,string $m): void{if(!$ok)throw new RuntimeException($m);}
check(config('app.env')==='testing'&&DB::getDatabaseName()===':memory:','Solo CI');
foreach([
'CREATE TABLE estados(id INTEGER PRIMARY KEY,codigo TEXT)',
'CREATE TABLE recepciones(id INTEGER PRIMARY KEY,numero_interno TEXT,estado_id INTEGER,compra_id INTEGER,proveedor_id INTEGER,bodega_id INTEGER,orden_compra_id INTEGER,recepcion_consolidada_id INTEGER,stock_aplicado INTEGER,token_confirmacion TEXT,total TEXT,fecha_recepcion TEXT)',
'CREATE TABLE compras(id INTEGER PRIMARY KEY,documento_id INTEGER,folio TEXT,fecha_documento TEXT,total_informado TEXT)',
'CREATE TABLE proveedores(id INTEGER PRIMARY KEY,nombre TEXT,rut TEXT)',
'CREATE TABLE bodegas(id INTEGER PRIMARY KEY,nombre TEXT,activo INTEGER)',
'CREATE TABLE ordenes_compra(id INTEGER PRIMARY KEY,numero TEXT,estado_id INTEGER)',
'CREATE TABLE recepciones_consolidadas(id INTEGER PRIMARY KEY,estado_id INTEGER)',
'CREATE TABLE recepcion_detalle(id INTEGER PRIMARY KEY,recepcion_id INTEGER,numero_linea INTEGER)',
'CREATE TABLE recepcion_diferencias(id INTEGER PRIMARY KEY,recepcion_id INTEGER,tipo_diferencia TEXT,cantidad_diferencia TEXT,motivo TEXT,estado_resolucion TEXT)',
'CREATE TABLE solicitudes_regularizacion(id INTEGER PRIMARY KEY,recepcion_id INTEGER,numero_interno TEXT,tipo_solicitud TEXT,estado TEXT,monto_total TEXT)',
'CREATE TABLE cola_notificaciones_correo(id INTEGER PRIMARY KEY,solicitud_id INTEGER,origen_tipo TEXT,estado TEXT,ultimo_error TEXT,intentos INTEGER,maximo_intentos INTEGER,enviada_en TEXT)'
] as $sql)DB::statement($sql);
DB::table('estados')->insert([['id'=>1,'codigo'=>'DESCUADRADA'],['id'=>2,'codigo'=>'CONSOLIDADA'],['id'=>3,'codigo'=>'ACTIVA']]);
DB::table('proveedores')->insert(['id'=>1,'nombre'=>'Proveedor CI','rut'=>'1-9']);
DB::table('bodegas')->insert(['id'=>1,'nombre'=>'Central','activo'=>1]);
DB::table('ordenes_compra')->insert(['id'=>1,'numero'=>'OC-CI','estado_id'=>3]);
DB::table('compras')->insert(['id'=>1,'documento_id'=>1,'folio'=>'123','fecha_documento'=>'2026-11-25','total_informado'=>'595']);
foreach([1,2] as $id)DB::table('recepciones')->insert(['id'=>$id,'numero_interno'=>'REC-CI-'.$id,'estado_id'=>1,'compra_id'=>1,'proveedor_id'=>1,'bodega_id'=>1,'orden_compra_id'=>1,'stock_aplicado'=>1,'total'=>'595']);
DB::table('recepcion_diferencias')->insert(['id'=>1,'recepcion_id'=>1,'tipo_diferencia'=>'DOCUMENTAL','cantidad_diferencia'=>'0','motivo'=>'Diferencia CI','estado_resolucion'=>'ABIERTA']);
$c=new \App\Http\Controllers\RecepcionesBorradorControlador();
$d=$c->ver(1)->getData(true);check($d['solicitudes']===[]&&$d['estado_consolidacion']===null&&count($d['diferencias'])===1,'Pendiente de consolidación');
DB::table('recepciones_consolidadas')->insert(['id'=>1,'estado_id'=>2]);
DB::table('recepciones')->where('id',1)->update(['recepcion_consolidada_id'=>1]);
foreach([[1,1,'PENDIENTE'],[2,2,'PENDIENTE'],[3,1,'NULA']] as [$id,$rec,$state])DB::table('solicitudes_regularizacion')->insert(['id'=>$id,'recepcion_id'=>$rec,'numero_interno'=>'SOL-CI-'.$id,'tipo_solicitud'=>'AVISO_COMERCIAL','estado'=>$state,'monto_total'=>'0']);
DB::table('cola_notificaciones_correo')->insert(['id'=>1,'solicitud_id'=>1,'origen_tipo'=>'SOLICITUD_REGULARIZACION','estado'=>'ERROR','ultimo_error'=>'No confirmado','intentos'=>1,'maximo_intentos'=>5]);
$d=$c->ver(1)->getData(true);check(count($d['solicitudes'])===1&&$d['solicitudes'][0]['id']===1&&$d['solicitudes'][0]['estado_correo']==='ERROR'&&$d['estado_consolidacion']==='CONSOLIDADA','Filtrar solicitudes propias, vigentes y cola');
$q=new \App\Http\Controllers\ComprasConsultasControlador();$l=$q->listar(Request::create('/','GET'),'recepciones')->getData(true);check(count($l['data'])===2&&(int)$l['data'][0]['solicitudes_count']===1&&(int)$l['data'][1]['solicitudes_count']===1,'Conteo sin duplicar filas');
check(DB::table('solicitudes_regularizacion')->count()===3&&DB::table('cola_notificaciones_correo')->count()===1,'Solo lectura');
echo "V97_SOLICITUDES_OK\n";
