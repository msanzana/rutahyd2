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
check(config('app.env')==='testing'&&($mysql?DB::getDatabaseName()==='rutahyd_v109d2_ci':DB::getDatabaseName()===':memory:'),'Solo CI aislado');
if($mysql)check(DB::selectOne('SELECT DATABASE() AS db')->db==='rutahyd_v109d2_ci','Base CI incorrecta');
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

foreach([
 'CREATE TABLE inventario_conteos(id INTEGER PRIMARY KEY,numero_interno VARCHAR(60) UNIQUE,bodega_id INTEGER NOT NULL,alcance VARCHAR(30) NOT NULL,estado VARCHAR(30) NOT NULL,conteo_ciego INTEGER NOT NULL DEFAULT 1,fecha_corte VARCHAR(30),operacion_ajuste_id INTEGER UNIQUE,creado_por INTEGER NOT NULL,creado_en VARCHAR(30) DEFAULT CURRENT_TIMESTAMP,sucursal_id INTEGER)',
 'CREATE TABLE inventario_conteos_detalle(id INTEGER PRIMARY KEY,conteo_id INTEGER NOT NULL,producto_id INTEGER NOT NULL,saldo_corte TEXT NOT NULL,ultimo_kardex_id INTEGER,cantidad_contada TEXT,cantidad_recontada TEXT,contado_por INTEGER,recontado_por INTEGER,observaciones VARCHAR(1000))'
] as $sql){
 if($mysql){$sql=str_replace('INTEGER PRIMARY KEY','BIGINT PRIMARY KEY AUTO_INCREMENT',$sql);
 $sql=str_replace('fecha_corte VARCHAR(30)','fecha_corte DATETIME(6)',$sql);
 $sql=str_replace('creado_en VARCHAR(30) DEFAULT CURRENT_TIMESTAMP','creado_en DATETIME DEFAULT CURRENT_TIMESTAMP',$sql);
 $sql=preg_replace('/\b(saldo_corte|cantidad_contada|cantidad_recontada) TEXT\b/','$1 DECIMAL(28,8)',$sql);
 $sql=str_replace('alcance VARCHAR(30)',"alcance ENUM('GENERAL','BODEGA','CLASIFICACION','SELECTIVO')",$sql);
 $sql=str_replace('estado VARCHAR(30)',"estado ENUM('BORRADOR','EN_CONTEO','EN_REVISION')",$sql);}
 DB::statement($sql);
}
DB::table('usuarios')->insert(['id'=>3,'rol_id'=>1,'activo'=>1]);
DB::table('tokens_acceso')->insert(['id'=>3,'usuario_id'=>3,'expira_en'=>now()->addHour()]);
DB::table('sesiones_sucursales')->insert(['token_acceso_id'=>3,'sucursal_id'=>1]);
DB::table('usuarios_sucursales')->insert(['usuario_id'=>3,'sucursal_id'=>1,'activo'=>1]);
function req(array $data=[],int $uid=1): Request {$r=Request::create('/','POST',$data);$r->setUserResolver(fn()=>(object)['id'=>$uid]);$r->attributes->set('id_token_acceso',$uid);return $r;}
function val($r):array{return $r->getData(true);}
function reject(callable $f,int $status=409):void{try{$f();}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){check($e->getStatusCode()===$status,'Rechazo inesperado: '.$e->getMessage());return;}throw new RuntimeException('Faltó rechazo');}
$c=$app->make(\App\Http\Controllers\ConteosInventarioControlador::class);
$stock=DB::table('stock')->orderBy('id')->get()->toJson();$k=DB::table('kardex')->count();
$payload=['bodega_id'=>1,'productos'=>[2,1],'conteo_ciego'=>true,'token_creacion'=>(string)Str::uuid()];
$x=val($c->crear(req($payload)));$id=$x['id'];check(!$x['repetida']&&$x['estado']==='EN_CONTEO','Corte inicial');
check(val($c->crear(req($payload)))['repetida'],'Creación idempotente');
reject(fn()=>$c->crear(req(array_replace($payload,['productos'=>[3]]))));
reject(fn()=>$c->crear(req(array_replace($payload,['token_creacion'=>(string)Str::uuid()]))));
$details=val($c->ver(req([]),$id))['detalles'];check(count($details)===2&&!array_key_exists('saldo_corte',$details[0])&&!array_key_exists('ultimo_kardex_id',$details[0]),'Conteo ciego');
$l1=$details[0]['id'];$l2=$details[1]['id'];
reject(fn()=>$c->revisar(req([],3),$id));
$first=['cantidad'=>'6.12345678','observaciones'=>'Primer conteo'];
check(!val($c->contar(req($first),$id,$l1))['repetida'],'Primer conteo');
check(val($c->contar(req($first),$id,$l1))['repetida'],'Reintento');
reject(fn()=>$c->contar(req(['cantidad'=>'8']),$id,$l1));
reject(fn()=>$c->recontar(req(['cantidad'=>'6']),$id,$l1));
$other=val($c->ver(req([],2),$id))['detalles'][0];
check($other['contado']&&!array_key_exists('cantidad_contada',$other),'Recontador no ve cantidad previa');
$c->contar(req(['cantidad'=>'7']),$id,$l2);
reject(fn()=>$c->revisar(req([],3),$id));
$c->recontar(req(['cantidad'=>'6.12345678','observaciones'=>'Reconteo'],2),$id,$l1);
check(val($c->contar(req($first),$id,$l1))['repetida'],'Reintento original después de reconteo');
reject(fn()=>$c->revisar(req([],2),$id));
$res=val($c->revisar(req([],3),$id));check($res['estado']==='EN_REVISION'&&!$res['movimiento_aplicado']&&$res['planes'][0]['diferencia']==='-0.87654322'&&$res['planes'][1]['resultado']==='CUADRADO','Conciliación independiente');
$shown=val($c->ver(req([],3),$id))['detalles'][0];check(array_key_exists('saldo_corte',$shown)&&array_key_exists('cantidad_contada',$shown),'Visible en revisión');
reject(fn()=>$c->contar(req(['cantidad'=>'8']),$id,$l1));
check(DB::table('stock')->orderBy('id')->get()->toJson()===$stock&&DB::table('kardex')->count()===$k,'No modifica stock ni Kardex');
$payload=['bodega_id'=>2,'productos'=>[3],'conteo_ciego'=>true,'token_creacion'=>(string)Str::uuid()];$stale=val($c->crear(req($payload)))['id'];$line=val($c->ver(req([]),$stale))['detalles'][0]['id'];
$copy=(array)DB::table('kardex')->where('id',3)->first();unset($copy['id']);$copy['token_idempotencia']=(string)Str::uuid();DB::table('kardex')->insert($copy);
reject(fn()=>$c->contar(req(['cantidad'=>'7']),$stale,$line));
DB::table('inventario_conteos')->where('id',$stale)->update(['sucursal_id'=>999]);reject(fn()=>$c->ver(req([]),$stale),404);
echo "V109D2_REGISTRO_OK\n";
