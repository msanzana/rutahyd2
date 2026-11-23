<?php
declare(strict_types=1);
/**
 * V50: ÚNICAMENTE MySQL efímero rutahyd2_es_local, sin escritura productiva.
 * Se ejecuta después de tests/integracion_catalogos_api.php (fixture admin).
 */
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
function checkCustomer(bool $ok,string $description): void{
  if(!$ok)throw new RuntimeException('V50 CI: '.$description);
}
function customerCall(string $method,string $url,array $body=[],?string $jwt=null): array{
  global $kernel;
  $headers=['HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json'];
  if($jwt!==null)$headers['HTTP_AUTHORIZATION']='Bearer '.$jwt;
  $req=Request::create($url,$method,[],[],[],$headers,
    in_array($method,['POST','PUT'],true)?json_encode($body,JSON_THROW_ON_ERROR):null);
  $resp=$kernel->handle($req);
  $out=[$resp->getStatusCode(),json_decode((string)$resp->getContent(),true)];
  $kernel->terminate($req,$resp);
  return $out;
}
function customerExpect(int $status,array $reply,string $label): array{
  checkCustomer($reply[0]===$status,$label.' expected '.$status.' got '.$reply[0].
    ' body '.json_encode($reply[1],JSON_UNESCAPED_UNICODE));
  return is_array($reply[1])?$reply[1]:[];
}
try{
  checkCustomer(DB::getDatabaseName()==='rutahyd2_es_local','Unsafe database selected');
  checkCustomer(count(DB::select("SHOW TABLES LIKE 'tipos_juridicos_clientes'"))===1,
    'DDL37 legal types absent');
  checkCustomer(count(DB::select("SHOW COLUMNS FROM clientes LIKE 'sector'"))===0,
    'Legacy sector still present');
  checkCustomer(count(DB::select("SHOW COLUMNS FROM clientes LIKE 'comuna'"))===0,
    'Legacy comuna still present');
  checkCustomer(count(DB::select("SHOW COLUMNS FROM clientes LIKE 'tipo_juridico_id'"))===1,
    'FK tipo_juridico_id missing');
  $types=DB::table('tipos_juridicos_clientes')->pluck('id','codigo')->all();
  checkCustomer(isset($types['EMPRESA'],$types['PERSONA_NATURAL']),'Both seeds required');
  customerExpect(401,customerCall('GET','/api/v1/clientes/tipos-juridicos'),
    'Legal types require authentication');
  $admin=customerExpect(200,customerCall('POST','/api/v1/sesion',[
    'correo_electronico'=>'integracion@ejemplo.invalid',
    'contrasena'=>'ClaveCI!soloParaPruebas-2026'
  ]),'Temporary admin login')['token'];
  $lookup=customerExpect(200,customerCall('GET','/api/v1/clientes/tipos-juridicos',[],$admin),
    'Legal type listing');
  checkCustomer(count($lookup['items'])===2,'Expected two legal types');
  $base=[
    'nombre'=>'Comercial CI V50','rut'=>'12345678-5',
    'tipo_juridico_id'=>(int)$types['EMPRESA'],
    'telefono'=>'+56 912345678',
    'correo_electronico'=>'clientev50@example.invalid',
    'direccion'=>'Av. Prueba 123','ciudad_id'=>null
  ];
  foreach(['+56912345678','+56 12345678','+56 1234567890',
    '56912345678','+56 ABCDEFGHI','+56 912 345 678'] as $phone){
    customerExpect(422,customerCall('POST','/api/v1/recursos/clientes',
      array_replace($base,['telefono'=>$phone]),$admin),
      'Invalid Chilean phone must be rejected: '.$phone);
  }
  customerExpect(422,customerCall('POST','/api/v1/recursos/clientes',
    array_replace($base,['rut'=>'12345678-9']),$admin),'Wrong RUT DV');
  customerExpect(422,customerCall('POST','/api/v1/recursos/clientes',
    array_replace($base,['tipo_juridico_id'=>999999]),$admin),'Unknown legal type');
  customerExpect(422,customerCall('POST','/api/v1/recursos/clientes',
    array_replace($base,['sector'=>'Antiguo']),$admin),'Deprecated sector disallowed');
  customerExpect(422,customerCall('POST','/api/v1/recursos/clientes',
    array_replace($base,['comuna'=>'Temuco']),$admin),'Deprecated comuna disallowed');
  $created=customerExpect(201,customerCall('POST','/api/v1/recursos/clientes',$base,$admin),
    'Valid +56 phone and legal type');
  $id=(int)($created['id']??0);
  checkCustomer($id>0&&$created['telefono']==='+56 912345678','Phone must be stored verbatim');
  checkCustomer((int)$created['tipo_juridico_id']===(int)$types['EMPRESA'],
    'Legal FK stored');
  // V52: Código de registro es exclusivamente el ID del servidor.
  customerExpect(422,customerCall('PUT','/api/v1/recursos/clientes/'.$id,
    ['codigo'=>'99999999'],$admin),'Legacy business code not writable');
  $activity=DB::table('giros_sii')->where('activo',1)
    ->orderBy('codigo')->first(['codigo','descripcion']);
  checkCustomer((bool)$activity,'Test needs active SII giro');
  $companyWithGiro=customerExpect(200,customerCall('PUT',
    '/api/v1/recursos/clientes/'.$id,[
      'tipo_juridico_id'=>(int)$types['EMPRESA'],
      'actividad_economica_codigo'=>$activity->codigo
    ],$admin),'Company giro SII allowed');
  checkCustomer($companyWithGiro['giro']===$activity->descripcion,
    'Official activity description required for empresa');

  $baseList=DB::table('listas_precios')->where('es_base',1)->value('id');
  checkCustomer((int)$baseList>0,'Base price list missing');
  checkCustomer(DB::table('clientes_listas_precios')
    ->where('cliente_id',$id)->where('lista_precio_id',$baseList)
    ->where('activo',1)->exists(),'New customer assigned active base list');
  // V51: alta con lista especial debe dejar una única fila de la tabla pivote,
  // dentro de la misma transacción; base se asigna solo si no hay selección.
  $specialId=(int)DB::table('listas_precios')->insertGetId([
    'codigo'=>'V51_ESPECIAL_CI',
    'nombre'=>'LISTA ESPECIAL CI',
    'es_base'=>0,'activo'=>1,
    'creado_en'=>now(),'actualizado_en'=>now()
  ]);
  checkCustomer($specialId>0,'Special price list fixture missing');
  $specialInput=array_replace($base,[
    'nombre'=>'Cliente Lista Especial CI',
    'rut'=>'11111111-1',
    'correo_electronico'=>'especial-v51@example.invalid',
    'lista_precio_id'=>$specialId
  ]);
  $specialCreated=customerExpect(201,customerCall('POST','/api/v1/recursos/clientes',
    $specialInput,$admin),'New customer with selected special price list');
  $specialCustomerId=(int)($specialCreated['id']??0);
  checkCustomer($specialCustomerId>0,'New special customer ID not returned');
  $pivot=DB::table('clientes_listas_precios')
    ->where('cliente_id',$specialCustomerId)->get();
  checkCustomer($pivot->count()===1&&
    (int)$pivot->first()->lista_precio_id===$specialId&&
    (int)$pivot->first()->activo===1,
    'Chosen special list must replace BASE in exactly one active pivot row');
  $specialList=customerExpect(200,customerCall('GET',
    '/api/v1/clientes/'.$specialCustomerId.'/lista-precio',[],$admin),
    'Confirm selected special list is active');
  checkCustomer((int)($specialList['lista']['id']??0)===$specialId,
    'The listed active price list must be the selected special one');
  // Inactive lists cannot be assigned; reject before creating a customer.
  DB::table('listas_precios')->where('id',$specialId)->update(['activo'=>0]);
  $countBefore=DB::table('clientes')->count();
  customerExpect(422,customerCall('POST','/api/v1/recursos/clientes',
    array_replace($specialInput,[
      'rut'=>'22222222-2',
      'lista_precio_id'=>$specialId
    ]),$admin),'Inactive price list must not create customer');
  checkCustomer(DB::table('clientes')->count()===$countBefore,
    'Rejected price list must not partially create customer');
  DB::table('listas_precios')->where('id',$specialId)->update(['activo'=>1]);
  // Editing may return to BASE without leaving another pivot record.
  customerExpect(200,customerCall('PUT','/api/v1/recursos/clientes/'.
    $specialCustomerId,['lista_precio_id'=>(int)$baseList],$admin),
    'Change active price list on existing customer');
  $pivot=DB::table('clientes_listas_precios')
    ->where('cliente_id',$specialCustomerId)->get();
  checkCustomer($pivot->count()===1&&
    (int)$pivot->first()->lista_precio_id===(int)$baseList,
    'Editing list must replace the pivot record, not duplicate it');

  $list=customerExpect(200,customerCall('GET','/api/v1/recursos/clientes?'.
    http_build_query(['page'=>1,'por_pagina'=>25,'filtros'=>[
      'tipo_juridico_id'=>(int)$types['EMPRESA'],'activo'=>'1',
      'rut'=>'12345678-5','lista_precio_id'=>(int)$baseList
    ]]),[],$admin),'Advanced search across linked tables');
  $found=array_values(array_filter($list['data'],fn($row)=>(int)$row['id']===$id));
  checkCustomer(count($found)===1,'Customer not found by advanced filters');
  checkCustomer(($found[0]['tipo_juridico_nombre']??'')==='Empresa',
    'Legal type label not joined');
  checkCustomer(($found[0]['lista_precio_nombre']??'')==='LISTA BASE',
    'Active price list not joined');
  customerExpect(422,customerCall('GET','/api/v1/recursos/clientes?'.
    http_build_query(['filtros'=>['sector'=>'Antiguo']]),[],$admin),
    'Advanced search rejects deprecated field');
  customerExpect(422,customerCall('PUT','/api/v1/recursos/clientes/'.$id,
    ['telefono'=>'+56 1234'],$admin),'Edit rejects invalid phone');
  $edited=customerExpect(200,customerCall('PUT','/api/v1/recursos/clientes/'.$id,[
    'nombre'=>'Cliente de prueba renovado',
    'telefono'=>'+56 987654321',
    'tipo_juridico_id'=>(int)$types['PERSONA_NATURAL']
  ],$admin),'Edit customer legal type/phone');
  checkCustomer(($edited['telefono']??'')==='+56 987654321','Edited phone persisted');
  checkCustomer(($edited['giro']??null)===null &&
    ($edited['actividad_economica_codigo']??null)===null,
    'Switching to persona natural must clear giro and code SII');
  $page=customerExpect(200,customerCall('GET','/api/v1/recursos/clientes?'.
    http_build_query(['filtros'=>['tipo_juridico_id'=>(int)$types['PERSONA_NATURAL']],
      'orden'=>'nombre','direccion_orden'=>'asc']),[],$admin),'Sort/filter edited customer');
  checkCustomer(in_array($id,array_map('intval',array_column($page['data'],'id')),true),
    'Edited customer not in filtered page');
  $wrong=customerExpect(200,customerCall('GET','/api/v1/recursos/clientes?'.
    http_build_query(['filtros'=>['tipo_juridico_id'=>(int)$types['EMPRESA']]]),
    [],$admin),'Old filter must not find edited customer');
  checkCustomer(!in_array($id,array_map('intval',array_column($wrong['data'],'id')),true),
    'Old legal type still joined');
  $off=customerExpect(200,customerCall('DELETE','/api/v1/recursos/clientes/'.$id,
    [],$admin),'Soft delete');
  checkCustomer(($off['activo']??null)===false,'Soft delete state');
  customerExpect(200,customerCall('PUT','/api/v1/recursos/clientes/'.$id,
    ['activo'=>true],$admin),'Reactivate preserving legal type and phone');
  echo "V52_BACK_OK: empresa-only giro, immutable internal code, pivot and phone +56 NNNNNNNNN, RUT, legal types, CRUD, advanced filters, active price list, JWT.\n";
}catch(Throwable $e){
  fwrite(STDERR,'V50_BACK_FAIL '.$e->getMessage()."\n");
  exit(1);
}
