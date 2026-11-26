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
check(DB::getDatabaseName()==='rutahd_v108b2_ci'&&config('app.env')==='testing','Prohibido ejecutar fuera de CI');
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
DB::statement("CREATE TABLE solicitudes_regularizacion(id BIGINT PRIMARY KEY,recepcion_id BIGINT,numero_interno VARCHAR(60),tipo_solicitud VARCHAR(30),estado VARCHAR(30),monto_total DECIMAL(28,8))");
DB::statement("CREATE TABLE cola_notificaciones_correo(id BIGINT PRIMARY KEY,solicitud_id BIGINT,origen_tipo VARCHAR(40),estado VARCHAR(30),ultimo_error TEXT,intentos INT,maximo_intentos INT,enviada_en DATETIME)");
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
DB::table('bodegas')->insert(['id'=>2,'activo'=>1]);
orderCI(6,'2','100');
$p=$payload;$p['orden_compra_id']=6;$p['detalles'][0]['detalle_orden_compra_id']=6;$p['bodega_id']=2;$p['folio']='CI-SIN-BODEGA';
reject(fn()=>$c->crear(req($p)),404);
DB::table('ordenes_compra')->where('id',6)->update(['sucursal_id'=>null]);
$p['bodega_id']=1;
reject(fn()=>$c->crear(req($p)),404);
DB::table('recepciones')->where('id',$hist)->update(['sucursal_id'=>null]);
reject(fn()=>$c->ver(req([]),$hist),404);
DB::table('recepciones')->where('id',$hist)->update(['sucursal_id'=>1]);
DB::table('sesiones_sucursales')->delete();
reject(fn()=>$c->ver(req([]),$hist),409);
echo "V108B2A_RECEPCIONES_OK\n";
