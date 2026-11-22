<?php
declare(strict_types=1);
/** CI EXCLUSIVAMENTE: ejecuta escrituras SOLO en MySQL efímero rutahyd2_es_local. */
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function assertEntity(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function entityRequest(string $method,string $uri,array $payload=[],?string $token=null): array {
    global $kernel;
    static $seq=0;
    $headers=[
        'HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json',
        'REMOTE_ADDR'=>'192.0.2.'.(++$seq%240+1),
    ];
    if ($token!==null) $headers['HTTP_AUTHORIZATION']='Bearer '.$token;
    $request=Request::create($uri,$method,[],[],[],$headers,
        in_array($method,['POST','PUT','PATCH'],true)
            ? json_encode($payload,JSON_THROW_ON_ERROR):null);
    $response=$kernel->handle($request);
    $value=[$response->getStatusCode(),json_decode((string)$response->getContent(),true)];
    $kernel->terminate($request,$response);
    return $value;
}
function entityExpect(int $code,array $reply,string $title): array {
    assertEntity($reply[0]===$code,
        $title.' esperaba HTTP '.$code.', obtuvo '.$reply[0].' '.json_encode($reply[1],JSON_UNESCAPED_UNICODE));
    return is_array($reply[1])?$reply[1]:[];
}
try {
    assertEntity(DB::getDatabaseName()==='rutahyd2_es_local',
        'PROHIBIDO ejecutar fuera de CI efímero');
    foreach (['clientes','proveedores','bodegas'] as $resource) {
        entityExpect(401,entityRequest('POST','/api/v1/recursos/'.$resource,[
            'nombre'=>'Inyección anónima'
        ]),'Anonimo '.$resource);
    }
    $admin=entityExpect(200,entityRequest('POST','/api/v1/sesion',[
        'correo_electronico'=>'integracion@ejemplo.invalid',
        'contrasena'=>'ClaveCI!soloParaPruebas-2026',
    ]),'Login administrador CI')['token'];
    $reader=entityExpect(200,entityRequest('POST','/api/v1/sesion',[
        'correo_electronico'=>'lector@ejemplo.invalid',
        'contrasena'=>'NoUsarEnProduccion-2026',
    ]),'Login lector CI')['token'];
    foreach (['clientes','proveedores','bodegas'] as $resource) {
        entityExpect(403,entityRequest('POST','/api/v1/recursos/'.$resource,[
            'nombre'=>'No autorizado','codigo'=>'INTRUSO'
        ],$reader),'No permitir escritura '.$resource);
    }
    entityExpect(405,entityRequest('POST','/api/v1/recursos/ventas',[
        'nombre'=>'No autorizado'
    ],$admin),'No permitir ventas por CRUD generico');
    entityExpect(422,entityRequest('POST','/api/v1/recursos/clientes',[
        'nombre'=>'Cliente CI','hash_contrasena'=>'prohibido'
    ],$admin),'No aceptar campos ajenos');
    entityExpect(422,entityRequest('POST','/api/v1/recursos/bodegas',[
        'nombre'=>'Bodega sin codigo'
    ],$admin),'Codigo de bodega obligatorio');

    $cliente=entityExpect(201,entityRequest('POST','/api/v1/recursos/clientes',[
        'codigo'=>'CLI-INTEGRACION',
        'nombre'=>'Cliente de integración',
        'rut'=>'12345678-9','correo_electronico'=>'cliente-ci@example.invalid',
        'comuna'=>'Temuco','activo'=>true
    ],$admin),'Crear cliente');
    $clienteId=(int)$cliente['id'];
    assertEntity($clienteId>0 && $cliente['comuna']==='Temuco','Cliente guardado');
    $base=DB::table('listas_precios')->where('es_base',1)->value('id');
    assertEntity($base!==null && DB::table('clientes_listas_precios')
        ->where('cliente_id',$clienteId)->where('lista_precio_id',$base)->exists(),
        'Laravel debe asignar la LISTA BASE al cliente, sin trigger');
    entityExpect(422,entityRequest('POST','/api/v1/recursos/clientes',[
        'nombre'=>'Cliente con campo inválido','total_bruto'=>999
    ],$admin),'No exponer ventas');
    entityExpect(422,entityRequest('PUT','/api/v1/recursos/clientes/'.$clienteId,[
        'nombre'=>''
    ],$admin),'No permitir nombre vacío');
    entityExpect(422,entityRequest('PUT','/api/v1/recursos/clientes/'.$clienteId,[], $admin),
        'No permitir update vacío');
    entityExpect(422,entityRequest('PUT','/api/v1/recursos/clientes/'.$clienteId,[
        'activo'=>'indefinido'
    ],$admin),'Estado inválido');
    entityExpect(422,entityRequest('POST','/api/v1/recursos/clientes',[
        'codigo'=>'CLI-INTEGRACION','nombre'=>'Código repetido'
    ],$admin),'Código cliente repetido');
    $edited=entityExpect(200,entityRequest('PUT','/api/v1/recursos/clientes/'.$clienteId,[
        'nombre'=>'Cliente actualizado CI','sector'=>'Centro'
    ],$admin),'Editar cliente');
    assertEntity($edited['nombre']==='Cliente actualizado CI' && $edited['sector']==='Centro',
        'Cliente actualizado');

    $proveedor=entityExpect(201,entityRequest('POST','/api/v1/recursos/proveedores',[
        'nombre'=>'Proveedor CI','correo_electronico'=>'proveedor@example.invalid'
    ],$admin),'Crear proveedor');
    $proveedorId=(int)$proveedor['id'];
    entityExpect(200,entityRequest('PUT','/api/v1/recursos/proveedores/'.$proveedorId,[
        'telefono'=>'+56987654321'
    ],$admin),'Editar proveedor');
    $bodega=entityExpect(201,entityRequest('POST','/api/v1/recursos/bodegas',[
        'codigo'=>'BOD-CI','nombre'=>'Bodega integrada CI','tipo'=>'general'
    ],$admin),'Crear bodega');
    $bodegaId=(int)$bodega['id'];
    entityExpect(422,entityRequest('POST','/api/v1/recursos/bodegas',[
        'codigo'=>'BOD-CI','nombre'=>'Otra bodega'
    ],$admin),'No duplicar bodega');
    entityExpect(200,entityRequest('PUT','/api/v1/recursos/bodegas/'.$bodegaId,[
        'nombre'=>'Bodega actualizada'
    ],$admin),'Editar bodega');

    foreach (['clientes'=>$clienteId,'proveedores'=>$proveedorId,'bodegas'=>$bodegaId] as $resource=>$id) {
        $page=entityExpect(200,entityRequest('GET','/api/v1/recursos/'.$resource.'?por_pagina=100',
            [],$admin),'Leer '.$resource);
        assertEntity(in_array($id,array_map('intval',array_column($page['data'],'id')),true),
            'El registro remoto debe aparecer en '.$resource);
        $off=entityExpect(200,entityRequest('DELETE','/api/v1/recursos/'.$resource.'/'.$id,
            [],$admin),'Desactivar '.$resource);
        assertEntity($off['eliminacion']==='logica' && $off['activo']===false,'Baja lógica '.$resource);
        assertEntity(DB::table($resource)->where('id',$id)->exists() &&
            (int)DB::table($resource)->where('id',$id)->value('activo')===0,
            'Baja no borra físicamente '.$resource);
        entityExpect(200,entityRequest('PUT','/api/v1/recursos/'.$resource.'/'.$id,[
            'activo'=>true
        ],$admin),'Reactivar '.$resource);
        assertEntity((int)DB::table($resource)->where('id',$id)->value('activo')===1,
            'Reactivación '.$resource);
    }
    entityExpect(404,entityRequest('DELETE','/api/v1/recursos/clientes/999999999',
        [],$admin),'Registro inexistente');
    echo "OK: CRUD real aislado clientes proveedores bodegas, permisos, validación, paginación y baja lógica.\n";
} catch (\Throwable $e) {
    fwrite(STDERR,"ERROR CI ENTIDADES: ".$e->getMessage()."\n");
    exit(1);
}
