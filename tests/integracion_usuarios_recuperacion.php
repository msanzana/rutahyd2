<?php
declare(strict_types=1);
/** CI únicamente: requiere MySQL efímero rutahyd2_es_local y usuarios de prueba previos. */
require dirname(__DIR__) . '/vendor/autoload.php';
$app=require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function assertOk(bool $value,string $message): void {
    if(!$value)throw new RuntimeException($message);
}
function reqApi(string $method,string $uri,array $payload=[],?string $token=null): array {
    global $kernel;
    $server=['HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'application/json'];
    if($token!==null)$server['HTTP_AUTHORIZATION']='Bearer '.$token;
    $request=Request::create($uri,$method,[],[],[],$server,
        in_array($method,['POST','PUT','PATCH'],true)
            ?json_encode($payload,JSON_THROW_ON_ERROR):null);
    $response=$kernel->handle($request);
    $out=[$response->getStatusCode(),json_decode((string)$response->getContent(),true)];
    $kernel->terminate($request,$response);
    return $out;
}
function statusCode(int $want,array $got,string $label): array {
    assertOk($got[0]===$want,$label.' esperaba '.$want.', obtuvo '.$got[0]);
    return is_array($got[1])?$got[1]:[];
}
try{
 assertOk(DB::getDatabaseName()==='rutahyd2_es_local',
    'PROHIBIDO EJECUTAR EN PRODUCCIÓN');
 assertOk(DB::getSchemaBuilder()->hasTable('restablecimientos_contrasena'),'Falta SQL18');
 $adminRole=(int)DB::table('roles')->where('codigo','administrador')->value('id');
 $role=(int)DB::table('roles')->where('codigo','vendedor')->value('id');
 statusCode(401,reqApi('GET','/api/v1/usuarios'),'Anónimo');
 $admin=statusCode(200,reqApi('POST','/api/v1/sesion',[
    'correo_electronico'=>'integracion@ejemplo.invalid',
    'contrasena'=>'ClaveCI!soloParaPruebas-2026',
 ]),'Login administrador')['token'];
 $ordinary=statusCode(200,reqApi('POST','/api/v1/sesion',[
    'correo_electronico'=>'lector@ejemplo.invalid',
    'contrasena'=>'NoUsarEnProduccion-2026'
 ]),'Login vendedor')['token'];
 statusCode(403,reqApi('GET','/api/v1/usuarios',[],$ordinary),'Usuarios protegidos');
 $list=statusCode(200,reqApi('GET','/api/v1/usuarios',[],$admin),'Listar usuarios');
 assertOk(count($list['usuarios'])>=2,'Listado protegido no retornó usuarios');
 statusCode(422,reqApi('POST','/api/v1/usuarios',[
    'nombre'=>'Prueba','correo_electronico'=>'nuevo@example.invalid','rol_id'=>$adminRole
 ],$admin),'No crear admin desde CRUD');
 statusCode(503,reqApi('POST','/api/v1/usuarios',[
    'nombre'=>'Prueba','correo_electronico'=>'nuevo@example.invalid','rol_id'=>$role
 ],$admin),'No invitar sin SMTP');
 // Crear operador exclusivamente como fixture CI; ninguna contraseña productiva.
 $id=DB::table('usuarios')->insertGetId([
   'nombre'=>'Operador para CI','correo_electronico'=>'operador-ci@example.invalid',
   'rol_id'=>$role,'hash_contrasena'=>Hash::make('PasswordTemporalPrueba-2026!'),'activo'=>1
 ]);
 $userJwt=statusCode(200,reqApi('POST','/api/v1/sesion',[
   'correo_electronico'=>'operador-ci@example.invalid',
   'contrasena'=>'PasswordTemporalPrueba-2026!'
 ]),'Login operador')['token'];
 statusCode(200,reqApi('GET','/api/v1/perfil',[],$userJwt),'JWT operador vigente');
 statusCode(200,reqApi('PUT','/api/v1/usuarios/'.$id,[
   'nombre'=>'Operador actualizado CI','correo_electronico'=>'operador-ci@example.invalid',
   'rol_id'=>$role,'activo'=>true
 ],$admin),'Editar usuario');
 statusCode(401,reqApi('GET','/api/v1/perfil',[],$userJwt),'Se revoca JWT al editar');
 statusCode(403,reqApi('PUT','/api/v1/usuarios/'.
     DB::table('usuarios')->where('correo_electronico','integracion@ejemplo.invalid')->value('id'),[
   'nombre'=>'Administradora','correo_electronico'=>'integracion@ejemplo.invalid',
   'rol_id'=>$role,'activo'=>true
 ],$admin),'Administrador protegido');
 statusCode(503,reqApi('POST','/api/v1/recuperacion/solicitar',[
   'correo_electronico'=>'ausente@example.invalid'
 ]),'No enumerar cuentas sin SMTP');
 $expired=bin2hex(random_bytes(32));
 DB::table('restablecimientos_contrasena')->insert([
   'usuario_id'=>$id,'hash_token'=>hash('sha256',$expired),
   'expira_en'=>now()->subMinutes(1),'creado_en'=>now()->subMinutes(40)
 ]);
 statusCode(422,reqApi('POST','/api/v1/recuperacion/restablecer',[
   'token'=>$expired,'contrasena'=>'OtraContraseñaLargaCI123!',
   'contrasena_confirmation'=>'OtraContraseñaLargaCI123!'
 ]),'Token vencido');
 $valid=bin2hex(random_bytes(32));
 DB::table('restablecimientos_contrasena')->where('usuario_id',$id)->delete();
 DB::table('restablecimientos_contrasena')->insert([
   'usuario_id'=>$id,'hash_token'=>hash('sha256',$valid),
   'expira_en'=>now()->addMinutes(30),'creado_en'=>now()
 ]);
 statusCode(200,reqApi('POST','/api/v1/recuperacion/restablecer',[
   'token'=>$valid,'contrasena'=>'OtraContrasenaLargaCI123!',
   'contrasena_confirmation'=>'OtraContrasenaLargaCI123!'
 ]),'Restablecer contraseña');
 statusCode(422,reqApi('POST','/api/v1/recuperacion/restablecer',[
   'token'=>$valid,'contrasena'=>'OtraContrasenaLargaCI123!',
   'contrasena_confirmation'=>'OtraContrasenaLargaCI123!'
 ]),'No reutilizar token');
 $newJwt=statusCode(200,reqApi('POST','/api/v1/sesion',[
   'correo_electronico'=>'operador-ci@example.invalid',
   'contrasena'=>'OtraContrasenaLargaCI123!'
 ]),'Login contraseña nueva')['token'];
 statusCode(204,reqApi('DELETE','/api/v1/usuarios/'.$id,[],$admin),'Baja lógica');
 statusCode(401,reqApi('GET','/api/v1/perfil',[],$newJwt),'JWT desactivado');
 assertOk((int)DB::table('usuarios')->where('id',$id)->value('activo')===0,
   'No se debe borrar historial de usuario');
 echo "OK: CRUD usuarios, restricciones administrador, recuperación y revocación JWT en MySQL efímero.\n";
}catch(\Throwable $e){
 fwrite(STDERR,"ERROR usuarios CI: ".$e->getMessage()."\n");exit(1);
}
