<?php
declare(strict_types=1);

/**
 * CI ÚNICAMENTE: escritura sobre MySQL efímero rutahyd2_es_local.
 * Nunca ejecutar contra InfinityFree / datos comerciales reales.
 */
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$http=$app->make(\Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

function photosAssert(bool $okay,string $message): void {
    if(!$okay)throw new RuntimeException($message);
}
function photosCall(string $verb,string $uri,array $data=[],?string $token=null,
                    array $files=[]): array {
    global $http;
    $headers=['HTTP_ACCEPT'=>'application/json'];
    if($token!==null)$headers['HTTP_AUTHORIZATION']='Bearer '.$token;
    $body=$files===[]&&in_array($verb,['POST','PUT'],true)
        ?json_encode($data,JSON_THROW_ON_ERROR):null;
    if($body!==null)$headers['CONTENT_TYPE']='application/json';
    $req=Request::create($uri,$verb,[],[],$files,$headers,$body);
    $res=$http->handle($req);
    $type=$res->headers->get('Content-Type','');
    $raw=$res->getContent();
    $answer=[$res->getStatusCode(),str_contains($type,'application/json')
        ?json_decode($raw,true):$raw,$type];
    $http->terminate($req,$res);
    return $answer;
}
function codePhotos(int $expected,array $reply,string $what): mixed {
    photosAssert($reply[0]===$expected,$what.' esperaba '.$expected.' y recibió '
        .$reply[0].' '.json_encode($reply[1],JSON_UNESCAPED_UNICODE));
    return $reply[1];
}
$temp=null;
try {
    photosAssert(DB::getDatabaseName()==='rutahyd2_es_local',
        'Prohibido usar pruebas de fotografía fuera de MySQL temporal');
    $noTriggers=DB::table('information_schema.TRIGGERS')
        ->where('TRIGGER_SCHEMA','rutahyd2_es_local')
        ->where('EVENT_OBJECT_TABLE','productos_fotos')->count();
    photosAssert($noTriggers===0,'DDL25 no debe crear triggers');
    $session=codePhotos(200,photosCall('POST','/api/v1/sesion',[
        'correo_electronico'=>'integracion@ejemplo.invalid',
        'contrasena'=>'ClaveCI!soloParaPruebas-2026'
    ]),'Sesión CI');
    $token=$session['token'];
    $linea=(int)DB::table('lineas')->orderBy('id')->value('id');
    photosAssert($linea>0,'Línea CI inexistente');
    $id=(int)DB::table('productos')->insertGetId([
        'sku'=>'SKU-FOTO-INICIO-CI','nombre'=>'Producto galería CI',
        'linea_id'=>$linea,'costo_neto'=>0,'activo'=>1
    ]);
    codePhotos(401,photosCall('GET','/api/v1/productos/'.$id.'/fotos'),
        'Fotos deben exigir JWT');
    $empty=codePhotos(200,photosCall('GET','/api/v1/productos/'.$id.'/fotos',[],$token),
        'Lista vacía CI');
    photosAssert($empty['total']===0,'El producto empieza sin fotos');

    // PNG de 1x1 válido, guardado en LONGBLOB (binario, nunca base64).
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WlphcwAAAAASUVORK5CYII=',true);
    photosAssert(is_string($png)&&strlen($png)>50,'Fixture PNG CI inválido');
    $temp=tempnam(sys_get_temp_dir(),'hd_photo_');
    file_put_contents($temp,$png);
    $upload=function()use($id,$token,$temp): array {
        return photosCall('POST','/api/v1/productos/'.$id.'/fotos',[],$token,[
            'fotos'=>[new UploadedFile($temp,'foto-ruta.png','image/png',null,true)]
        ]);
    };
    $first=codePhotos(201,$upload(),'Subir imagen 1');
    $second=codePhotos(201,$upload(),'Subir imagen 2');
    photosAssert(count($first['ids'])===1&&count($second['ids'])===1,
        'Cada carga crea un registro propio con id');
    $list=codePhotos(200,photosCall('GET','/api/v1/productos/'.$id.'/fotos',[],$token),
        'Galería con N fotos');
    photosAssert($list['total']===2&&count($list['items'])===2,
        'Dos fotos asociadas al mismo producto');
    photosAssert(!array_key_exists('foto',$list['items'][0]),
        'No serializar BLOB a JSON de la galería');
    $row=DB::table('productos_fotos')->where('id',$first['ids'][0])->first();
    photosAssert($row->sku==='SKU-FOTO-INICIO-CI'&&$row->producto_id===$id,
        'Relaciona foto con SKU e ID del producto');
    photosAssert($row->foto===$png&&$row->mime_tipo==='image/png',
        'BLOB conserva bytes PNG sin modificación');
    $image=photosCall('GET','/api/v1/productos/'.$id.'/fotos/'.$first['ids'][0],
        [],$token);
    codePhotos(200,$image,'Imagen con JWT');
    photosAssert($image[1]===$png&&str_contains($image[2],'image/png'),
        'Entrega foto binaria autenticada con MIME seguro');
    $wrongId=$id+999999;
    codePhotos(404,photosCall('GET','/api/v1/productos/'.$wrongId.'/fotos/'.$first['ids'][0],
        [],$token),'No abrir foto desde otro producto');

    $sorted=codePhotos(200,photosCall('GET',
        '/api/v1/productos?orden=precios&direccion=asc&por_pagina=100',
        [],$token),'Orden global por precio');
    photosAssert(isset($sorted['data'])&&is_array($sorted['data']),
        'Ordenamiento de catálogo devuelve página');
    $sorted2=codePhotos(200,photosCall('GET',
        '/api/v1/productos?orden=rubro&direccion=desc&por_pagina=100',
        [],$token),'Orden global por Rubro');
    $item=collect($sorted2['data'])->firstWhere('id',$id);
    photosAssert($item!==null&&$item['cantidadFotos']===2,
        'Listado muestra contador de fotografías por producto');
    photosAssert(array_key_exists('clasificacionNombres',$item) &&
        array_key_exists('precios',$item) &&
        array_key_exists('codigosVenta',$item),
        'Listado incorpora taxonomía, precios y códigos sin traer BLOB');

    codePhotos(200,photosCall('PUT','/api/v1/productos/'.$id,[
        'sku'=>'SKU-FOTO-CAMBIADO-CI'
    ],$token),'Cambio SKU de producto con fotos');
    photosAssert(DB::table('productos_fotos')->where('producto_id',$id)
        ->where('sku','SKU-FOTO-CAMBIADO-CI')->count()===2,
        'Laravel actualiza todos los SKU de fotos sin trigger');
    codePhotos(200,photosCall('DELETE',
        '/api/v1/productos/'.$id.'/fotos/'.$first['ids'][0],[],$token),
        'Eliminar foto de producto');
    photosAssert(DB::table('productos_fotos')->where('producto_id',$id)->count()===1,
        'Eliminar una foto conserva las demás');
    echo "OK: DDL25 sin triggers, JWT, PNG binario, N fotos, SKU, listado y ordenación.\n";
}catch(\Throwable $e){
    fwrite(STDERR,"ERROR fotos CI: ".$e->getMessage()."\n");
    exit(1);
}finally {
    if($temp&&file_exists($temp))unlink($temp);
}
