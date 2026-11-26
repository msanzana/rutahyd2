<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Brick\Math\BigDecimal;
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$mysql=DB::connection()->getDriverName()==='mysql';
check(config('app.env')==='testing'&&($mysql?DB::getDatabaseName()==='rutahyd_v109a_ci':DB::getDatabaseName()===':memory:'),'Solo CI aislado');
if($mysql)check(DB::selectOne('SELECT DATABASE() AS db')->db==='rutahyd_v109a_ci','Base CI incorrecta');
foreach([
 'CREATE TABLE sucursales(id INTEGER PRIMARY KEY,activo INTEGER,codigo TEXT)',
 'CREATE TABLE roles(id INTEGER PRIMARY KEY,codigo TEXT)',
 'CREATE TABLE usuarios(id INTEGER PRIMARY KEY,rol_id INTEGER,activo INTEGER)',
 'CREATE TABLE tokens_acceso(id INTEGER PRIMARY KEY,usuario_id INTEGER,expira_en TEXT)',
 'CREATE TABLE sesiones_sucursales(token_acceso_id INTEGER PRIMARY KEY,sucursal_id INTEGER)',
 'CREATE TABLE usuarios_sucursales(usuario_id INTEGER,sucursal_id INTEGER,activo INTEGER)',
 'CREATE TABLE inventario_bodegas_sucursales(bodega_id INTEGER PRIMARY KEY,sucursal_id INTEGER)',
 'CREATE TABLE productos(id INTEGER PRIMARY KEY,sku TEXT,nombre TEXT,activo INTEGER,costo_neto TEXT,costo_promedio_ponderado TEXT)',
 'CREATE TABLE bodegas(id INTEGER PRIMARY KEY,nombre TEXT,activo INTEGER)',
 'CREATE TABLE stock(id INTEGER PRIMARY KEY,producto_id INTEGER,bodega_id INTEGER,disponible TEXT,transito TEXT,comprometido TEXT,cantidad_fisica TEXT,UNIQUE(producto_id,bodega_id))',
 'CREATE TABLE saldos_inventario(id INTEGER PRIMARY KEY,presentacion_id INTEGER,cantidad NUMERIC)',
 'CREATE TABLE presentaciones_productos(id INTEGER PRIMARY KEY,producto_id INTEGER)',
 'CREATE TABLE inventario_traspasos_detalle(id INTEGER PRIMARY KEY,producto_id INTEGER,cantidad_despachada NUMERIC,cantidad_recibida NUMERIC,cantidad_regularizada NUMERIC)',
 'CREATE TABLE kardex(id INTEGER PRIMARY KEY,sucursal_id INTEGER,producto_id INTEGER,bodega_id INTEGER,tipo_documento_id INTEGER,folio_documento TEXT,tipo_movimiento INTEGER,cantidad_anterior TEXT,cantidad_documento TEXT,cantidad_actual TEXT,transito_anterior TEXT,transito_actual TEXT,comprometido_anterior TEXT,comprometido_actual TEXT,fisico_anterior TEXT,fisico_actual TEXT,costo_unitario_neto TEXT,ultimo_costo_anterior TEXT,ultimo_costo_actual TEXT,cpp_anterior TEXT,cpp_actual TEXT,origen_tipo TEXT,origen_detalle_id INTEGER,operacion_codigo TEXT,token_idempotencia TEXT UNIQUE,movimiento_reversado_id INTEGER,registrado_por INTEGER,observaciones TEXT,fecha_movimiento TEXT DEFAULT CURRENT_TIMESTAMP)',
 'CREATE TABLE modulos(id INTEGER PRIMARY KEY,codigo TEXT,activo INTEGER)',
 'CREATE TABLE estados(id INTEGER PRIMARY KEY,codigo TEXT,nombre TEXT,activo INTEGER)',
 'CREATE TABLE modulos_estados(modulo_id INTEGER,estado_id INTEGER,activo INTEGER)',
 'CREATE TABLE inventario_grupos_motivos(id INTEGER PRIMARY KEY,codigo INTEGER,nombre TEXT,activo INTEGER)',
 'CREATE TABLE inventario_tipos_movimiento(id INTEGER PRIMARY KEY,codigo TEXT,nombre TEXT,naturaleza TEXT,activo INTEGER)',
 'CREATE TABLE inventario_motivos(id INTEGER PRIMARY KEY,codigo INTEGER,grupo_id INTEGER,tipo_movimiento_id INTEGER,nombre TEXT,regla TEXT,politica_cpp TEXT,activo INTEGER,ajuste_rapido INTEGER,requiere_referencia INTEGER,requiere_aprobacion INTEGER,requiere_respaldo INTEGER)',
 'CREATE TABLE inventario_indicadores_traslado_sii(codigo INTEGER PRIMARY KEY,nombre TEXT)',
 'CREATE TABLE inventario_referencias_sii(codigo INTEGER PRIMARY KEY,nombre TEXT)',
 'CREATE TABLE inventario_operaciones(id INTEGER PRIMARY KEY,sucursal_id INTEGER,numero_interno TEXT UNIQUE,clase TEXT,motivo_id INTEGER,estado_id INTEGER,motivo_codigo_snapshot INTEGER,motivo_nombre_snapshot TEXT,regla_snapshot TEXT,politica_cpp_snapshot TEXT,justificacion TEXT,referencia_tipo_documento_id INTEGER,referencia_folio TEXT,referencia_kardex_id INTEGER,revision_tributaria TEXT,token_creacion TEXT UNIQUE,token_confirmacion TEXT UNIQUE,version INTEGER DEFAULT 1,creado_por INTEGER,aprobado_por INTEGER,confirmado_por INTEGER,anulado_por INTEGER,motivo_anulacion TEXT,creado_en TEXT DEFAULT CURRENT_TIMESTAMP,aprobado_en TEXT,confirmado_en TEXT,anulado_en TEXT)',
 'CREATE TABLE inventario_operaciones_detalle(id INTEGER PRIMARY KEY,operacion_id INTEGER,numero_linea INTEGER,producto_id INTEGER,bodega_id INTEGER,cantidad TEXT,delta_disponible TEXT,delta_fisico TEXT,delta_transito TEXT DEFAULT "0",delta_comprometido TEXT DEFAULT "0",kardex_referencia_id INTEGER,kardex_id INTEGER,costo_unitario_snapshot TEXT)',
 'CREATE TABLE inventario_operaciones_historial(id INTEGER PRIMARY KEY,operacion_id INTEGER,estado_anterior_id INTEGER,estado_nuevo_id INTEGER,usuario_id INTEGER,accion TEXT,observaciones TEXT)',
 'CREATE TABLE registros_auditoria(id INTEGER PRIMARY KEY,usuario_id INTEGER,accion TEXT,tipo_entidad TEXT,entidad_id INTEGER,valores_anteriores TEXT,valores_nuevos TEXT)'
] as $sql){
 if($mysql){
   $sql=str_replace('INTEGER PRIMARY KEY','BIGINT PRIMARY KEY AUTO_INCREMENT',$sql);
   $sql=preg_replace('/\\b(fecha_movimiento|creado_en) TEXT DEFAULT CURRENT_TIMESTAMP\\b/','$1 DATETIME DEFAULT CURRENT_TIMESTAMP',$sql);
   $sql=preg_replace('/\\b(expira_en|aprobado_en|confirmado_en|anulado_en) TEXT\\b/','$1 DATETIME NULL',$sql);
   $sql=preg_replace('/\\b(disponible|transito|comprometido|cantidad_fisica|cantidad|delta_disponible|delta_fisico|delta_transito|delta_comprometido|cantidad_anterior|cantidad_documento|cantidad_actual|transito_anterior|transito_actual|comprometido_anterior|comprometido_actual|fisico_anterior|fisico_actual|costo_unitario_neto|costo_neto|costo_promedio_ponderado|ultimo_costo_anterior|ultimo_costo_actual|cpp_anterior|cpp_actual|costo_unitario_snapshot) TEXT\\b/','$1 DECIMAL(28,8)',$sql);
   $sql=str_replace('NUMERIC','DECIMAL(28,8)',$sql);
   $sql=preg_replace('/\\b(numero_interno|token_creacion|token_confirmacion|token_idempotencia) TEXT UNIQUE\\b/','$1 VARCHAR(100) UNIQUE',$sql);
 }
 DB::statement($sql);
}
DB::table('sucursales')->insert(['id'=>1,'activo'=>1,'codigo'=>'S01']);
DB::table('roles')->insert(['id'=>1,'codigo'=>'operador']);
foreach([1,2] as $u){DB::table('usuarios')->insert(['id'=>$u,'rol_id'=>1,'activo'=>1]);DB::table('tokens_acceso')->insert(['id'=>$u,'usuario_id'=>$u,'expira_en'=>now()->addHour()]);DB::table('sesiones_sucursales')->insert(['token_acceso_id'=>$u,'sucursal_id'=>1]);DB::table('usuarios_sucursales')->insert(['usuario_id'=>$u,'sucursal_id'=>1,'activo'=>1]);DB::table('inventario_bodegas_sucursales')->insert(['bodega_id'=>$u,'sucursal_id'=>1]);}
DB::table('modulos')->insert(['id'=>1,'codigo'=>'INVENTARIO_OPERACION','activo'=>1]);
foreach(['INV_BORRADOR','INV_PEND_APROBACION','INV_APROBADA','INV_CONFIRMADA','INV_ANULADA','INV_REVERSADA'] as $i=>$code){DB::table('estados')->insert(['id'=>$i+1,'codigo'=>$code,'nombre'=>$code,'activo'=>1]);DB::table('modulos_estados')->insert(['modulo_id'=>1,'estado_id'=>$i+1,'activo'=>1]);}
DB::table('inventario_grupos_motivos')->insert(['id'=>1,'codigo'=>200,'nombre'=>'Errores','activo'=>1]);
DB::table('inventario_tipos_movimiento')->insert(['id'=>1,'codigo'=>'MIXTO','nombre'=>'Mixto','naturaleza'=>'MIXTO','activo'=>1]);
foreach([[201,'CORREGIR_DESPACHO_SKU','HISTORICO'],[203,'CORREGIR_DESPACHO_BODEGA','CONSERVAR'],[207,'DESPACHO_NO_REALIZADO','HISTORICO']] as [$code,$rule,$cpp])DB::table('inventario_motivos')->insert(['id'=>$code,'codigo'=>$code,'grupo_id'=>1,'tipo_movimiento_id'=>1,'nombre'=>$rule,'regla'=>$rule,'politica_cpp'=>$cpp,'activo'=>1,'ajuste_rapido'=>1,'requiere_referencia'=>1,'requiere_aprobacion'=>1,'requiere_respaldo'=>0]);
foreach([1,2] as $id)DB::table('bodegas')->insert(['id'=>$id,'nombre'=>'Bodega '.$id,'activo'=>1]);
foreach([1,2,3,4,5] as $id){DB::table('productos')->insert(['id'=>$id,'sku'=>'CI-'.$id,'nombre'=>'Producto CI '.$id,'activo'=>1,'costo_neto'=>'500.0000','costo_promedio_ponderado'=>'500']);foreach([1,2] as $b)DB::table('stock')->insert(['producto_id'=>$id,'bodega_id'=>$b,'disponible'=>'5','transito'=>'1','comprometido'=>'2','cantidad_fisica'=>'8']);}
DB::table('stock')->where('producto_id',5)->where('bodega_id',2)->update(['disponible'=>'0','cantidad_fisica'=>'3']);
foreach([1,3,4,5] as $id)DB::table('kardex')->insert(['id'=>$id,'sucursal_id'=>1,'producto_id'=>$id,'bodega_id'=>1,'tipo_documento_id'=>1,'folio_documento'=>(string)$id,'tipo_movimiento'=>2,'cantidad_anterior'=>'6','cantidad_documento'=>'1','cantidad_actual'=>'5','transito_anterior'=>'1','transito_actual'=>'1','comprometido_anterior'=>'2','comprometido_actual'=>'2','fisico_anterior'=>'9','fisico_actual'=>'8','costo_unitario_neto'=>'500','ultimo_costo_anterior'=>'500','ultimo_costo_actual'=>'500','cpp_anterior'=>'500','cpp_actual'=>'500','origen_tipo'=>'VENTA','origen_detalle_id'=>$id,'operacion_codigo'=>'DESPACHO','token_idempotencia'=>(string)Str::uuid()]);
$c=$app->make(\App\Http\Controllers\InventarioOperacionesControlador::class);
function req(array $data=[],int $user=1): Request {$r=Request::create('/','POST',$data);$r->setUserResolver(fn()=> (object)['id'=>$user]);$r->attributes->set('id_token_acceso',$user);return $r;}
function val($response):array{return $response->getData(true);}
function balance(int $p,int $b,string $a,string $f):void{$s=DB::table('stock')->where('producto_id',$p)->where('bodega_id',$b)->first();check(BigDecimal::of($s->disponible)->isEqualTo($a)&&BigDecimal::of($s->cantidad_fisica)->isEqualTo($f)&&BigDecimal::of($s->transito)->isEqualTo(1)&&BigDecimal::of($s->comprometido)->isEqualTo(2),'saldo '.$p.':'.$b);}
function blocked(callable $f):void{try{$f();}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===409,'rechazo esperado');return;}throw new RuntimeException('No bloqueado');}
foreach([[201,1,['producto_real_id'=>2]],[203,3,['bodega_real_id'=>2]],[207,4,[]],[203,5,['bodega_real_id'=>2]]] as [$reason,$kid,$target]){
 $payload=['motivo_codigo'=>$reason,'kardex_id'=>$kid,'justificacion'=>'Prueba aislada de corrección','token_creacion'=>(string)Str::uuid()]+$target;
 $id=val($c->crear(req($payload)))['id'];check(val($c->crear(req($payload)))['id']===$id,'idempotencia creación');
 balance($kid,1,'5','8');
 $confirm=['version'=>2,'token_confirmacion'=>(string)Str::uuid()];
 blocked(fn()=>$c->confirmar(req($confirm),$id));
 $c->aprobar(req(['version'=>1,'motivo'=>'Autorización CI'],2),$id);
 if($kid===5){$before=DB::table('kardex')->count();blocked(fn()=>$c->confirmar(req($confirm,2),$id));balance(5,1,'5','8');balance(5,2,'0','3');check(DB::table('kardex')->count()===$before,'rollback atómico');continue;}
 $before=DB::table('kardex')->count();$c->confirmar(req($confirm,2),$id);$c->confirmar(req($confirm,2),$id);
 check(DB::table('kardex')->count()===$before+($reason===207?1:2),'sin duplicar Kardex');
 balance($kid,1,'6','9');
 if($reason===201)balance(2,1,'4','7');
 if($reason===203)balance(3,2,'4','7');
 check(DB::table('kardex')->where('id',$kid)->value('operacion_codigo')==='DESPACHO','original intacto');
 check(BigDecimal::of(DB::table('productos')->where('id',$kid)->value('costo_promedio_ponderado'))->isEqualTo(500),'CPP');
 blocked(fn()=>$c->crear(req(array_merge($payload,['token_creacion'=>(string)Str::uuid()]))));
}
check(DB::table('inventario_operaciones_historial')->count()===11,'historial de operaciones');
check(DB::table('registros_auditoria')->count()===11,'auditoría');
check(DB::table('inventario_operaciones')->where('sucursal_id',1)->count()===4,'operaciones atribuidas');
check(DB::table('kardex')->whereNull('sucursal_id')->count()===0,'kardex atribuido');
check(count(val($c->bodegas(req())))===2,'bodegas autorizadas');
DB::table('bodegas')->insert(['id'=>3,'nombre'=>'No asignada','activo'=>1]);
check(count(val($c->bodegas(req())))===2,'bodega no asignada excluida');
$payload=['motivo_codigo'=>203,'kardex_id'=>5,'bodega_real_id'=>3,'justificacion'=>'Rechazo de bodega sin asignar','token_creacion'=>(string)Str::uuid()];
blocked(fn()=>$c->crear(req($payload)));
// Reversa de salida histórica cuyo saldo actual no existe: el ID generado
// de stock no puede sobrescribir el contexto de sucursal.
DB::table('productos')->insert(['id'=>6,'sku'=>'CI-6','nombre'=>'Producto sin saldo CI','activo'=>1,'costo_neto'=>'500','costo_promedio_ponderado'=>'500']);
$origin=(array)DB::table('kardex')->where('id',4)->first();
$origin['id']=100;$origin['producto_id']=6;$origin['origen_detalle_id']=100;$origin['token_idempotencia']=(string)Str::uuid();
DB::table('kardex')->insert($origin);
$payload=['motivo_codigo'=>207,'kardex_id'=>100,'justificacion'=>'Reversa con saldo nuevo CI','token_creacion'=>(string)Str::uuid()];
$id=val($c->crear(req($payload)))['id'];
$c->aprobar(req(['version'=>1,'motivo'=>'Autorización CI'],2),$id);
$c->confirmar(req(['version'=>2,'token_confirmacion'=>(string)Str::uuid()],2),$id);
$created=DB::table('stock')->where('producto_id',6)->first();
check((int)$created->id!==1&&BigDecimal::of($created->disponible)->isEqualTo(1),'saldo nuevo');
check((int)DB::table('kardex')->where('movimiento_reversado_id',100)->value('sucursal_id')===1,'sucursal no confundida con saldo nuevo');
DB::table('sesiones_sucursales')->where('token_acceso_id',1)->delete();
blocked(fn()=>$c->listar(req()));
DB::table('sesiones_sucursales')->insert(['token_acceso_id'=>1,'sucursal_id'=>1]);
DB::table('sucursales')->insert(['id'=>2,'codigo'=>'S02','activo'=>0]);
blocked(fn()=>$c->bodegas(req()));
echo "V109A_MOTOR_OK\n";
