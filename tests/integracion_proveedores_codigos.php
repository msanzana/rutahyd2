<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function checkAb(bool $ok,string $name):void{
    if(!$ok)throw new RuntimeException('Error abastecimiento: '.$name);
}
function abRequest(string $method,string $path,array $body=[],?string $token=null):array{
    global $kernel;
    static $index=1;
    $server=['HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json',
        'REMOTE_ADDR'=>'192.0.2.'.(($index++%240)+1)];
    if($token!==null)$server['HTTP_AUTHORIZATION']='Bearer '.$token;
    $r=Request::create($path,$method,[],[],[],$server,
        in_array($method,['POST','PUT','PATCH'],true)?json_encode($body,JSON_THROW_ON_ERROR):null);
    $response=$kernel->handle($r);
    $out=[$response->getStatusCode(),json_decode((string)$response->getContent(),true)];
    $kernel->terminate($r,$response);
    return $out;
}
function abExpect(int $expected,array $result,string $name):array{
    checkAb($result[0]===$expected,$name.' esperó '.$expected.' obtuvo '.$result[0].
        ' '.json_encode($result[1],JSON_UNESCAPED_UNICODE));
    return $result[1]??[];
}
try{
    checkAb(DB::getDatabaseName()==='rutahyd2_es_local','prohibido fuera MySQL CI');
    foreach(['tipos_codigos_barras','productos_proveedores',
        'productos_codigos_barra_venta','productos_codigos_barra_compra'] as $table){
        checkAb(DB::getSchemaBuilder()->hasTable($table),'Falta DDL20 '.$table);
    }
    abExpect(401,abRequest('GET','/api/v1/proveedores-productos'),'JWT requerido');
    $admin=abExpect(200,abRequest('POST','/api/v1/sesion',[
        'correo_electronico'=>'integracion@ejemplo.invalid',
        'contrasena'=>'ClaveCI!soloParaPruebas-2026'
    ]),'Login administrador CI')['token'];
    $reader=abExpect(200,abRequest('POST','/api/v1/sesion',[
        'correo_electronico'=>'lector@ejemplo.invalid',
        'contrasena'=>'NoUsarEnProduccion-2026'
    ]),'Login lector CI')['token'];
    abExpect(403,abRequest('POST','/api/v1/proveedores-productos',[
        'producto_id'=>1,'proveedor_id'=>1
    ],$reader),'Rol sin permiso comprar');
    abExpect(405,abRequest('POST','/api/v1/recursos/ventas',[
        'nombre'=>'No autorizado'
    ],$admin),'Sin escritura genérica ventas');

    $provider=abExpect(201,abRequest('POST','/api/v1/recursos/proveedores',[
        'nombre'=>'Proveedor de prueba CI','rut'=>'12345678-5',
        'correo_electronico'=>'ci-proveedor@example.invalid'
    ],$admin),'Crear proveedor CI');
    $providerId=(int)$provider['id'];
    $prodId=(int)DB::table('productos')->insertGetId([
        'sku'=>'SKU-ABASTECIMIENTO-CI','nombre'=>'Producto abastecimiento CI','activo'=>1
    ]);
    abExpect(422,abRequest('POST','/api/v1/proveedores-productos',[
        'producto_id'=>$prodId,'proveedor_id'=>$providerId,
        'ultima_recepcion_id'=>100,'codigo_proveedor_producto'=>'PROV-CI-1'
    ],$admin),'No alterar último recibo manualmente');
    $link=abExpect(201,abRequest('POST','/api/v1/proveedores-productos',[
        'producto_id'=>$prodId,'proveedor_id'=>$providerId,
        'codigo_proveedor_producto'=>'COD-PROV-2026',
        'costo_proveedor'=>'1250.9999','activo'=>true
    ],$admin),'Asociar producto proveedor');
    $linkId=(int)$link['id'];
    checkAb($linkId>0&&$link['codigo_producto']==='SKU-ABASTECIMIENTO-CI'&&
        $link['codigo_proveedor_producto']==='COD-PROV-2026','Códigos propios vía JOIN');
    checkAb($link['ultima_recepcion_id']===null&&$link['fecha_ultima_recepcion']===null,
        'Recepciones solo lectura al inicio');
    abExpect(409,abRequest('POST','/api/v1/proveedores-productos',[
        'producto_id'=>$prodId,'proveedor_id'=>$providerId,
        'codigo_proveedor_producto'=>'COD-PROV-2026'
    ],$admin),'Relación duplicada');
    $search=abExpect(200,abRequest('GET',
        '/api/v1/proveedores-productos?buscar=ABASTECIMIENTO-CI',[],$admin),'Búsqueda por SKU');
    checkAb((int)$search['total']===1,'Búsqueda proveedor-producto paginada');
    $ean13=(int)DB::table('tipos_codigos_barras')->where('codigo','EAN13')->value('id');
    $code128=(int)DB::table('tipos_codigos_barras')->where('codigo','CODE128')->value('id');
    checkAb($ean13>0&&$code128>0,'Semillas de tipos código barras');
    abExpect(422,abRequest('POST','/api/v1/productos/'.$prodId.'/codigos-venta',[
        'tipo_codigo_barra_id'=>$ean13,'codigo_barras'=>'INVÁLIDO',
        'unidades_por_codigo'=>1
    ],$admin),'EAN13 sólo 13 dígitos');
    $sales=abExpect(201,abRequest('POST','/api/v1/productos/'.$prodId.'/codigos-venta',[
        'tipo_codigo_barra_id'=>$ean13,'codigo_barras'=>'7801234567890',
        'unidades_por_codigo'=>1
    ],$admin),'Añadir código venta');
    checkAb((int)$sales['producto_id']===$prodId,'Código venta sin proveedor');
    $purchase=abExpect(201,abRequest('POST',
        '/api/v1/proveedores-productos/'.$linkId.'/codigos-compra',[
        'tipo_codigo_barra_id'=>$code128,'codigo_barras'=>'PACK-PROV-001',
        'unidades_por_codigo'=>'12.000'
    ],$admin),'Añadir código compra');
    $purchaseId=(int)$purchase['id'];
    $row=abExpect(200,abRequest('GET',
        '/api/v1/proveedores-productos/'.$linkId.'/codigos-compra',[],$admin),'Listar códigos compra');
    checkAb(count($row['items'])===1&&$row['items'][0]['codigo_producto']==='SKU-ABASTECIMIENTO-CI'&&
        $row['items'][0]['codigo_proveedor_producto']==='COD-PROV-2026',
        'Código compra expone SKU y código proveedor sin duplicarlos');
    abExpect(409,abRequest('POST',
        '/api/v1/proveedores-productos/'.$linkId.'/codigos-compra',[
        'tipo_codigo_barra_id'=>$code128,'codigo_barras'=>'PACK-PROV-001',
        'unidades_por_codigo'=>'6'
    ],$admin),'Código compra repetido para relación');
    $edited=abExpect(200,abRequest('PUT',
        '/api/v1/proveedores-productos/'.$linkId.'/codigos-compra/'.$purchaseId,[
        'tipo_codigo_barra_id'=>$code128,'codigo_barras'=>'PACK-PROV-002',
        'unidades_por_codigo'=>'24'
    ],$admin),'Editar cantidad compra');
    checkAb((float)$edited['unidades_por_codigo']===24.0,'Cantidad de compra actualizada');
    abExpect(200,abRequest('DELETE',
        '/api/v1/proveedores-productos/'.$linkId.'/codigos-compra/'.$purchaseId,
        [],$admin),'Desactivar código compra');
    checkAb(DB::table('productos_codigos_barra_compra')->where('id',$purchaseId)->exists(),
        'Código histórico permanece');
    abExpect(200,abRequest('DELETE','/api/v1/proveedores-productos/'.$linkId,
        [],$admin),'Desactivar asociación');
    checkAb(DB::table('productos_proveedores')->where('id',$linkId)->exists(),
        'Asociación histórica permanece');
    echo "OK: proveedores-productos, costos, códigos compra/venta, permisos, bajas lógicas y JOIN en MySQL CI.\n";
}catch(\Throwable $e){
    fwrite(STDERR,'FALLO CI abastecimiento: '.$e->getMessage()."\n");
    exit(1);
}
