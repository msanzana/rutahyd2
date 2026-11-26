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
check(config('app.env')==='testing'&&($mysql?DB::getDatabaseName()==='rutahyd_v109c3_ci':DB::getDatabaseName()===':memory:'),'Solo CI aislado');
if($mysql)check(DB::selectOne('SELECT DATABASE() AS db')->db==='rutahyd_v109c3_ci','Base CI incorrecta');
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
 'CREATE TABLE inventario_traspasos_detalle(id INTEGER PRIMARY KEY,traspaso_id INTEGER,producto_id INTEGER,cantidad_solicitada TEXT,cantidad_despachada TEXT,cantidad_recibida TEXT,cantidad_regularizada TEXT,costo_cpp_snapshot TEXT)',
 'CREATE TABLE inventario_traspasos(id INTEGER PRIMARY KEY,numero_interno TEXT UNIQUE,bodega_origen_id INTEGER,bodega_destino_id INTEGER,sucursal_origen_id INTEGER,sucursal_destino_id INTEGER,modalidad TEXT,estado TEXT,creado_por INTEGER,creado_en TEXT DEFAULT CURRENT_TIMESTAMP,guia_despacho_id INTEGER)',
 'CREATE TABLE inventario_traspasos_operaciones(traspaso_id INTEGER,operacion_id INTEGER,etapa TEXT,PRIMARY KEY(traspaso_id,operacion_id))',
 'CREATE TABLE kardex(id INTEGER PRIMARY KEY,sucursal_id INTEGER,producto_id INTEGER,bodega_id INTEGER,tipo_documento_id INTEGER,folio_documento TEXT,tipo_movimiento INTEGER,cantidad_anterior TEXT,cantidad_documento TEXT,cantidad_actual TEXT,transito_anterior TEXT,transito_actual TEXT,comprometido_anterior TEXT,comprometido_actual TEXT,fisico_anterior TEXT,fisico_actual TEXT,costo_unitario_neto TEXT,ultimo_costo_anterior TEXT,ultimo_costo_actual TEXT,cpp_anterior TEXT,cpp_actual TEXT,origen_tipo TEXT,origen_detalle_id INTEGER,operacion_codigo TEXT,token_idempotencia TEXT UNIQUE,movimiento_reversado_id INTEGER,registrado_por INTEGER,observaciones TEXT,fecha_movimiento TEXT DEFAULT CURRENT_TIMESTAMP)',
 'CREATE TABLE modulos(id INTEGER PRIMARY KEY,codigo TEXT,activo INTEGER)',
 'CREATE TABLE estados(id INTEGER PRIMARY KEY,codigo TEXT,nombre TEXT,activo INTEGER)',
 'CREATE TABLE modulos_estados(modulo_id INTEGER,estado_id INTEGER,activo INTEGER)',
 'CREATE TABLE inventario_grupos_motivos(id INTEGER PRIMARY KEY,codigo INTEGER,nombre TEXT,activo INTEGER)',
 'CREATE TABLE inventario_tipos_movimiento(id INTEGER PRIMARY KEY,codigo TEXT,nombre TEXT,naturaleza TEXT,activo INTEGER)',
 'CREATE TABLE inventario_motivos(id INTEGER PRIMARY KEY,codigo INTEGER,grupo_id INTEGER,tipo_movimiento_id INTEGER,nombre TEXT,regla TEXT,politica_cpp TEXT,activo INTEGER,ajuste_rapido INTEGER,requiere_referencia INTEGER,requiere_aprobacion INTEGER,requiere_respaldo INTEGER,revision_tributaria INTEGER,evaluar_aviso_sii INTEGER,indicador_traslado_sii INTEGER)',
 'CREATE TABLE inventario_indicadores_traslado_sii(codigo INTEGER PRIMARY KEY,nombre TEXT)',
 'CREATE TABLE inventario_referencias_sii(codigo INTEGER PRIMARY KEY,nombre TEXT)',
 'CREATE TABLE inventario_operaciones(id INTEGER PRIMARY KEY,sucursal_id INTEGER,numero_interno TEXT UNIQUE,clase TEXT,motivo_id INTEGER,estado_id INTEGER,motivo_codigo_snapshot INTEGER,motivo_nombre_snapshot TEXT,regla_snapshot TEXT,politica_cpp_snapshot TEXT,justificacion TEXT,referencia_tipo_documento_id INTEGER,referencia_folio TEXT,referencia_kardex_id INTEGER,revision_tributaria TEXT,evaluacion_aviso_sii TEXT,indicador_traslado_sii INTEGER,token_creacion TEXT UNIQUE,token_confirmacion TEXT UNIQUE,version INTEGER DEFAULT 1,creado_por INTEGER,aprobado_por INTEGER,confirmado_por INTEGER,anulado_por INTEGER,motivo_anulacion TEXT,creado_en TEXT DEFAULT CURRENT_TIMESTAMP,aprobado_en TEXT,confirmado_en TEXT,anulado_en TEXT)',
 'CREATE TABLE inventario_operaciones_detalle(id INTEGER PRIMARY KEY,operacion_id INTEGER,numero_linea INTEGER,producto_id INTEGER,bodega_id INTEGER,cantidad TEXT,delta_disponible TEXT,delta_fisico TEXT,delta_transito TEXT DEFAULT "0",delta_comprometido TEXT DEFAULT "0",kardex_referencia_id INTEGER,kardex_id INTEGER,costo_unitario_snapshot TEXT)',
 'CREATE TABLE inventario_operaciones_adjuntos(id INTEGER PRIMARY KEY,operacion_id INTEGER NOT NULL,nombre VARCHAR(190) NOT NULL,mime VARCHAR(100) NOT NULL,contenido MEDIUMBLOB NOT NULL,sha256 CHAR(64) NOT NULL,creado_por INTEGER NOT NULL,creado_en TEXT DEFAULT CURRENT_TIMESTAMP)',
 'CREATE TABLE inventario_operaciones_historial(id INTEGER PRIMARY KEY,operacion_id INTEGER,estado_anterior_id INTEGER,estado_nuevo_id INTEGER,usuario_id INTEGER,accion TEXT,observaciones TEXT)',
 'CREATE TABLE registros_auditoria(id INTEGER PRIMARY KEY,usuario_id INTEGER,accion TEXT,tipo_entidad TEXT,entidad_id INTEGER,valores_anteriores TEXT,valores_nuevos TEXT)'
] as $sql){
 if($mysql){
   $sql=str_replace('INTEGER PRIMARY KEY','BIGINT PRIMARY KEY AUTO_INCREMENT',$sql);
   $sql=preg_replace('/\\b(fecha_movimiento|creado_en) TEXT DEFAULT CURRENT_TIMESTAMP\\b/','$1 DATETIME DEFAULT CURRENT_TIMESTAMP',$sql);
   $sql=preg_replace('/\\b(expira_en|aprobado_en|confirmado_en|anulado_en) TEXT\\b/','$1 DATETIME NULL',$sql);
   $sql=preg_replace('/\\b(disponible|transito|comprometido|cantidad_fisica|cantidad|delta_disponible|delta_fisico|delta_transito|delta_comprometido|cantidad_anterior|cantidad_documento|cantidad_actual|transito_anterior|transito_actual|comprometido_anterior|comprometido_actual|fisico_anterior|fisico_actual|costo_unitario_neto|costo_neto|costo_promedio_ponderado|ultimo_costo_anterior|ultimo_costo_actual|cpp_anterior|cpp_actual|costo_unitario_snapshot|cantidad_solicitada|cantidad_despachada|cantidad_recibida|cantidad_regularizada|costo_cpp_snapshot) TEXT\\b/','$1 DECIMAL(28,8)',$sql);
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

DB::table('inventario_grupos_motivos')->insert(['id'=>7,'codigo'=>700,'nombre'=>'Especiales','activo'=>1]);
DB::table('inventario_tipos_movimiento')->insert(['id'=>5,'codigo'=>'TRASLADO','nombre'=>'Traslado','naturaleza'=>'TRASLADO','activo'=>1]);
DB::table('inventario_motivos')->insert(['id'=>62,'codigo'=>709,'grupo_id'=>7,'tipo_movimiento_id'=>5,'nombre'=>'Traspaso interno entre bodegas','regla'=>'TRASPASO_INTERNO','politica_cpp'=>'CONSERVAR','activo'=>1,'ajuste_rapido'=>0,'requiere_referencia'=>0,'requiere_aprobacion'=>1,'requiere_respaldo'=>0,'revision_tributaria'=>0,'evaluar_aviso_sii'=>0,'indicador_traslado_sii'=>5]);
$c=$app->make(\App\Http\Controllers\TraspasosInventarioControlador::class);
$payload=['bodega_origen_id'=>1,'bodega_destino_id'=>2,'modalidad'=>'INMEDIATO','items'=>[['producto_id'=>1,'cantidad'=>'1.12345678'],['producto_id'=>2,'cantidad'=>'2.25']],'justificacion'=>'Solicitud de traspaso CI','token_creacion'=>(string)Str::uuid()];
$before=DB::table('stock')->orderBy('id')->get()->toJson();$moves=DB::table('kardex')->count();
$x=val($c->crear(req($payload)));$id=$x['id'];$op=$x['operacion_id'];
check(!$x['repetida']&&val($c->crear(req($payload)))['repetida'],'Idempotencia creación');
blocked(fn()=>$c->crear(req(array_replace($payload,['modalidad'=>'DESPACHO_RECEPCION']))));
$t=DB::table('inventario_traspasos')->where('id',$id)->first();$o=DB::table('inventario_operaciones')->where('id',$op)->first();
check($t->estado==='BORRADOR'&&(int)$t->sucursal_origen_id===1&&(int)$t->sucursal_destino_id===1&&$o->clase==='TRASPASO'&&(int)$o->estado_id===2,'Cabeceras');
check(BigDecimal::of(DB::table('inventario_traspasos_detalle')->where('traspaso_id',$id)->where('producto_id',1)->value('cantidad_solicitada'))->isEqualTo('1.12345678'),'Decimal exacto persistido');
check(DB::table('inventario_traspasos_detalle')->where('traspaso_id',$id)->count()===2&&DB::table('inventario_operaciones_detalle')->where('operacion_id',$op)->count()===4,'Detalle multi SKU');
check(DB::table('inventario_traspasos_operaciones')->where('traspaso_id',$id)->value('etapa')==='INMEDIATO','Etapa');
$approval=['version'=>1,'motivo'=>'Autorizado CI'];
blocked(fn()=>$c->aprobar(req($approval),$id));
DB::table('productos')->where('id',1)->update(['costo_promedio_ponderado'=>501]);
blocked(fn()=>$c->aprobar(req($approval,2),$id));
DB::table('productos')->where('id',1)->update(['costo_promedio_ponderado'=>500]);
$approved=val($c->aprobar(req($approval,2),$id));check(!$approved['movimiento_aplicado']&&$approved['estado_operacion']==='INV_APROBADA','Aprobación');
blocked(fn()=>$c->aprobar(req($approval,2),$id));
$other=array_replace($payload,['modalidad'=>'DESPACHO_RECEPCION','token_creacion'=>(string)Str::uuid()]);
$y=val($c->crear(req($other)));check(DB::table('inventario_traspasos_operaciones')->where('traspaso_id',$y['id'])->value('etapa')==='DESPACHO','Etapa despacho');
DB::table('inventario_operaciones_detalle')->where('operacion_id',$y['operacion_id'])->where('bodega_id',2)->update(['delta_transito'=>0]);
blocked(fn()=>$c->aprobar(req($approval,2),$y['id']));
$insufficient=array_replace($payload,['items'=>[['producto_id'=>1,'cantidad'=>'6']],'token_creacion'=>(string)Str::uuid()]);
blocked(fn()=>$c->crear(req($insufficient)));
if($mysql)DB::unprepared("CREATE TRIGGER ci_fail_trp BEFORE INSERT ON inventario_traspasos_operaciones FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo CI'");
else DB::unprepared("CREATE TRIGGER ci_fail_trp BEFORE INSERT ON inventario_traspasos_operaciones BEGIN SELECT RAISE(ABORT,'Fallo CI'); END");
try{$c->crear(req(array_replace($payload,['token_creacion'=>(string)Str::uuid()])));throw new RuntimeException('No hubo rollback');}catch(\Illuminate\Database\QueryException $e){}
DB::unprepared('DROP TRIGGER ci_fail_trp');
check(DB::table('inventario_traspasos')->count()===2&&DB::table('inventario_operaciones')->count()===2&&DB::table('inventario_traspasos_operaciones')->count()===2,'Rollback');
check(DB::table('stock')->orderBy('id')->get()->toJson()===$before&&DB::table('kardex')->count()===$moves,'Solicitudes sin movimientos');
check(DB::table('inventario_operaciones_historial')->count()===3&&DB::table('registros_auditoria')->count()===3,'Trazabilidad');
echo "V109C3_SOLICITUDES_OK\n";
