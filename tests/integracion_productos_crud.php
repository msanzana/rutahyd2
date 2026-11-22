<?php
declare(strict_types=1);

/** Ejecución exclusiva sobre base efímera de GitHub Actions, NUNCA producción. */
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function assertTrue(bool $test,string $msg): void {
    if (!$test) throw new RuntimeException($msg);
}
function callApi(string $method,string $uri,array $data=[],?string $token=null): array {
    global $kernel;
    $server=['HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json'];
    if ($token!==null) $server['HTTP_AUTHORIZATION']='Bearer '.$token;
    $r=Request::create($uri,$method,[],[],[],$server,
        in_array($method,['POST','PUT','PATCH'],true)?json_encode($data,JSON_THROW_ON_ERROR):null);
    $response=$kernel->handle($r);
    $out=[$response->getStatusCode(),json_decode((string)$response->getContent(),true)];
    $kernel->terminate($r,$response);
    return $out;
}
function expectCode(int $code,array $reply,string $msg): array {
    assertTrue($reply[0]===$code,$msg.': HTTP esperado '.$code.' obtuvo '.$reply[0].
        ' '.json_encode($reply[1],JSON_UNESCAPED_UNICODE));
    return $reply[1]??[];
}
try {
    assertTrue(DB::getDatabaseName()==='rutahyd2_es_local','PROHIBIDO usar esta prueba fuera de CI');
    expectCode(401,callApi('GET','/api/v1/productos'),'GET anónimo');
    $session=expectCode(200,callApi('POST','/api/v1/sesion',[
        'correo_electronico'=>'integracion@ejemplo.invalid',
        'contrasena'=>'ClaveCI!soloParaPruebas-2026',
    ]),'Login CI');
    $token=$session['token'];
    $reader=expectCode(200,callApi('POST','/api/v1/sesion',[
        'correo_electronico'=>'lector@ejemplo.invalid',
        'contrasena'=>'NoUsarEnProduccion-2026',
    ]),'Login sin permisos')['token'];
    expectCode(403,callApi('POST','/api/v1/productos',[], $reader),'Escritura sin permiso');

    $idDe=function(string $tabla,string $nombre): int {
        $id=DB::table($tabla)->where('nombre',$nombre)->value('id');
        assertTrue($id!==null,'Falta semilla temporal '.$tabla);
        return (int)$id;
    };
    $attrs=[
        'rubroId'=>$idDe('rubros','Alimentos'),
        'departamentoId'=>$idDe('departamentos','Abarrotes'),
        'familiaId'=>$idDe('familias','Conservas'),
        'lineaId'=>$idDe('lineas','Vegetales'),
        'marcaId'=>$idDe('marcas','H&D'),
        'modeloId'=>$idDe('modelos','Tradicional'),
        'tipoUnidadId'=>$idDe('tipos_unidad','Unidad'),
        'tipoEnvasadoId'=>$idDe('tipos_envasado','Lata'),
        'condicionTributariaId'=>$idDe('condiciones_tributarias','Exento'),
    ];
    $payload=[
        'sku'=>'SKU-CRUD-FULLSTACK-CI',
        'nombre'=>'Producto fullstack CI',
        'descripcion'=>'Prueba de clasificación y precio',
        'activo'=>true,
        'proveedorId'=>null,
        'clasificacion'=>$attrs,
        'presentacion'=>[
            'nombre'=>'Unidad de venta',
            'codigoBarras'=>'BAR-CI-FULLSTACK-001',
            'unidadesPorEnvase'=>'1.000',
            'precioNeto'=>'1234.5000',
        ],
    ];
    expectCode(422,callApi('POST','/api/v1/productos',[
        'sku'=>'SIN-CATALOGOS', 'nombre'=>'Mal formado'
    ],$token),'Clasificaciones exigidas');
    $mal=$payload;
    $mal['clasificacion']['departamentoId']=$idDe('departamentos','Abarrotes')+1000000;
    expectCode(422,callApi('POST','/api/v1/productos',$mal,$token),'No aceptar jerarquía inválida');
    $created=expectCode(201,callApi('POST','/api/v1/productos',$payload,$token),'Crear producto');
    $id=(int)$created['id'];
    assertTrue($id>0 && $created['presentacion']['tasaIva']==='0.00','Producto exento debe tener IVA cero');
    assertTrue((int)DB::table('presentaciones_productos')->where('producto_id',$id)->count()===1,
        'Guardar presentación en su tabla');

    $ean13=$idDe('tipos_codigos_barras','EAN-13 (GTIN-13)');
    $saleCode=expectCode(201,callApi('POST','/api/v1/productos/'.$id.'/codigos-venta',[
        'tipo_codigo_barra_id'=>$ean13,'codigo_barras'=>'7801234567890',
        'unidades_por_codigo'=>'1'
    ],$token),'Añadir código de barras de venta');
    assertTrue((int)$saleCode['producto_id']===$id,'Código pertenece al producto');
    expectCode(409,callApi('POST','/api/v1/productos/'.$id.'/codigos-venta',[
        'tipo_codigo_barra_id'=>$ean13,'codigo_barras'=>'7801234567890',
        'unidades_por_codigo'=>1
    ],$token),'Código duplicado');
    $saleList=expectCode(200,callApi('GET','/api/v1/productos/'.$id.'/codigos-venta',
        [],$token),'Listar códigos venta');
    assertTrue(count($saleList['items'])===1,'Códigos de venta en tabla propia');

    expectCode(409,callApi('POST','/api/v1/productos',$payload,$token),'Duplicado SKU');
    $single=expectCode(200,callApi('GET','/api/v1/productos/'.$id,[],$token),'Leer producto');
    assertTrue($single['sku']==='SKU-CRUD-FULLSTACK-CI' &&
        (int)$single['clasificacion']['lineaId']===$attrs['lineaId'],'Ficha y taxonomía');
    $page=expectCode(200,callApi('GET',
        '/api/v1/productos?por_pagina=10&page=1&buscar=SKU-CRUD-FULLSTACK-CI',[],$token),
        'Listado con búsqueda');
    assertTrue($page['total']>=1 && count($page['data'])>=1,'Debe listar el producto creado');

    $attrs['condicionTributariaId']=$idDe('condiciones_tributarias','Afecto');
    $updated=$payload;
    $updated['nombre']='Producto actualizado CI';
    $updated['clasificacion']=$attrs;
    $updated['presentacion']['id']=$single['presentacion']['id'];
    $updated['presentacion']['precioNeto']='2399.9900';
    $after=expectCode(200,callApi('PUT','/api/v1/productos/'.$id,$updated,$token),
        'Actualizar producto');
    assertTrue($after['nombre']==='Producto actualizado CI' &&
        $after['presentacion']['tasaIva']==='19.00','IVA Afecto y nombre actualizados');
    assertTrue((float)DB::table('presentaciones_productos')
        ->where('producto_id',$id)->value('precio_neto')===2399.99,'Precio persistido');
    assertTrue((int)DB::table('productos')->where('id',$id)->value('categoria_id')===0 ||
        DB::table('productos')->where('id',$id)->value('categoria_id')===null,
        'La categoría heredada no se inventa');

    $off=expectCode(200,callApi('DELETE','/api/v1/productos/'.$id,[],$token),
        'Desactivación sin borrado físico');
    assertTrue($off['eliminacion']==='logica' && $off['activo']===false,'Respuesta de baja lógica');
    assertTrue(DB::table('productos')->where('id',$id)->exists() &&
        DB::table('presentaciones_productos')->where('producto_id',$id)->exists(),
        'Baja nunca destruye producto ni presentación');
    $visible=expectCode(200,callApi('GET','/api/v1/productos?por_pagina=100',[],$token),
        'Lista activos');
    assertTrue(!in_array($id,array_column($visible['data'],'id'),true),'Inactivo fuera de lista activa');
    $all=expectCode(200,callApi('GET','/api/v1/productos?incluye_inactivos=1',[],$token),
        'Lista incluye inactivos');
    assertTrue(in_array($id,array_column($all['data'],'id'),true),'Baja visible en listado administrativo');
    echo "OK: productos CRUD Laravel + MySQL, IVA, clasificación, conflictos, roles, historia y baja lógica.\n";
} catch (\Throwable $e) {
    fwrite(STDERR,"ERROR FULLSTACK PRODUCTOS: ".$e->getMessage()."\n");
    exit(1);
}
