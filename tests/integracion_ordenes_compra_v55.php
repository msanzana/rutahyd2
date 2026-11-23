<?php
declare(strict_types=1);
/** V55 — JWT real sobre Laravel y MySQL TEMPORAL, correo simulado. */
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

function checkOC(bool $yes,string $message): void
{
    if(!$yes)throw new RuntimeException('V55 API: '.$message);
}
function callOC(string $method,string $path,array $body=[],?string $token=null): array
{
    global $kernel;
    $headers=['HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json'];
    if($token)$headers['HTTP_AUTHORIZATION']='Bearer '.$token;
    $req=Request::create($path,$method,[],[],[],$headers,
        in_array($method,['POST','PUT'],true)?json_encode($body,JSON_THROW_ON_ERROR):null);
    $response=$kernel->handle($req);
    $data=json_decode((string)$response->getContent(),true);
    $result=[$response->getStatusCode(),$data,$response];
    $kernel->terminate($req,$response);
    return $result;
}
function expectOC(int $status,array $response,string $name): array
{
    checkOC($response[0]===$status,$name.' esperaba '.$status.
        ' obtuvo '.$response[0].' cuerpo='.json_encode($response[1]));
    return is_array($response[1])?$response[1]:[];
}
try{
    checkOC(DB::getDatabaseName()==='rutahyd2_es_local','Base prohibida');
    $adminRole=(int)DB::table('roles')->where('codigo','administrador')->value('id');
    checkOC($adminRole>0,'Rol administrador temporal requerido');
    DB::table('usuarios')->insert([
        'rol_id'=>$adminRole,'nombre'=>'Admin CI OC',
        'correo_electronico'=>'oc_ci@example.invalid',
        'hash_contrasena'=>Hash::make('Segura-Solo-CI-2026'),
        'activo'=>1
    ]);
    $token=expectOC(200,callOC('POST','/api/v1/sesion',[
        'correo_electronico'=>'oc_ci@example.invalid',
        'contrasena'=>'Segura-Solo-CI-2026'
    ]),'login')['token'];
    expectOC(401,callOC('GET','/api/v1/ordenes-compra'),'Sin JWT');
    expectOC(401,callOC('POST','/api/v1/ordenes-compra/calcular',[
      'proveedor_id'=>1,'solicitada_en'=>'2026-11-23',
      'detalles'=>[['producto_id'=>1,'cantidad'=>'1.000',
        'costo_unitario_neto'=>'100.0000']]
    ]),'Cotización sin JWT');
    $providerId=(int)DB::table('proveedores')->insertGetId([
        'nombre'=>'Proveedor V55 CI','correo_electronico'=>'no-real@example.invalid',
        'rut'=>'12345678-5','activo'=>1
    ]);
    $productId=(int)DB::table('productos')->insertGetId([
        'sku'=>'SKU-V55-CI','nombre'=>'Producto V55 CI','activo'=>1
    ]);
    $otherId=(int)DB::table('productos')->insertGetId([
        'sku'=>'SKU-V55-OTRO','nombre'=>'Producto no asociado','activo'=>1
    ]);
    DB::table('productos_proveedores')->insert([
        'producto_id'=>$productId,'proveedor_id'=>$providerId,
        'activo'=>1,'costo_proveedor'=>'100.0000'
    ]);
    $suppliers=expectOC(200,callOC('GET','/api/v1/ordenes-compra/proveedores',
        [],$token),'proveedores');
    checkOC($suppliers['total']>=1,'Proveedor no disponible');
    $states=expectOC(200,callOC('GET','/api/v1/ordenes-compra/estados',
        [],$token),'estados');
    checkOC(count($states['items'])===3,'Tres estados por modulo');

    $body=[
        'proveedor_id'=>$providerId,'solicitada_en'=>'2026-11-23',
        'observaciones'=>'Pedido de prueba en MySQL efímero',
        'detalles'=>[['producto_id'=>$productId,'cantidad'=>'2.000',
            'costo_unitario_neto'=>'100.0000']]
    ];
    // La vista previa usa el cálculo fiscal exacto de MariaDB y no escribe.
    $beforePreview=(int)DB::table('ordenes_compra')->count();
    $preview=expectOC(200,callOC('POST','/api/v1/ordenes-compra/calcular',
        $body,$token),'previsualización autorizada');
    checkOC((float)$preview['subtotal_neto']===200.00,'Subtotal de previsualización');
    checkOC((float)$preview['total_impuestos']===38.00,'Impuestos de previsualización');
    checkOC((float)$preview['total_bruto']===238.00,'Total bruto de previsualización');
    checkOC((int)DB::table('ordenes_compra')->count()===$beforePreview,
        'Previsualizar no debe insertar ni modificar OC');
    expectOC(422,callOC('POST','/api/v1/ordenes-compra',
        array_merge($body,['estado_id'=>999]),$token),'estado inmutable');
    $bad=$body;$bad['detalles'][0]['producto_id']=$otherId;
    expectOC(422,callOC('POST','/api/v1/ordenes-compra',$bad,$token),
        'Producto ajeno al proveedor');

    // SMTP se simula estrictamente en memoria; NUNCA envío externo.
    config(['mail.default'=>'smtp']);
    $correoSimulado=new class {
        public array $entregas=[];
        public function html(string $html,callable $callback): void
        {
            $message=new class {
                public string $destinatario='';
                public string $asunto='';
                public string $archivo='';
                public string $pdf='';
                public function to(string $correo): self {
                    $this->destinatario=$correo;return $this;
                }
                public function subject(string $asunto): self {
                    $this->asunto=$asunto;return $this;
                }
                public function attachData(string $pdf,string $nombre,array $opciones=[]): self {
                    if(($opciones['mime']??'')!=='application/pdf')
                        throw new RuntimeException('MIME PDF inválido');
                    $this->archivo=$nombre;$this->pdf=$pdf;return $this;
                }
            };
            $callback($message);
            $this->entregas[]=[
                'destino'=>$message->destinatario,
                'asunto'=>$message->asunto,
                'archivo'=>$message->archivo,
                'pdf_valido'=>str_starts_with($message->pdf,'%PDF-1.4'),
                'html_valido'=>str_contains($html,'ORDEN DE COMPRA GENERADA'),
            ];
        }
    };
    Mail::swap($correoSimulado);
    $new=expectOC(201,callOC('POST','/api/v1/ordenes-compra',$body,$token),
        'guardar y emitir');
    checkOC((float)$new['subtotal_neto']===200.00,'Respuesta subtotal persistido');
    checkOC((float)$new['total_impuestos']===38.00,'Respuesta impuestos persistidos');
    checkOC((float)$new['total_bruto']===238.00,'Respuesta total bruto persistido');
    checkOC(($new['correo_enviado']??false)===true,'Aviso simulado tras confirmar');
    checkOC(count($correoSimulado->entregas)===1,'Se debe enviar exactamente un correo en alta');
    checkOC($correoSimulado->entregas[0]['destino']==='no-real@example.invalid',
        'Destinatario del proveedor');
    checkOC($correoSimulado->entregas[0]['pdf_valido'] &&
        $correoSimulado->entregas[0]['html_valido'],'PDF y mensaje corporativo');
    $id=(int)($new['id']??0);
    checkOC($id>0,'Nuevo id requerido');
    $oc=DB::table('ordenes_compra')->where('id',$id)->first();
    $stateCode=DB::table('estados')->where('id',$oc->estado_id)->value('codigo');
    checkOC($stateCode==='ACTIVA','Estado inicial');
    checkOC($oc->subtotal_neto==='200.00' || (float)$oc->subtotal_neto===200.,
        'Subtotal calculado incorrectamente');
    checkOC((float)$oc->total_impuestos===38.,'IVA inicial incorrecto');
    checkOC((float)$oc->total_bruto===238.,'Total bruto incorrecto');
    checkOC((int)DB::table('detalles_ordenes_compra')->where('orden_compra_id',$id)
        ->value('producto_id')===$productId,'Detalle guardado como producto_id');

    $pdf=callOC('GET','/api/v1/ordenes-compra/'.$id.'/pdf',[],$token);
    checkOC($pdf[0]===200,'PDF HTTP 200');
    checkOC(str_starts_with((string)$pdf[2]->getContent(),'%PDF-1.4'),'PDF no válido');
    expectOC(200,callOC('POST','/api/v1/ordenes-compra/'.$id.'/reenviar',[],$token),
        'Reenvío solicitado sin crear otra orden');
    checkOC(count($correoSimulado->entregas)===2,
        'Reenvío debe crear segundo correo, nunca segunda orden');


    $modified=$body;$modified['detalles'][0]['cantidad']='1.000';
    $savedUpdate=expectOC(200,callOC('PUT','/api/v1/ordenes-compra/'.$id,
        $modified,$token),'Edición transaccional');
    checkOC((float)$savedUpdate['subtotal_neto']===100.00,'Subtotal editado confirmado');
    checkOC((float)$savedUpdate['total_impuestos']===19.00,'IVA editado confirmado');
    checkOC((float)$savedUpdate['total_bruto']===119.00,'Bruto editado confirmado');
    checkOC((float)DB::table('ordenes_compra')->where('id',$id)->value('total_bruto')===119.,
        'Total después de editar');
    expectOC(200,callOC('POST','/api/v1/ordenes-compra/'.$id.'/anular',[],$token),
        'Anular OC');
    checkOC(DB::table('ordenes_compra as oc')
        ->join('estados as e','e.id','=','oc.estado_id')
        ->where('oc.id',$id)->value('e.codigo')==='NULA','Estado NULA');
    expectOC(409,callOC('PUT','/api/v1/ordenes-compra/'.$id,$body,$token),
        'No editar nula');
    expectOC(409,callOC('POST','/api/v1/ordenes-compra/'.$id.'/anular',[],$token),
        'No anular dos veces');
    expectOC(409,callOC('POST','/api/v1/ordenes-compra/'.$id.'/reenviar',[],$token),
        'No reenviar nula');
    checkOC(DB::table('movimientos_inventario')->count()===0,
        'Orden de compra no puede alterar inventario');
    echo "V55_OC_JWT_MYSQL_OK estados, proveedor, guardar, PDF, mail fake, editar y anular\n";
}catch(Throwable $e){
    fwrite(STDERR,$e->getMessage()."\n");exit(1);
}
