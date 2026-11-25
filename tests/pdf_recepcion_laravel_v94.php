<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
DB::statement('CREATE TABLE estados(id INTEGER PRIMARY KEY,codigo TEXT)');
DB::statement('CREATE TABLE proveedores(id INTEGER PRIMARY KEY,nombre TEXT,rut TEXT)');
DB::statement('CREATE TABLE bodegas(id INTEGER PRIMARY KEY,nombre TEXT)');
DB::statement('CREATE TABLE ordenes_compra(id INTEGER PRIMARY KEY,numero TEXT)');
DB::statement('CREATE TABLE tipo_documentos(id INTEGER PRIMARY KEY,nombre TEXT)');
DB::statement('CREATE TABLE compras(id INTEGER PRIMARY KEY,documento_id INTEGER,folio TEXT,fecha_documento TEXT,total_informado TEXT)');
DB::statement('CREATE TABLE recepciones(id INTEGER PRIMARY KEY,estado_id INTEGER,proveedor_id INTEGER,bodega_id INTEGER,orden_compra_id INTEGER,compra_id INTEGER,numero_interno TEXT,fecha_recepcion TEXT,subtotal_neto TEXT,total_descuentos TEXT,total_exento TEXT,total_iva TEXT,otros_impuestos TEXT,total TEXT,observaciones TEXT)');
DB::statement('CREATE TABLE productos(id INTEGER PRIMARY KEY,sku TEXT,nombre TEXT)');
DB::statement('CREATE TABLE recepcion_detalle(id INTEGER PRIMARY KEY,recepcion_id INTEGER,producto_id INTEGER,numero_linea INTEGER,cantidad_documentada TEXT,cantidad_recibida TEXT,cantidad_aceptada TEXT,cantidad_rechazada TEXT,costo_unitario_neto TEXT,monto_neto TEXT,monto_exento TEXT,monto_iva TEXT,total TEXT)');
DB::statement('CREATE TABLE recepcion_diferencias(id INTEGER PRIMARY KEY,recepcion_id INTEGER,tipo_diferencia TEXT,cantidad_diferencia TEXT,motivo TEXT,estado_resolucion TEXT)');
DB::table('estados')->insert([['id'=>1,'codigo'=>'RECEPCIONADA'],['id'=>2,'codigo'=>'DESCUADRADA']]);
DB::table('proveedores')->insert(['id'=>1,'nombre'=>'Proveedor CI $¢','rut'=>'76.000.000-0']);
DB::table('bodegas')->insert(['id'=>1,'nombre'=>'Bodega CI']);
DB::table('ordenes_compra')->insert(['id'=>1,'numero'=>'OC-CI']);
DB::table('tipo_documentos')->insert(['id'=>1,'nombre'=>'Factura electrónica']);
DB::table('compras')->insert(['id'=>1,'documento_id'=>1,'folio'=>'000123','fecha_documento'=>'2026-11-25','total_informado'=>'1190']);
DB::table('recepciones')->insert(['id'=>1,'estado_id'=>1,'proveedor_id'=>1,'bodega_id'=>1,'orden_compra_id'=>1,'compra_id'=>1,'numero_interno'=>'REC-CI','fecha_recepcion'=>'2026-11-25','subtotal_neto'=>'1000','total_descuentos'=>'0','total_exento'=>'0','total_iva'=>'190','otros_impuestos'=>'0','total'=>'1190','observaciones'=>'Verificación de símbolos $¢ y acentos.']);
DB::table('productos')->insert(['id'=>1,'sku'=>'SKU-CI','nombre'=>'Producto con descripción y $¢']);
DB::table('recepcion_detalle')->insert(['id'=>1,'recepcion_id'=>1,'producto_id'=>1,'numero_linea'=>1,'cantidad_documentada'=>'1','cantidad_recibida'=>'1','cantidad_aceptada'=>'1','cantidad_rechazada'=>'0','costo_unitario_neto'=>'1000','monto_neto'=>'1000','monto_exento'=>'0','monto_iva'=>'190','total'=>'1190']);
$c=new \App\Http\Controllers\ComprasConsultasControlador();
$dir=sys_get_temp_dir().'/v94pdf';
if(!is_dir($dir))mkdir($dir,0777,true);
foreach([1,2] as $state){
 if($state===2){
  DB::table('recepciones')->where('id',1)->update(['estado_id'=>2]);
  DB::table('recepcion_diferencias')->insert(['id'=>1,'recepcion_id'=>1,'tipo_diferencia'=>'FALTANTE','cantidad_diferencia'=>'0.5','motivo'=>'Conteo físico','estado_resolucion'=>'ABIERTA']);
 }
 $response=$c->pdf('recepcion',1);
 $body=$response->getContent();
 if($response->getStatusCode()!==200||$response->headers->get('Content-Type')!=='application/pdf'||!str_starts_with($body,'%PDF-1.4'))throw new RuntimeException('Respuesta PDF incorrecta');
 file_put_contents($dir.'/recepcion-'.$state.'.pdf',$body);
}
echo "V94_LARAVEL_PDF_OK\n";
