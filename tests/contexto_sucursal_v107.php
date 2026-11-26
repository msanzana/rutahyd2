<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\ContextoSucursalControlador;
function check(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
check(config('app.env')==='testing'&&DB::getDatabaseName()===':memory:','Solo CI aislado');
foreach([
'CREATE TABLE sucursales(id INTEGER PRIMARY KEY,codigo TEXT,nombre TEXT,direccion TEXT,ciudad_id INTEGER,activo INTEGER)',
'CREATE TABLE roles(id INTEGER PRIMARY KEY,codigo TEXT)',
'CREATE TABLE usuarios(id INTEGER PRIMARY KEY,rol_id INTEGER,activo INTEGER)',
'CREATE TABLE usuarios_sucursales(usuario_id INTEGER,sucursal_id INTEGER,activo INTEGER,PRIMARY KEY(usuario_id,sucursal_id))',
'CREATE TABLE tokens_acceso(id INTEGER PRIMARY KEY,usuario_id INTEGER,expira_en TEXT)',
'CREATE TABLE sesiones_sucursales(token_acceso_id INTEGER PRIMARY KEY,sucursal_id INTEGER,version_contexto INTEGER,seleccionada_en TEXT)',
'CREATE TABLE sucursales_configuracion(id INTEGER PRIMARY KEY,contexto_obligatorio INTEGER)',
'CREATE TABLE registros_auditoria(id INTEGER PRIMARY KEY,usuario_id INTEGER,accion TEXT,tipo_entidad TEXT,entidad_id INTEGER,valores_anteriores TEXT,valores_nuevos TEXT)'
] as $sql)DB::statement($sql);
foreach([1,2,3] as $id)DB::table('sucursales')->insert(['id'=>$id,'codigo'=>'CI'.$id,'nombre'=>'Sucursal CI '.$id,'activo'=>$id!==3?1:0]);
DB::table('roles')->insert([['id'=>1,'codigo'=>'administrador'],['id'=>2,'codigo'=>'vendedor']]);
DB::table('usuarios')->insert([['id'=>1,'rol_id'=>1,'activo'=>1],['id'=>2,'rol_id'=>2,'activo'=>1],['id'=>3,'rol_id'=>2,'activo'=>1]]);
DB::table('usuarios_sucursales')->insert([['usuario_id'=>2,'sucursal_id'=>1,'activo'=>1],['usuario_id'=>2,'sucursal_id'=>2,'activo'=>0]]);
foreach([[1,1],[2,2],[3,2],[4,3]] as [$tid,$uid])DB::table('tokens_acceso')->insert(['id'=>$tid,'usuario_id'=>$uid,'expira_en'=>now()->addHour()]);
DB::table('sucursales_configuracion')->insert(['id'=>1,'contexto_obligatorio'=>0]);
function req(int $uid,int $tid,array $data=[]):Request{$r=Request::create('/','POST',$data);$r->setUserResolver(fn()=>(object)['id'=>$uid]);$r->attributes->set('id_token_acceso',$tid);return $r;}
function val($r):array{return $r->getData(true);}
function blocked(callable $f,int $code):void{try{$f();}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===$code,'Código de rechazo');return;}throw new RuntimeException('No bloqueado');}
$c=$app->make(ContextoSucursalControlador::class);
$state=val($c->estado(req(2,2)));check(!$state['contexto_obligatorio']&&count($state['sucursales'])===1&&$state['sucursal_actual']===null,'Configuración desactivada');
blocked(fn()=>$c->seleccionar(req(2,2,['sucursal_id'=>1,'version_contexto'=>0])),409);
check(DB::table('sesiones_sucursales')->count()===0,'Desactivado no escribe');
// Activación solamente en el fixture de pruebas, jamás en producción.
DB::table('sucursales_configuracion')->where('id',1)->update(['contexto_obligatorio'=>1]);
check(count(val($c->estado(req(1,1)))['sucursales'])===2,'Administrador solo activas');
check(count(val($c->estado(req(3,4)))['sucursales'])===0,'Sin accesos no ve sucursales');
blocked(fn()=>$c->estado(req(3,2)),401);
blocked(fn()=>$c->seleccionar(req(2,2,['sucursal_id'=>2,'version_contexto'=>0])),403);
blocked(fn()=>$c->seleccionar(req(1,1,['sucursal_id'=>3,'version_contexto'=>0])),403);
$x=val($c->seleccionar(req(2,2,['sucursal_id'=>1,'version_contexto'=>0])));
check($x['version_contexto']===1&&(int)$x['sucursal_actual']['id']===1,'Selección inicial');
check(val($c->estado(req(2,3)))['sucursal_actual']===null,'Contexto separado por JWT');
blocked(fn()=>$c->seleccionar(req(2,2,['sucursal_id'=>1,'version_contexto'=>0])),409);
check(val($c->seleccionar(req(2,2,['sucursal_id'=>1,'version_contexto'=>1])))['version_contexto']===1,'Selección idéntica idempotente');
DB::table('usuarios_sucursales')->where('usuario_id',2)->where('sucursal_id',2)->update(['activo'=>1]);
$x=val($c->seleccionar(req(2,2,['sucursal_id'=>2,'version_contexto'=>1])));
check($x['version_contexto']===2&&(int)$x['sucursal_actual']['id']===2,'Cambio de contexto versionado');
DB::table('usuarios_sucursales')->where('usuario_id',2)->where('sucursal_id',2)->update(['activo'=>0]);
check(val($c->estado(req(2,2)))['sucursal_actual']===null,'Revocación deja contexto no autorizado invisible');
blocked(fn()=>$c->seleccionar(req(2,2,['sucursal_id'=>2,'version_contexto'=>2])),403);
DB::table('tokens_acceso')->where('id',2)->update(['expira_en'=>now()->subHour()]);
blocked(fn()=>$c->estado(req(2,2)),401);
check(DB::table('registros_auditoria')->count()===2,'Solo selección efectiva auditada');
echo "V107_CONTEXT_OK\n";
