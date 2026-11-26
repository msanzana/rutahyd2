<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\SucursalesControlador;
function check(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
check(config('app.env')==='testing'&&DB::getDatabaseName()===':memory:','Solo CI aislado');
foreach([
'CREATE TABLE sucursales(id INTEGER PRIMARY KEY,codigo TEXT UNIQUE,nombre TEXT,ciudad_id INTEGER,direccion TEXT,activo INTEGER)',
'CREATE TABLE ciudades(id INTEGER PRIMARY KEY,nombre TEXT,activo INTEGER)',
'CREATE TABLE bodegas(id INTEGER PRIMARY KEY,codigo TEXT,nombre TEXT,activo INTEGER)',
'CREATE TABLE inventario_bodegas_sucursales(bodega_id INTEGER PRIMARY KEY,sucursal_id INTEGER)',
'CREATE TABLE roles(id INTEGER PRIMARY KEY,codigo TEXT,nombre TEXT)',
'CREATE TABLE usuarios(id INTEGER PRIMARY KEY,nombre TEXT,activo INTEGER,rol_id INTEGER)',
'CREATE TABLE usuarios_sucursales(usuario_id INTEGER,sucursal_id INTEGER,activo INTEGER,asignado_por INTEGER,asignado_en TEXT,PRIMARY KEY(usuario_id,sucursal_id))',
'CREATE TABLE tokens_acceso(id INTEGER PRIMARY KEY,usuario_id INTEGER)',
'CREATE TABLE sesiones_sucursales(token_acceso_id INTEGER PRIMARY KEY,sucursal_id INTEGER)',
'CREATE TABLE registros_auditoria(id INTEGER PRIMARY KEY,usuario_id INTEGER,accion TEXT,tipo_entidad TEXT,entidad_id INTEGER,valores_anteriores TEXT,valores_nuevos TEXT)'
] as $s)DB::statement($s);
foreach(['ordenes_compra','compras','recepciones','recepciones_consolidadas','ventas','notas_venta','kardex','inventario_operaciones','inventario_conteos','inventario_reservas'] as $t)DB::statement('CREATE TABLE '.$t.'(id INTEGER PRIMARY KEY,sucursal_id INTEGER)');
foreach(['guias_despacho','inventario_traspasos'] as $t)DB::statement('CREATE TABLE '.$t.'(id INTEGER PRIMARY KEY,sucursal_origen_id INTEGER,sucursal_destino_id INTEGER)');
DB::table('roles')->insert([['id'=>1,'codigo'=>'administrador','nombre'=>'Admin CI'],['id'=>2,'codigo'=>'vendedor','nombre'=>'Operador CI']]);
DB::table('usuarios')->insert([['id'=>1,'nombre'=>'Admin CI','activo'=>1,'rol_id'=>1],['id'=>2,'nombre'=>'Operador CI','activo'=>1,'rol_id'=>2]]);
DB::table('bodegas')->insert(['id'=>1,'codigo'=>'CI','nombre'=>'Bodega CI','activo'=>1]);
DB::table('ciudades')->insert(['id'=>1,'nombre'=>'Ciudad CI','activo'=>1]);
function req(array $data=[],bool $admin=true):Request{$r=Request::create('/','POST',$data);$r->setUserResolver(fn()=>(object)['id'=>$admin?1:2,'activo'=>1,'rol'=>(object)['codigo'=>$admin?'administrador':'vendedor']]);return $r;}
function val($r):array{return $r->getData(true);}
function blocked(callable $f,int $code):void{try{$f();}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===$code,'código rechazo');return;}throw new RuntimeException('No bloqueado');}
$c=$app->make(SucursalesControlador::class);
blocked(fn()=>$c->listar(req([],false)),403);
$a=val($c->crear(req(['codigo'=>'CI1','nombre'=>'Sucursal CI A','ciudad_id'=>1,'direccion'=>'Casa CI'])))['id'];
$b=val($c->crear(req(['codigo'=>'CI2','nombre'=>'Sucursal CI B'])))['id'];
$c->actualizar(req(['nombre'=>'Sucursal CI A editada','ciudad_id'=>1]),$a);
check(DB::table('sucursales')->where('id',$a)->value('codigo')==='CI1','Código estable');
check(DB::table('sucursales')->where('id',$a)->value('direccion')==='Casa CI','Dirección preservada');
$c->asignarBodega(req(),$a,1);$c->asignarBodega(req(),$a,1);
blocked(fn()=>$c->asignarBodega(req(),$b,1),409);
blocked(fn()=>$c->estado(req(['activo'=>false]),$a),409);
$c->acceso(req(['activo'=>true]),$a,2);$c->acceso(req(['activo'=>true]),$b,2);
check(DB::table('usuarios_sucursales')->where('usuario_id',2)->where('activo',1)->count()===2,'Acceso múltiple');
blocked(fn()=>$c->acceso(req(['activo'=>false]),$a,1),409);
DB::table('tokens_acceso')->insert([['id'=>1,'usuario_id'=>2],['id'=>2,'usuario_id'=>2]]);
DB::table('sesiones_sucursales')->insert([['token_acceso_id'=>1,'sucursal_id'=>$a],['token_acceso_id'=>2,'sucursal_id'=>$b]]);
$c->acceso(req(['activo'=>false]),$a,2);
check(DB::table('sesiones_sucursales')->count()===1&&DB::table('sesiones_sucursales')->value('sucursal_id')==$b,'Revocación selectiva de contexto');
check(val($c->listar(req()))['total']===2,'Listado');
check(val($c->bodegas(req(),$a))['total']===1,'Bodegas');
check(val($c->usuarios(req(),$a))['total']===2,'Usuarios');
check(DB::table('registros_auditoria')->count()===7,'Auditoría');
echo "V106_ADMIN_OK\n";
