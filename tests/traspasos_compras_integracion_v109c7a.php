<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\RecepcionesBorradorControlador as Controller;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

function check(bool $ok,string $message): void { if(!$ok)throw new RuntimeException($message); }
check(DB::getDatabaseName()==='rutahd_v109c7a_ci'&&config('app.env')==='testing','Prohibido ejecutar fuera de CI');
foreach([
"CREATE TABLE sucursales(id BIGINT PRIMARY KEY,activo TINYINT,codigo VARCHAR(20))",
"CREATE TABLE roles(id BIGINT PRIMARY KEY,codigo VARCHAR(30))",
"CREATE TABLE usuarios(id BIGINT PRIMARY KEY,rol_id BIGINT,activo TINYINT)",
"CREATE TABLE tokens_acceso(id BIGINT PRIMARY KEY,usuario_id BIGINT,expira_en DATETIME)",
"CREATE TABLE sesiones_sucursales(token_acceso_id BIGINT PRIMARY KEY,sucursal_id BIGINT)",
"CREATE TABLE usuarios_sucursales(usuario_id BIGINT,sucursal_id BIGINT,activo TINYINT)",
"CREATE TABLE inventario_bodegas_sucursales(bodega_id BIGINT PRIMARY KEY,sucursal_id BIGINT)",
"CREATE TABLE estados (id BIGINT UNSIGNED PRIMARY KEY,codigo VARCHAR(60),activo TINYINT)",
"CREATE TABLE modulos (id BIGINT UNSIGNED PRIMARY KEY,codigo VARCHAR(60),activo TINYINT)",
"CREATE TABLE modulos_estados (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,modulo_id BIGINT UNSIGNED,estado_id BIGINT UNSIGNED,activo TINYINT)",
"CREATE TABLE proveedores (id BIGINT UNSIGNED PRIMARY KEY,activo TINYINT)",
"CREATE TABLE bodegas (id BIGINT UNSIGNED PRIMARY KEY,activo TINYINT)",
"CREATE TABLE tipo_documentos (id BIGINT UNSIGNED PRIMARY KEY,codigo_sii SMALLINT,familia VARCHAR(24),activo TINYINT,documento_compra TINYINT)",
"CREATE TABLE ordenes_compra (id BIGINT UNSIGNED PRIMARY KEY,sucursal_id BIGINT NULL,proveedor_id BIGINT UNSIGNED,estado_id BIGINT UNSIGNED)",
"CREATE TABLE condiciones_tributarias (id BIGINT UNSIGNED PRIMARY KEY,codigo VARCHAR(10))",
"CREATE TABLE productos (id BIGINT UNSIGNED PRIMARY KEY,nombre VARCHAR(190),condicion_tributaria_id BIGINT UNSIGNED)",
"CREATE TABLE detalles_ordenes_compra (id BIGINT UNSIGNED PRIMARY KEY,orden_compra_id BIGINT UNSIGNED,producto_id BIGINT UNSIGNED)",
"CREATE TABLE compras (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,sucursal_id BIGINT NULL,proveedor_id BIGINT UNSIGNED NOT NULL,documento_id BIGINT UNSIGNED NOT NULL,folio VARCHAR(60) NOT NULL,orden_compra_id BIGINT UNSIGNED,fecha_documento DATE NOT NULL,estado_id BIGINT UNSIGNED NOT NULL,subtotal_neto DECIMAL(28,8) NOT NULL DEFAULT 0,total_exento DECIMAL(28,8) NOT NULL DEFAULT 0,iva_recuperable DECIMAL(28,8) NOT NULL DEFAULT 0,otros_impuestos DECIMAL(28,8) NOT NULL DEFAULT 0,total DECIMAL(28,8) NOT NULL DEFAULT 0,total_informado DECIMAL(28,8),signo_contable TINYINT NOT NULL DEFAULT 1,observaciones TEXT,creado_por BIGINT UNSIGNED,UNIQUE KEY uq_hd_compra_prov_doc_folio(proveedor_id,documento_id,folio)) ENGINE=InnoDB",
"CREATE TABLE recepciones (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,sucursal_id BIGINT NULL,numero_interno VARCHAR(60) NOT NULL UNIQUE,recepcion_consolidada_id BIGINT UNSIGNED,orden_compra_id BIGINT UNSIGNED,compra_id BIGINT UNSIGNED NOT NULL,proveedor_id BIGINT UNSIGNED NOT NULL,bodega_id BIGINT UNSIGNED NOT NULL,recepcion_tipo TINYINT NOT NULL,estado_id BIGINT UNSIGNED NOT NULL,fecha_recepcion DATETIME,subtotal_neto DECIMAL(28,8) NOT NULL DEFAULT 0,total_exento DECIMAL(28,8) NOT NULL DEFAULT 0,total_iva DECIMAL(28,8) NOT NULL DEFAULT 0,otros_impuestos DECIMAL(28,8) NOT NULL DEFAULT 0,total DECIMAL(28,8) NOT NULL DEFAULT 0,stock_aplicado TINYINT NOT NULL DEFAULT 0,token_confirmacion CHAR(36) UNIQUE,version_borrador INT UNSIGNED NOT NULL DEFAULT 0,observaciones TEXT,creado_por BIGINT UNSIGNED,FOREIGN KEY(compra_id) REFERENCES compras(id)) ENGINE=InnoDB",
"CREATE TABLE compras_detalle (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,compra_id BIGINT UNSIGNED NOT NULL,numero_linea SMALLINT UNSIGNED NOT NULL,producto_id BIGINT UNSIGNED,descripcion VARCHAR(255) NOT NULL,cantidad DECIMAL(28,8) NOT NULL,costo_unitario_neto DECIMAL(28,8) NOT NULL,es_exento TINYINT NOT NULL DEFAULT 0,tasa_iva DECIMAL(11,8) NOT NULL DEFAULT 0,monto_neto DECIMAL(28,8) NOT NULL DEFAULT 0,monto_exento DECIMAL(28,8) NOT NULL DEFAULT 0,monto_iva DECIMAL(28,8) NOT NULL DEFAULT 0,total DECIMAL(28,8) NOT NULL DEFAULT 0,UNIQUE KEY uq_hd_cd_linea(compra_id,numero_linea),FOREIGN KEY(compra_id) REFERENCES compras(id)) ENGINE=InnoDB",
"CREATE TABLE recepcion_detalle (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,recepcion_id BIGINT UNSIGNED NOT NULL,numero_linea SMALLINT UNSIGNED NOT NULL,producto_id BIGINT UNSIGNED NOT NULL,compra_detalle_id BIGINT UNSIGNED NOT NULL,detalle_orden_compra_id BIGINT UNSIGNED,cantidad_documentada DECIMAL(28,8) NOT NULL,cantidad_recibida DECIMAL(28,8) NOT NULL,cantidad_aceptada DECIMAL(28,8) NOT NULL DEFAULT 0,cantidad_rechazada DECIMAL(28,8) NOT NULL DEFAULT 0,costo_unitario_neto DECIMAL(28,8) NOT NULL,monto_neto DECIMAL(28,8) NOT NULL DEFAULT 0,monto_exento DECIMAL(28,8) NOT NULL DEFAULT 0,monto_iva DECIMAL(28,8) NOT NULL DEFAULT 0,total DECIMAL(28,8) NOT NULL DEFAULT 0,UNIQUE KEY uq_hd_rd_linea(recepcion_id,numero_linea),FOREIGN KEY(recepcion_id) REFERENCES recepciones(id),FOREIGN KEY(compra_detalle_id) REFERENCES compras_detalle(id)) ENGINE=InnoDB"
] as $sql)DB::statement($sql);
// Reproduce la invariante contable de cantidades que fallaba en producción.
DB::statement("ALTER TABLE recepcion_detalle ADD CONSTRAINT chk_hd_rd_cant CHECK (cantidad_documentada >= 0 AND cantidad_recibida >= 0 AND cantidad_aceptada >= 0 AND cantidad_rechazada >= 0 AND cantidad_aceptada + cantidad_rechazada = cantidad_recibida)");
DB::table('sucursales')->insert(['id'=>1,'activo'=>1,'codigo'=>'S01']);
DB::table('roles')->insert(['id'=>1,'codigo'=>'operador']);
DB::table('usuarios')->insert(['id'=>1,'rol_id'=>1,'activo'=>1]);
DB::table('tokens_acceso')->insert(['id'=>1,'usuario_id'=>1,'expira_en'=>now()->addHour()]);
DB::table('sesiones_sucursales')->insert(['token_acceso_id'=>1,'sucursal_id'=>1]);
DB::table('usuarios_sucursales')->insert(['usuario_id'=>1,'sucursal_id'=>1,'activo'=>1]);
DB::table('inventario_bodegas_sucursales')->insert(['bodega_id'=>1,'sucursal_id'=>1]);
DB::table('estados')->insert([['id'=>1,'codigo'=>'ACTIVA','activo'=>1],['id'=>4,'codigo'=>'PENDIENTE','activo'=>1],['id'=>2,'codigo'=>'RECEPCIONADA','activo'=>1]]);
DB::table('modulos')->insert([['id'=>2,'codigo'=>'FACTURA_COMPRA','activo'=>1],['id'=>3,'codigo'=>'RECEPCION','activo'=>1]]);
DB::table('modulos_estados')->insert([['modulo_id'=>2,'estado_id'=>4,'activo'=>1],['modulo_id'=>3,'estado_id'=>4,'activo'=>1]]);
DB::table('proveedores')->insert(['id'=>1,'activo'=>1]);DB::table('bodegas')->insert(['id'=>1,'activo'=>1]);
DB::table('tipo_documentos')->insert([['id'=>1,'codigo_sii'=>33,'familia'=>'FACTURA','activo'=>1,'documento_compra'=>1],['id'=>2,'codigo_sii'=>34,'familia'=>'FACTURA','activo'=>1,'documento_compra'=>1]]);
DB::table('ordenes_compra')->insert(['sucursal_id'=>1,'id'=>1,'proveedor_id'=>1,'estado_id'=>1]);
DB::table('condiciones_tributarias')->insert(['id'=>1,'codigo'=>'AFECTO']);
DB::table('productos')->insert(['id'=>1,'nombre'=>'Producto CI','condicion_tributaria_id'=>1]);
DB::table('detalles_ordenes_compra')->insert(['id'=>1,'orden_compra_id'=>1,'producto_id'=>1]);
DB::statement("CREATE TABLE solicitudes_regularizacion(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,numero_interno VARCHAR(60) NOT NULL UNIQUE,recepcion_consolidada_id BIGINT UNSIGNED NOT NULL,recepcion_id BIGINT UNSIGNED NOT NULL,compra_id BIGINT UNSIGNED NOT NULL,proveedor_id BIGINT UNSIGNED NOT NULL,tipo_solicitud ENUM('NC','ND','AVISO_COMERCIAL') NOT NULL,tipo_documento_solicitado_id BIGINT UNSIGNED NULL,estado ENUM('PENDIENTE','ENVIADA','RESPONDIDA','RESUELTA','NULA') DEFAULT 'PENDIENTE',motivo TEXT NOT NULL,monto_neto DECIMAL(28,8) DEFAULT 0,monto_exento DECIMAL(28,8) DEFAULT 0,monto_iva DECIMAL(28,8) DEFAULT 0,otros_impuestos DECIMAL(28,8) DEFAULT 0,monto_total DECIMAL(28,8) DEFAULT 0,token_idempotencia CHAR(36) NOT NULL UNIQUE,creado_por BIGINT UNSIGNED,enviada_en DATETIME NULL) ENGINE=InnoDB");
DB::statement("CREATE TABLE solicitudes_regularizacion_detalle(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,solicitud_id BIGINT UNSIGNED NOT NULL,recepcion_diferencia_id BIGINT UNSIGNED NOT NULL,recepcion_detalle_id BIGINT UNSIGNED NULL,producto_id BIGINT UNSIGNED NULL,numero_linea SMALLINT UNSIGNED NOT NULL,descripcion VARCHAR(255) NOT NULL,tipo_diferencia VARCHAR(40),cantidad_documentada DECIMAL(28,8),cantidad_recibida DECIMAL(28,8),cantidad_diferencia DECIMAL(28,8) NOT NULL DEFAULT 0,costo_unitario_neto DECIMAL(28,8) DEFAULT 0,monto_neto DECIMAL(28,8) DEFAULT 0,monto_exento DECIMAL(28,8) DEFAULT 0,monto_iva DECIMAL(28,8) DEFAULT 0,otros_impuestos DECIMAL(28,8) DEFAULT 0,monto_total DECIMAL(28,8) DEFAULT 0,observaciones TEXT,UNIQUE KEY uq_linea(solicitud_id,numero_linea),UNIQUE KEY uq_diff(solicitud_id,recepcion_diferencia_id)) ENGINE=InnoDB");
DB::statement("CREATE TABLE cola_notificaciones_correo(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,modulo_codigo VARCHAR(60),origen_tipo VARCHAR(60),origen_id BIGINT UNSIGNED,solicitud_id BIGINT UNSIGNED,proveedor_id BIGINT UNSIGNED,destinatario VARCHAR(190) NOT NULL,destinatario_nombre VARCHAR(190),asunto VARCHAR(255),plantilla_codigo VARCHAR(60),cuerpo_html MEDIUMTEXT,estado ENUM('PENDIENTE','PROCESANDO','ENVIADA','ERROR','NULA') DEFAULT 'PENDIENTE',intentos SMALLINT UNSIGNED DEFAULT 0,maximo_intentos SMALLINT UNSIGNED DEFAULT 5,token_idempotencia CHAR(36) UNIQUE,clave_evento VARCHAR(190) UNIQUE,token_procesamiento CHAR(36),ultimo_error TEXT,creado_por BIGINT UNSIGNED,procesando_en DATETIME,ultimo_intento_en DATETIME,enviada_en DATETIME) ENGINE=InnoDB");
DB::statement("CREATE TABLE cola_notificaciones_adjuntos(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,notificacion_id BIGINT UNSIGNED NOT NULL,origen_tipo VARCHAR(60),origen_id BIGINT UNSIGNED,nombre_archivo VARCHAR(255),tipo_mime VARCHAR(100),UNIQUE KEY uq_adj(notificacion_id,origen_tipo,origen_id)) ENGINE=InnoDB");
$c=new Controller();
$payload=['orden_compra_id'=>1,'bodega_id'=>1,'documento_id'=>1,'folio'=>'CI-1','fecha_documento'=>'2026-11-25','fecha_recepcion'=>'2026-11-25','neto'=>'240','exento'=>'0','otros'=>'0','total_informado'=>'286','detalles'=>[['detalle_orden_compra_id'=>1,'cantidad_documentada'=>'2','cantidad_recibida'=>'1.5','costo_unitario_neto'=>'120.1234','confirmar_cero'=>false]]];
function req(array $v): Request {$r=Request::create('/','POST',$v);$r->setUserResolver(fn()=>(object)['id'=>1]);$r->attributes->set('id_token_acceso',1);return $r;}
function reject(callable $call,int $status): void{try{$call();}catch(ValidationException $e){check($status===422,'Validación inesperada');return;}catch(HttpExceptionInterface $e){check($e->getStatusCode()===$status,'HTTP incorrecto');return;}throw new RuntimeException('Operación inválida aceptada');}
DB::statement("ALTER TABLE productos ADD sku VARCHAR(80)");
DB::statement("ALTER TABLE detalles_ordenes_compra ADD cantidad DECIMAL(28,8) NOT NULL DEFAULT 2, ADD costo_unitario_neto DECIMAL(28,8) NOT NULL DEFAULT 120.1234");
DB::table('productos')->where('id',1)->update(['sku'=>'SKU-CI']);
DB::table('estados')->insert(['id'=>3,'codigo'=>'NULA','activo'=>1]);
DB::table('modulos_estados')->insert([['modulo_id'=>2,'estado_id'=>3,'activo'=>1],['modulo_id'=>3,'estado_id'=>3,'activo'=>1]]);
DB::statement("CREATE TABLE libro_compras (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,compra_id BIGINT UNSIGNED UNIQUE)");
DB::statement("CREATE TABLE kardex (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,origen_tipo VARCHAR(50),origen_detalle_id BIGINT UNSIGNED)");
DB::statement("CREATE TABLE registros_auditoria (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,usuario_id BIGINT UNSIGNED,accion VARCHAR(80),tipo_entidad VARCHAR(80),entidad_id BIGINT UNSIGNED,valores_anteriores LONGTEXT,valores_nuevos LONGTEXT)");
DB::statement("ALTER TABLE proveedores ADD rut VARCHAR(25) NULL, ADD razon_social VARCHAR(190) NULL");
DB::table('proveedores')->where('id',1)->update(['rut'=>'11111111-1','razon_social'=>'Proveedor CI']);
DB::statement("ALTER TABLE compras ADD fecha_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD fecha_vencimiento DATE NULL, ADD iva_no_recuperable DECIMAL(28,8) NOT NULL DEFAULT 0, ADD iva_uso_comun DECIMAL(28,8) NOT NULL DEFAULT 0, ADD impuestos_retenidos DECIMAL(28,8) NOT NULL DEFAULT 0");
DB::statement("ALTER TABLE tipo_documentos ADD nombre VARCHAR(150) NOT NULL DEFAULT 'Factura CI'");
DB::statement("ALTER TABLE libro_compras ADD documento_id BIGINT UNSIGNED NOT NULL, ADD periodo_tributario CHAR(7) NOT NULL, ADD folio VARCHAR(60) NOT NULL, ADD rut_emisor VARCHAR(20) NOT NULL, ADD razon_social_emisor VARCHAR(190) NOT NULL, ADD fecha_documento DATE NOT NULL, ADD fecha_ingreso DATETIME NOT NULL, ADD fecha_vencimiento DATE NULL, ADD numero_interno VARCHAR(60) NULL, ADD monto_exento DECIMAL(28,8) NOT NULL DEFAULT 0, ADD monto_neto DECIMAL(28,8) NOT NULL DEFAULT 0, ADD monto_iva_recuperable DECIMAL(28,8) NOT NULL DEFAULT 0, ADD monto_iva_no_recuperable DECIMAL(28,8) NOT NULL DEFAULT 0, ADD monto_iva_uso_comun DECIMAL(28,8) NOT NULL DEFAULT 0, ADD monto_impuestos_adicionales DECIMAL(28,8) NOT NULL DEFAULT 0, ADD monto_retenciones DECIMAL(28,8) NOT NULL DEFAULT 0, ADD monto_total DECIMAL(28,8) NOT NULL DEFAULT 0, ADD signo_contable TINYINT NOT NULL DEFAULT 1, ADD estado_rcv VARCHAR(30) NOT NULL DEFAULT 'INTERNO_NO_CONCILIADO'");
DB::statement("ALTER TABLE productos ADD activo TINYINT NOT NULL DEFAULT 1, ADD costo_neto DECIMAL(15,4) NOT NULL DEFAULT 90, ADD costo_promedio_ponderado DECIMAL(28,8) NOT NULL DEFAULT 0");
DB::statement("ALTER TABLE recepciones ADD stock_aplicado_en DATETIME NULL, ADD finalizado_por BIGINT UNSIGNED NULL");
DB::statement("ALTER TABLE recepcion_detalle ADD presentacion_id BIGINT UNSIGNED NULL, ADD unidades_por_presentacion_snapshot DECIMAL(28,8) NOT NULL DEFAULT 1");
DB::statement("CREATE TABLE presentaciones_productos(id BIGINT UNSIGNED PRIMARY KEY,producto_id BIGINT UNSIGNED NOT NULL)");
DB::statement("CREATE TABLE saldos_inventario(bodega_id BIGINT UNSIGNED,presentacion_id BIGINT UNSIGNED,cantidad DECIMAL(15,3) NOT NULL DEFAULT 0,PRIMARY KEY(bodega_id,presentacion_id))");
DB::statement("CREATE TABLE stock(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,producto_id BIGINT UNSIGNED NOT NULL,bodega_id BIGINT UNSIGNED NOT NULL,disponible DECIMAL(28,8) NOT NULL DEFAULT 0,transito DECIMAL(28,8) NOT NULL DEFAULT 0,comprometido DECIMAL(28,8) NOT NULL DEFAULT 0,cantidad_fisica DECIMAL(28,8) NOT NULL DEFAULT 0,UNIQUE KEY uq_stock(producto_id,bodega_id)) ENGINE=InnoDB");
DB::statement("DROP TABLE kardex");
DB::statement("CREATE TABLE kardex(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,sucursal_id BIGINT NULL,producto_id BIGINT UNSIGNED NOT NULL,bodega_id BIGINT UNSIGNED NOT NULL,tipo_documento_id BIGINT UNSIGNED,folio_documento VARCHAR(60),tipo_movimiento TINYINT UNSIGNED NOT NULL,cantidad_anterior DECIMAL(28,8) NOT NULL,cantidad_documento DECIMAL(28,8) NOT NULL,cantidad_actual DECIMAL(28,8) NOT NULL,transito_anterior DECIMAL(28,8) NOT NULL DEFAULT 0,transito_actual DECIMAL(28,8) NOT NULL DEFAULT 0,comprometido_anterior DECIMAL(28,8) NOT NULL DEFAULT 0,comprometido_actual DECIMAL(28,8) NOT NULL DEFAULT 0,fisico_anterior DECIMAL(28,8) NOT NULL DEFAULT 0,fisico_actual DECIMAL(28,8) NOT NULL DEFAULT 0,costo_unitario_neto DECIMAL(28,8),ultimo_costo_anterior DECIMAL(15,4),ultimo_costo_actual DECIMAL(15,4),cpp_anterior DECIMAL(28,8),cpp_actual DECIMAL(28,8),origen_tipo VARCHAR(50) NOT NULL,origen_detalle_id BIGINT UNSIGNED NOT NULL,operacion_codigo VARCHAR(32) NOT NULL,token_idempotencia CHAR(36) NOT NULL UNIQUE,registrado_por BIGINT UNSIGNED,observaciones TEXT,UNIQUE KEY uq_origen(origen_tipo,origen_detalle_id,operacion_codigo)) ENGINE=InnoDB");
DB::statement("CREATE TABLE recepcion_diferencias(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,recepcion_id BIGINT UNSIGNED NOT NULL,recepcion_detalle_id BIGINT UNSIGNED,producto_id BIGINT UNSIGNED,tipo_diferencia VARCHAR(40) NOT NULL,cantidad_documentada DECIMAL(28,8),cantidad_oc_pendiente DECIMAL(28,8),cantidad_contada DECIMAL(28,8),cantidad_diferencia DECIMAL(28,8) NOT NULL DEFAULT 0,estado_resolucion VARCHAR(24) NOT NULL DEFAULT 'ABIERTA',motivo TEXT) ENGINE=InnoDB");
DB::table('estados')->insert(['id'=>6,'codigo'=>'DESCUADRADA','activo'=>1]);
DB::table('modulos_estados')->insert([['modulo_id'=>2,'estado_id'=>2,'activo'=>1],['modulo_id'=>3,'estado_id'=>2,'activo'=>1],['modulo_id'=>3,'estado_id'=>6,'activo'=>1]]);
DB::statement("ALTER TABLE ordenes_compra ADD numero VARCHAR(50), ADD estado VARCHAR(40) NOT NULL DEFAULT 'activa', ADD recibida_en DATETIME NULL");
DB::table('modulos')->insert(['id'=>1,'codigo'=>'ORDEN_COMPRA','activo'=>1]);
DB::table('modulos_estados')->insert(['modulo_id'=>1,'estado_id'=>2,'activo'=>1]);
function confirmPayload(array $rev,bool $accept=false): array{return ['version_borrador'=>$rev['version_borrador'],'huella_revision'=>$rev['huella_revision'],'token_confirmacion'=>(string)\Illuminate\Support\Str::uuid(),'aceptar_diferencias'=>$accept,'motivo'=>$accept?'Diferencia CI':null];}
function orderCI(int $id,string $qty,string $cost): void {
 DB::table('ordenes_compra')->insert(['sucursal_id'=>1,'id'=>$id,'proveedor_id'=>1,'estado_id'=>1,'numero'=>'OC-CI-'.$id]);
 DB::table('detalles_ordenes_compra')->insert(['id'=>$id,'orden_compra_id'=>$id,'producto_id'=>1,'cantidad'=>$qty,'costo_unitario_neto'=>$cost]);
}
function draftCI($c,array $payload,int $oc,string $folio,string $documented,string $received,string $cost,string $net,string $total): int{
 $p=$payload;$p['orden_compra_id']=$oc;$p['folio']=$folio;$p['neto']=$net;$p['total_informado']=$total;$p['detalles'][0]=['detalle_orden_compra_id'=>$oc,'cantidad_documentada'=>$documented,'cantidad_recibida'=>$received,'costo_unitario_neto'=>$cost,'confirmar_cero'=>false];
 return $c->crear(req($p))->getData(true)['id'];
}
foreach([
 'CREATE TABLE inventario_traspasos_detalle(id INTEGER PRIMARY KEY,traspaso_id INTEGER,producto_id INTEGER,cantidad_solicitada TEXT,cantidad_despachada TEXT,cantidad_recibida TEXT,cantidad_regularizada TEXT,costo_cpp_snapshot TEXT)',
 'CREATE TABLE inventario_traspasos(id INTEGER PRIMARY KEY,numero_interno TEXT UNIQUE,bodega_origen_id INTEGER,bodega_destino_id INTEGER,sucursal_origen_id INTEGER,sucursal_destino_id INTEGER,modalidad TEXT,estado TEXT,creado_por INTEGER,creado_en TEXT DEFAULT CURRENT_TIMESTAMP,guia_despacho_id INTEGER)',
 'CREATE TABLE inventario_traspasos_operaciones(traspaso_id INTEGER,operacion_id INTEGER,etapa TEXT,PRIMARY KEY(traspaso_id,operacion_id))',
 'CREATE TABLE inventario_grupos_motivos(id INTEGER PRIMARY KEY,codigo INTEGER,nombre TEXT,activo INTEGER)',
 'CREATE TABLE inventario_tipos_movimiento(id INTEGER PRIMARY KEY,codigo TEXT,nombre TEXT,naturaleza TEXT,activo INTEGER)',
 'CREATE TABLE inventario_motivos(id INTEGER PRIMARY KEY,codigo INTEGER,grupo_id INTEGER,tipo_movimiento_id INTEGER,nombre TEXT,regla TEXT,politica_cpp TEXT,activo INTEGER,ajuste_rapido INTEGER,requiere_referencia INTEGER,requiere_aprobacion INTEGER,requiere_respaldo INTEGER,revision_tributaria INTEGER,evaluar_aviso_sii INTEGER,indicador_traslado_sii INTEGER)',
 'CREATE TABLE inventario_operaciones(id INTEGER PRIMARY KEY,sucursal_id INTEGER,numero_interno TEXT UNIQUE,clase TEXT,motivo_id INTEGER,estado_id INTEGER,motivo_codigo_snapshot INTEGER,motivo_nombre_snapshot TEXT,regla_snapshot TEXT,politica_cpp_snapshot TEXT,justificacion TEXT,referencia_tipo_documento_id INTEGER,referencia_folio TEXT,referencia_kardex_id INTEGER,revision_tributaria TEXT,evaluacion_aviso_sii TEXT,indicador_traslado_sii INTEGER,token_creacion TEXT UNIQUE,token_confirmacion TEXT UNIQUE,version INTEGER DEFAULT 1,creado_por INTEGER,aprobado_por INTEGER,confirmado_por INTEGER,anulado_por INTEGER,motivo_anulacion TEXT,creado_en TEXT DEFAULT CURRENT_TIMESTAMP,aprobado_en TEXT,confirmado_en TEXT,anulado_en TEXT)',
 'CREATE TABLE inventario_operaciones_detalle(id INTEGER PRIMARY KEY,operacion_id INTEGER,numero_linea INTEGER,producto_id INTEGER,bodega_id INTEGER,cantidad TEXT,delta_disponible TEXT,delta_fisico TEXT,delta_transito TEXT DEFAULT "0",delta_comprometido TEXT DEFAULT "0",kardex_referencia_id INTEGER,kardex_id INTEGER,costo_unitario_snapshot TEXT)',
 'CREATE TABLE inventario_operaciones_historial(id INTEGER PRIMARY KEY,operacion_id INTEGER,estado_anterior_id INTEGER,estado_nuevo_id INTEGER,usuario_id INTEGER,accion TEXT,observaciones TEXT)',
] as $sql){
 $sql=str_replace('INTEGER PRIMARY KEY','BIGINT PRIMARY KEY AUTO_INCREMENT',$sql);
 $sql=preg_replace('/\b(creado_en) TEXT DEFAULT CURRENT_TIMESTAMP\b/','$1 DATETIME DEFAULT CURRENT_TIMESTAMP',$sql);
 $sql=preg_replace('/\b(aprobado_en|confirmado_en|anulado_en) TEXT\b/','$1 DATETIME NULL',$sql);
 $sql=preg_replace('/\b(cantidad|delta_disponible|delta_fisico|delta_transito|delta_comprometido|costo_unitario_snapshot|cantidad_solicitada|cantidad_despachada|cantidad_recibida|cantidad_regularizada|costo_cpp_snapshot) TEXT\b/','$1 DECIMAL(28,8)',$sql);
 $sql=preg_replace('/\b(numero_interno|token_creacion|token_confirmacion) TEXT UNIQUE\b/','$1 VARCHAR(100) UNIQUE',$sql);
 DB::statement($sql);
}
DB::table('modulos')->insert(['id'=>99,'codigo'=>'INVENTARIO_OPERACION','activo'=>1]);
foreach(['INV_PEND_APROBACION','INV_APROBADA','INV_CONFIRMADA','INV_ANULADA'] as $i=>$code){DB::table('estados')->insert(['id'=>90+$i,'codigo'=>$code,'activo'=>1]);DB::table('modulos_estados')->insert(['modulo_id'=>99,'estado_id'=>90+$i,'activo'=>1]);}
DB::table('inventario_grupos_motivos')->insert(['id'=>7,'codigo'=>700,'nombre'=>'Especiales','activo'=>1]);
DB::table('inventario_tipos_movimiento')->insert(['id'=>5,'codigo'=>'TRASLADO','nombre'=>'Traslado','naturaleza'=>'TRASLADO','activo'=>1]);
DB::table('inventario_motivos')->insert(['id'=>62,'codigo'=>709,'grupo_id'=>7,'tipo_movimiento_id'=>5,'nombre'=>'Traspaso interno entre bodegas','regla'=>'TRASPASO_INTERNO','politica_cpp'=>'CONSERVAR','activo'=>1,'ajuste_rapido'=>0,'requiere_referencia'=>0,'requiere_aprobacion'=>1,'requiere_respaldo'=>0,'revision_tributaria'=>0,'evaluar_aviso_sii'=>0,'indicador_traslado_sii'=>5]);
DB::table('usuarios')->insert(['id'=>2,'rol_id'=>1,'activo'=>1]);DB::table('tokens_acceso')->insert(['id'=>2,'usuario_id'=>2,'expira_en'=>now()->addHour()]);DB::table('sesiones_sucursales')->insert(['token_acceso_id'=>2,'sucursal_id'=>1]);DB::table('usuarios_sucursales')->insert(['usuario_id'=>2,'sucursal_id'=>1,'activo'=>1]);
function trReq(array $data,int $uid=1): Request {$r=Request::create('/','POST',$data);$r->setUserResolver(fn()=>(object)['id'=>$uid]);$r->attributes->set('id_token_acceso',$uid);return $r;}
$tr=$app->make(\App\Http\Controllers\TraspasosInventarioControlador::class);
orderCI(2,'2','100');$id=draftCI($c,$payload,2,'CI-DIRECTA','2','2','100','200','238');
$rev=$c->revisar(req([]),$id)->getData(true);check($rev['cierre_directo']===true,'Debe cerrar directo');
$cv=confirmPayload($rev);
DB::unprepared("CREATE TRIGGER ci_fail_oc BEFORE UPDATE ON ordenes_compra FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo CI'");
try{$c->confirmar(req($cv),$id);throw new RuntimeException('No falló OC');}catch(\Illuminate\Database\QueryException $e){}
DB::unprepared("DROP TRIGGER ci_fail_oc");
check(DB::table('stock')->count()===0&&DB::table('kardex')->count()===0&&DB::table('libro_compras')->count()===0,'Rollback directo');
$out=$c->confirmar(req($cv),$id)->getData(true);check($out['cierre_directo']===true,'Cierre directo');
$s=DB::table('stock')->first();check((float)$s->disponible===2.0&&(float)$s->transito===0.0&&(float)$s->cantidad_fisica===2.0,'Stock directo');
check((int)DB::table('ordenes_compra')->where('id',2)->value('estado_id')===2&&(float)DB::table('productos')->value('costo_promedio_ponderado')===100.0,'OC y CPP');
check(DB::table('kardex')->value('operacion_codigo')==='ENTRADA_DIRECTA'&&DB::table('kardex')->value('folio_documento')==='CI-DIRECTA','Kardex documental');
check($c->confirmar(req($cv),$id)->getData(true)['repetida']===true&&DB::table('kardex')->count()===1,'Idempotencia');
// Ejecutar un traspaso inmediato real y despachar de vuelta a bodega 1.
DB::table('bodegas')->insert(['id'=>2,'activo'=>1]);
DB::table('inventario_bodegas_sucursales')->insert(['bodega_id'=>2,'sucursal_id'=>1]);
$initial=['bodega_origen_id'=>1,'bodega_destino_id'=>2,'modalidad'=>'INMEDIATO','items'=>[['producto_id'=>1,'cantidad'=>'1']],'justificacion'=>'Traspaso inicial CI','token_creacion'=>(string)\Illuminate\Support\Str::uuid()];
$ap=['version'=>1,'motivo'=>'Aprobación CI'];
$confirm=fn()=>['version'=>2,'token_confirmacion'=>(string)\Illuminate\Support\Str::uuid()];
$tid=$tr->crear(trReq($initial))->getData(true)['id'];$tr->aprobar(trReq($ap,2),$tid);$tr->confirmar(trReq($confirm(),2),$tid);
$dispatch=array_replace($initial,['bodega_origen_id'=>2,'bodega_destino_id'=>1,'modalidad'=>'DESPACHO_RECEPCION','token_creacion'=>(string)\Illuminate\Support\Str::uuid()]);
$tid=$tr->crear(trReq($dispatch))->getData(true)['id'];$tr->aprobar(trReq($ap,2),$tid);$tr->despachar(trReq($confirm(),2),$tid);
$base=DB::transaction(fn()=>(new \App\Services\LectorBaseValorizadaInventario())->leer(1,DB::table('stock')->where('producto_id',1)->lockForUpdate()->get()));
check($base['stock_valorizado']==='2.00000000'&&$base['transito_interno']==='1.00000000','Base acreditada');
reject(fn()=>(new \App\Services\BaseValorizadaInventario())->verificarLiberacionCompra($base,1,'0.00000001'),409);
DB::table('kardex')->where('operacion_codigo','TRASPASO_DESPACHO')->where('bodega_id',1)->update(['transito_actual'=>0]);
reject(fn()=>DB::transaction(fn()=>(new \App\Services\LectorBaseValorizadaInventario())->leer(1,DB::table('stock')->where('producto_id',1)->lockForUpdate()->get())),409);
DB::table('kardex')->where('operacion_codigo','TRASPASO_DESPACHO')->where('bodega_id',1)->update(['transito_actual'=>1]);
orderCI(3,'3','200');$partial=draftCI($c,$payload,3,'CI-PARCIAL','2','2','200','400','476');$rv=$c->revisar(req([]),$partial)->getData(true);check(!$rv['cierre_directo'],'Parcial requiere consolidación');$c->confirmar(req(confirmPayload($rv)),$partial);
orderCI(4,'2','200');$bad=draftCI($c,$payload,4,'CI-DESCUADRE','2','1','200','400','476');$rv=$c->revisar(req([]),$bad)->getData(true);check(!$rv['cierre_directo'],'Descuadre requiere consolidación');$c->confirmar(req(confirmPayload($rv,true)),$bad);
orderCI(5,'2','200');$hist=draftCI($c,$payload,5,'CI-HIST','2','2','200','400','476');
// Recrear un cierre histórico real del flujo anterior, sin alterar el código de producción:
// un segundo borrador vigente impide la entrada directa; luego se anula.
$other=draftCI($c,$payload,5,'CI-OTRO','2','2','200','400','476');
$rv=$c->revisar(req([]),$hist)->getData(true);check(!$rv['cierre_directo'],'Otra recepción vigente impide cierre directo');$c->confirmar(req(confirmPayload($rv)),$hist);
$c->anular(req(['version_borrador'=>1,'motivo'=>'Anulación CI']),$other);
$read=$c->ver(req([]),$hist)->getData(true);check($read['completar_cierre_directo']===true,'Histórico recuperable');
$before=DB::table('stock')->first();$book=DB::table('libro_compras')->count();
$c->confirmar(req(['completar_cierre_directo'=>true,'version_borrador'=>$read['recepcion']['version_borrador']]),$hist);
$after=DB::table('stock')->first();check((float)$after->cantidad_fisica===(float)$before->cantidad_fisica&&(float)$after->disponible===(float)$before->disponible+2&&(float)$after->transito===(float)$before->transito-2,'Liberar sin duplicar físico');
check((float)DB::table('productos')->value('costo_promedio_ponderado')===150.0&&(int)DB::table('ordenes_compra')->where('id',5)->value('estado_id')===2&&DB::table('libro_compras')->count()===$book,'CPP, OC, libro histórico');
reject(fn()=>$c->confirmar(req(['completar_cierre_directo'=>true,'version_borrador'=>$read['recepcion']['version_borrador']]),$hist),409);
check(DB::table('recepciones')->whereNull('sucursal_id')->count()===0&&DB::table('compras')->whereNull('sucursal_id')->count()===0&&DB::table('kardex')->whereNull('sucursal_id')->count()===0,'Sucursal de nuevas operaciones');

DB::statement("CREATE TABLE recepciones_consolidadas(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,sucursal_id BIGINT NULL,numero_interno VARCHAR(60) NOT NULL UNIQUE,orden_compra_id BIGINT UNSIGNED,proveedor_id BIGINT UNSIGNED NOT NULL,bodega_id BIGINT UNSIGNED NOT NULL,recepcion_tipo TINYINT NOT NULL,estado_id BIGINT UNSIGNED NOT NULL,fecha_consolidacion DATETIME NULL,cantidad_recepciones SMALLINT UNSIGNED NOT NULL DEFAULT 0,subtotal_neto DECIMAL(28,8) NOT NULL DEFAULT 0,total_descuentos DECIMAL(28,8) NOT NULL DEFAULT 0,total_exento DECIMAL(28,8) NOT NULL DEFAULT 0,total_iva DECIMAL(28,8) NOT NULL DEFAULT 0,otros_impuestos DECIMAL(28,8) NOT NULL DEFAULT 0,total DECIMAL(28,8) NOT NULL DEFAULT 0,token_confirmacion CHAR(36) UNIQUE,observaciones TEXT,creado_por BIGINT UNSIGNED,consolidado_por BIGINT UNSIGNED)");
DB::statement("CREATE TABLE recepciones_consolidadas_detalle(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,recepcion_consolidada_id BIGINT UNSIGNED NOT NULL,numero_linea SMALLINT UNSIGNED NOT NULL,producto_id BIGINT UNSIGNED NOT NULL,detalle_orden_compra_id BIGINT UNSIGNED,cantidad_documentada DECIMAL(28,8) NOT NULL DEFAULT 0,cantidad_recibida DECIMAL(28,8) NOT NULL DEFAULT 0,cantidad_aceptada DECIMAL(28,8) NOT NULL DEFAULT 0,cantidad_rechazada DECIMAL(28,8) NOT NULL DEFAULT 0,costo_unitario_neto_ponderado DECIMAL(28,8) NOT NULL DEFAULT 0,descuento DECIMAL(28,8) NOT NULL DEFAULT 0,monto_neto DECIMAL(28,8) NOT NULL DEFAULT 0,monto_exento DECIMAL(28,8) NOT NULL DEFAULT 0,monto_iva DECIMAL(28,8) NOT NULL DEFAULT 0,total DECIMAL(28,8) NOT NULL DEFAULT 0,UNIQUE KEY uq_cd(recepcion_consolidada_id,numero_linea))");
DB::statement("ALTER TABLE bodegas ADD nombre VARCHAR(120) NOT NULL DEFAULT 'Bodega CI'");
DB::statement("ALTER TABLE tipo_documentos ADD codigo VARCHAR(60) NULL");
DB::statement("ALTER TABLE recepciones ADD total_descuentos DECIMAL(28,8) NOT NULL DEFAULT 0");
DB::statement("ALTER TABLE recepcion_detalle ADD descuento DECIMAL(28,8) NOT NULL DEFAULT 0");
DB::statement("ALTER TABLE compras_detalle ADD descuento DECIMAL(28,8) NOT NULL DEFAULT 0, ADD recargo DECIMAL(28,8) NOT NULL DEFAULT 0");
DB::statement("ALTER TABLE proveedores ADD nombre VARCHAR(190) NOT NULL DEFAULT 'Proveedor CI', ADD correo_electronico VARCHAR(190) NULL");
DB::table('modulos')->insert(['id'=>4,'codigo'=>'CONSOLIDACION_RECEPCION','activo'=>1]);
DB::table('estados')->insert(['id'=>7,'codigo'=>'CONSOLIDADA','activo'=>1]);
DB::table('modulos_estados')->insert([['modulo_id'=>4,'estado_id'=>1,'activo'=>1],['modulo_id'=>4,'estado_id'=>7,'activo'=>1],['modulo_id'=>4,'estado_id'=>3,'activo'=>1]]);
DB::table('tipo_documentos')->insert([['id'=>10,'codigo_sii'=>0,'familia'=>'INTERNO','activo'=>1,'documento_compra'=>1,'codigo'=>'CONSOLIDACION_RECEPCION'],['id'=>11,'codigo_sii'=>61,'familia'=>'NC','activo'=>1,'documento_compra'=>1,'codigo'=>'NC']]);
$con=new \App\Http\Controllers\ConsolidacionesControlador();
orderCI(7,'3','200');
$x=draftCI($c,$payload,7,'CI-C7-1','2','2','200','400','476');
$rev=$c->revisar(req([]),$x)->getData(true);$c->confirmar(req(confirmPayload($rev)),$x);
$y=draftCI($c,$payload,7,'CI-C7-2','1','0.5','200','200','238');
$rev=$c->revisar(req([]),$y)->getData(true);$c->confirmar(req(confirmPayload($rev,true)),$y);
check(count($con->candidatas(req([]),7)->getData(true)['recepciones'])===2,'Candidatas');
$cid=$con->crear(req(['orden_compra_id'=>7,'recepciones'=>[$x,$y]]))->getData(true)['id'];
$vw=$con->ver(req([]),$cid)->getData(true);
check($vw['cierre_habilitado']&&$vw['requiere_aceptacion'],'Descuadre requiere aceptación');
$cp=['huella'=>$vw['huella'],'token_confirmacion'=>(string)\Illuminate\Support\Str::uuid(),'aceptar_diferencias'=>true,'motivo'=>'Aceptación CI'];
$before=DB::table('stock')->first();$moves=DB::table('kardex')->count();
DB::table('kardex')->where('origen_tipo','RECEPCION')->whereIn('origen_detalle_id',DB::table('recepcion_detalle')->where('recepcion_id',$y)->select('id'))->update(['sucursal_id'=>null]);
reject(fn()=>$con->confirmar(req($cp),$cid),409);
DB::table('kardex')->where('origen_tipo','RECEPCION')->whereIn('origen_detalle_id',DB::table('recepcion_detalle')->where('recepcion_id',$y)->select('id'))->update(['sucursal_id'=>1]);
DB::unprepared("CREATE TRIGGER ci_fail_queue BEFORE INSERT ON cola_notificaciones_correo FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo CI'");
try{$con->confirmar(req($cp),$cid);throw new RuntimeException('No se revirtió cola');}catch(\Illuminate\Database\QueryException $e){}
DB::unprepared("DROP TRIGGER ci_fail_queue");
check(DB::table('kardex')->count()===$moves&&DB::table('solicitudes_regularizacion')->count()===0&&(string)DB::table('stock')->value('disponible')===(string)$before->disponible,'Rollback integral');
$out=$con->confirmar(req($cp),$cid)->getData(true);
check($out['estado']==='CONSOLIDADA'&&count($out['notificaciones'])===1,'Cierre con una solicitud');
$after=DB::table('stock')->first();
check((float)$after->disponible===(float)$before->disponible+2.5&&(float)$after->transito===(float)$before->transito-2.5&&(float)$after->cantidad_fisica===(float)$before->cantidad_fisica,'Liberación sin duplicación');
check((string)DB::table('productos')->value('costo_promedio_ponderado')==='169.23076923','CPP global exacto');
check(DB::table('recepciones_consolidadas')->where('id',$cid)->value('sucursal_id')==1&&DB::table('kardex')->whereNull('sucursal_id')->count()===0,'Atribución');
check($con->confirmar(req($cp),$cid)->getData(true)['repetida']===true&&DB::table('solicitudes_regularizacion')->count()===1,'Sin duplicar solicitud');
$sol=DB::table('solicitudes_regularizacion')->first();$n=DB::table('cola_notificaciones_correo')->first();
check(str_starts_with($con->solicitudPdf(req([]),(int)$sol->id)->getContent(),'%PDF-'),'PDF autorizado');
DB::table('compras')->where('id',$sol->compra_id)->update(['sucursal_id'=>null]);
reject(fn()=>$con->solicitudPdf(req([]),(int)$sol->id),404);
reject(fn()=>$con->reenviarSolicitud(req(['verificar_envio'=>true]),(int)$n->id),404);
DB::table('compras')->where('id',$sol->compra_id)->update(['sucursal_id'=>1]);
DB::table('proveedores')->where('id',1)->update(['correo_electronico'=>'proveedor@example.invalid']);
check(config('mail.default')==='log','Correo solo log CI');
check($con->reenviarSolicitud(req(['verificar_envio'=>true]),(int)$n->id)->getData(true)['estado']==='ENVIADA','Reintento autorizado');
check($con->reenviarSolicitud(req(['verificar_envio'=>true]),(int)$n->id)->getData(true)['estado']==='ENVIADA'&&(int)DB::table('cola_notificaciones_correo')->value('intentos')===1,'No duplicar correo');
orderCI(8,'1','200');$direct=draftCI($c,$payload,8,'CI-DIRECTA-TRP','1','1','200','200','238');
$rv=$c->revisar(req([]),$direct)->getData(true);check($rv['cierre_directo'],'Compra directa con traspaso');
$c->confirmar(req(confirmPayload($rv)),$direct);
check((string)DB::table('productos')->value('costo_promedio_ponderado')==='173.33333333','CPP compra directa conserva tránsito interno');
check((string)DB::table('inventario_traspasos_detalle')->where('traspaso_id',$tid)->value('costo_cpp_snapshot')==='100.00000000','Costo histórico de despacho intacto');
$before=DB::table('stock')->where('producto_id',1)->where('bodega_id',1)->first();
$rec=['items'=>[['producto_id'=>1,'cantidad'=>'0.4']],'justificacion'=>'Recepción parcial CI','token_creacion'=>(string)\Illuminate\Support\Str::uuid()];
$op=$tr->crearRecepcion(trReq($rec),$tid)->getData(true)['operacion_id'];$tr->aprobarRecepcion(trReq($ap,2),$tid,$op);
check($tr->confirmarRecepcion(trReq($confirm(),2),$tid,$op)->getData(true)['estado_traspaso']==='PARCIAL','Recepción parcial real tras compra');
$rec['items'][0]['cantidad']='0.6';$rec['token_creacion']=(string)\Illuminate\Support\Str::uuid();
$op=$tr->crearRecepcion(trReq($rec),$tid)->getData(true)['operacion_id'];$tr->aprobarRecepcion(trReq($ap,2),$tid,$op);
check($tr->confirmarRecepcion(trReq($confirm(),2),$tid,$op)->getData(true)['estado_traspaso']==='RECIBIDO','Recepción total real tras compra');
$after=DB::table('stock')->where('producto_id',1)->where('bodega_id',1)->first();
check(\Brick\Math\BigDecimal::of($after->disponible)->isEqualTo(\Brick\Math\BigDecimal::of($before->disponible)->plus(1))&&\Brick\Math\BigDecimal::of($after->transito)->isEqualTo(\Brick\Math\BigDecimal::of($before->transito)->minus(1))&&$after->cantidad_fisica===$before->cantidad_fisica,'Recepción conserva físico y tránsito ajeno');
check((string)DB::table('productos')->value('costo_promedio_ponderado')==='173.33333333','CPP no cambia en recepción interna');
$stockCheck=DB::table('stock')->orderBy('id')->get()->toJson();$kCheck=DB::table('kardex')->count();
reject(fn()=>(new \App\Http\Controllers\InventarioControlador())->trasladar(trReq([])),409);
check(DB::table('stock')->orderBy('id')->get()->toJson()===$stockCheck&&DB::table('kardex')->count()===$kCheck,'Ruta antigua no escribe');
DB::table('sesiones_sucursales')->delete();
reject(fn()=>$con->ver(req([]),$cid),409);
echo "V109C7A_INTEGRACION_OK\n";
