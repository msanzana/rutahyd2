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
check(DB::getDatabaseName()==='rutahd_v81_ci'&&config('app.env')==='testing','Prohibido ejecutar fuera de CI');
foreach([
"CREATE TABLE estados (id BIGINT UNSIGNED PRIMARY KEY,codigo VARCHAR(60),activo TINYINT)",
"CREATE TABLE modulos (id BIGINT UNSIGNED PRIMARY KEY,codigo VARCHAR(60),activo TINYINT)",
"CREATE TABLE modulos_estados (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,modulo_id BIGINT UNSIGNED,estado_id BIGINT UNSIGNED,activo TINYINT)",
"CREATE TABLE proveedores (id BIGINT UNSIGNED PRIMARY KEY,activo TINYINT)",
"CREATE TABLE bodegas (id BIGINT UNSIGNED PRIMARY KEY,activo TINYINT)",
"CREATE TABLE tipo_documentos (id BIGINT UNSIGNED PRIMARY KEY,codigo_sii SMALLINT,familia VARCHAR(24),activo TINYINT,documento_compra TINYINT)",
"CREATE TABLE ordenes_compra (id BIGINT UNSIGNED PRIMARY KEY,proveedor_id BIGINT UNSIGNED,estado_id BIGINT UNSIGNED)",
"CREATE TABLE condiciones_tributarias (id BIGINT UNSIGNED PRIMARY KEY,codigo VARCHAR(10))",
"CREATE TABLE productos (id BIGINT UNSIGNED PRIMARY KEY,nombre VARCHAR(190),condicion_tributaria_id BIGINT UNSIGNED)",
"CREATE TABLE detalles_ordenes_compra (id BIGINT UNSIGNED PRIMARY KEY,orden_compra_id BIGINT UNSIGNED,producto_id BIGINT UNSIGNED)",
"CREATE TABLE compras (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,proveedor_id BIGINT UNSIGNED NOT NULL,documento_id BIGINT UNSIGNED NOT NULL,folio VARCHAR(60) NOT NULL,orden_compra_id BIGINT UNSIGNED,fecha_documento DATE NOT NULL,estado_id BIGINT UNSIGNED NOT NULL,subtotal_neto DECIMAL(28,8) NOT NULL DEFAULT 0,total_exento DECIMAL(28,8) NOT NULL DEFAULT 0,iva_recuperable DECIMAL(28,8) NOT NULL DEFAULT 0,otros_impuestos DECIMAL(28,8) NOT NULL DEFAULT 0,total DECIMAL(28,8) NOT NULL DEFAULT 0,total_informado DECIMAL(28,8),signo_contable TINYINT NOT NULL DEFAULT 1,observaciones TEXT,creado_por BIGINT UNSIGNED,UNIQUE KEY uq_hd_compra_prov_doc_folio(proveedor_id,documento_id,folio)) ENGINE=InnoDB",
"CREATE TABLE recepciones (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,numero_interno VARCHAR(60) NOT NULL UNIQUE,recepcion_consolidada_id BIGINT UNSIGNED,orden_compra_id BIGINT UNSIGNED,compra_id BIGINT UNSIGNED NOT NULL,proveedor_id BIGINT UNSIGNED NOT NULL,bodega_id BIGINT UNSIGNED NOT NULL,recepcion_tipo TINYINT NOT NULL,estado_id BIGINT UNSIGNED NOT NULL,fecha_recepcion DATETIME,subtotal_neto DECIMAL(28,8) NOT NULL DEFAULT 0,total_exento DECIMAL(28,8) NOT NULL DEFAULT 0,total_iva DECIMAL(28,8) NOT NULL DEFAULT 0,otros_impuestos DECIMAL(28,8) NOT NULL DEFAULT 0,total DECIMAL(28,8) NOT NULL DEFAULT 0,stock_aplicado TINYINT NOT NULL DEFAULT 0,token_confirmacion CHAR(36) UNIQUE,version_borrador INT UNSIGNED NOT NULL DEFAULT 0,observaciones TEXT,creado_por BIGINT UNSIGNED,FOREIGN KEY(compra_id) REFERENCES compras(id)) ENGINE=InnoDB",
"CREATE TABLE compras_detalle (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,compra_id BIGINT UNSIGNED NOT NULL,numero_linea SMALLINT UNSIGNED NOT NULL,producto_id BIGINT UNSIGNED,descripcion VARCHAR(255) NOT NULL,cantidad DECIMAL(28,8) NOT NULL,costo_unitario_neto DECIMAL(28,8) NOT NULL,es_exento TINYINT NOT NULL DEFAULT 0,tasa_iva DECIMAL(11,8) NOT NULL DEFAULT 0,monto_neto DECIMAL(28,8) NOT NULL DEFAULT 0,monto_exento DECIMAL(28,8) NOT NULL DEFAULT 0,monto_iva DECIMAL(28,8) NOT NULL DEFAULT 0,total DECIMAL(28,8) NOT NULL DEFAULT 0,UNIQUE KEY uq_hd_cd_linea(compra_id,numero_linea),FOREIGN KEY(compra_id) REFERENCES compras(id)) ENGINE=InnoDB",
"CREATE TABLE recepcion_detalle (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,recepcion_id BIGINT UNSIGNED NOT NULL,numero_linea SMALLINT UNSIGNED NOT NULL,producto_id BIGINT UNSIGNED NOT NULL,compra_detalle_id BIGINT UNSIGNED NOT NULL,detalle_orden_compra_id BIGINT UNSIGNED,cantidad_documentada DECIMAL(28,8) NOT NULL,cantidad_recibida DECIMAL(28,8) NOT NULL,cantidad_aceptada DECIMAL(28,8) NOT NULL DEFAULT 0,cantidad_rechazada DECIMAL(28,8) NOT NULL DEFAULT 0,costo_unitario_neto DECIMAL(28,8) NOT NULL,monto_neto DECIMAL(28,8) NOT NULL DEFAULT 0,monto_exento DECIMAL(28,8) NOT NULL DEFAULT 0,monto_iva DECIMAL(28,8) NOT NULL DEFAULT 0,total DECIMAL(28,8) NOT NULL DEFAULT 0,UNIQUE KEY uq_hd_rd_linea(recepcion_id,numero_linea),FOREIGN KEY(recepcion_id) REFERENCES recepciones(id),FOREIGN KEY(compra_detalle_id) REFERENCES compras_detalle(id)) ENGINE=InnoDB"
] as $sql)DB::statement($sql);
DB::table('estados')->insert([['id'=>1,'codigo'=>'ACTIVA','activo'=>1],['id'=>4,'codigo'=>'PENDIENTE','activo'=>1],['id'=>2,'codigo'=>'RECEPCIONADA','activo'=>1]]);
DB::table('modulos')->insert([['id'=>2,'codigo'=>'FACTURA_COMPRA','activo'=>1],['id'=>3,'codigo'=>'RECEPCION','activo'=>1]]);
DB::table('modulos_estados')->insert([['modulo_id'=>2,'estado_id'=>4,'activo'=>1],['modulo_id'=>3,'estado_id'=>4,'activo'=>1]]);
DB::table('proveedores')->insert(['id'=>1,'activo'=>1]);DB::table('bodegas')->insert(['id'=>1,'activo'=>1]);
DB::table('tipo_documentos')->insert([['id'=>1,'codigo_sii'=>33,'familia'=>'FACTURA','activo'=>1,'documento_compra'=>1],['id'=>2,'codigo_sii'=>34,'familia'=>'FACTURA','activo'=>1,'documento_compra'=>1]]);
DB::table('ordenes_compra')->insert(['id'=>1,'proveedor_id'=>1,'estado_id'=>1]);
DB::table('condiciones_tributarias')->insert(['id'=>1,'codigo'=>'AFECTO']);
DB::table('productos')->insert(['id'=>1,'nombre'=>'Producto CI','condicion_tributaria_id'=>1]);
DB::table('detalles_ordenes_compra')->insert(['id'=>1,'orden_compra_id'=>1,'producto_id'=>1]);
$c=new Controller();
$payload=['orden_compra_id'=>1,'bodega_id'=>1,'documento_id'=>1,'folio'=>'CI-1','fecha_documento'=>'2026-11-25','fecha_recepcion'=>'2026-11-25','neto'=>'240','exento'=>'0','otros'=>'0','total_informado'=>'286','detalles'=>[['detalle_orden_compra_id'=>1,'cantidad_documentada'=>'2','cantidad_recibida'=>'1.5','costo_unitario_neto'=>'120.1234','confirmar_cero'=>false]]];
function req(array $v): Request {$r=Request::create('/','POST',$v);$r->setUserResolver(fn()=>(object)['id'=>1]);return $r;}
function reject(callable $call,int $status): void{try{$call();}catch(ValidationException $e){check($status===422,'Validación inesperada');return;}catch(HttpExceptionInterface $e){check($e->getStatusCode()===$status,'HTTP incorrecto');return;}throw new RuntimeException('Operación inválida aceptada');}
$r=$c->crear(req($payload));check($r->getStatusCode()===201,'Crear');$v=$r->getData(true);$id=$v['id'];check($v['version_borrador']===1&&$v['estado']==='PENDIENTE','Estado y versión');
$line=DB::table('recepcion_detalle')->first();$doc=DB::table('compras_detalle')->first();
check((float)$line->cantidad_recibida===1.5&&(float)$line->monto_neto===180.0&&(float)$doc->monto_neto===240.0,'Cálculo exacto');
check((float)DB::table('compras')->value('total')===286.0,'Total calculado por servidor');
reject(fn()=>$c->crear(req($payload)),422);check(DB::table('compras')->count()===1,'Sin duplicados');
$update=$payload;$update['version_borrador']=1;$update['detalles'][0]['cantidad_recibida']='2';$u=$c->actualizar(req($update),$id)->getData(true);check($u['version_borrador']===2,'Incremento versión');
check(DB::table('recepcion_detalle')->value('id')===$line->id&&DB::table('compras_detalle')->value('id')===$doc->id,'IDs conservados');
reject(fn()=>$c->actualizar(req($update),$id),409);
$bad=$payload;$bad['folio']='CI-BAD';$bad['detalles'][0]['costo_unitario_neto']='0';reject(fn()=>$c->crear(req($bad)),422);
$bad=$payload;$bad['folio']='CI-EX';$bad['documento_id']=2;reject(fn()=>$c->crear(req($bad)),422);
$bad=$payload;$bad['folio']='CI-ROLLBACK';DB::unprepared("CREATE TRIGGER ci_abort_rd BEFORE INSERT ON recepcion_detalle FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo deliberado CI'");
try{$c->crear(req($bad));throw new RuntimeException('No hubo fallo deliberado');}catch(\Illuminate\Database\QueryException $e){}
DB::unprepared("DROP TRIGGER ci_abort_rd");
check(DB::table('compras')->count()===1&&DB::table('recepciones')->count()===1&&DB::table('compras_detalle')->count()===1,'Rollback integral');
check((int)DB::table('recepciones')->value('stock_aplicado')===0&&(int)DB::table('ordenes_compra')->value('estado_id')===1,'Sin cierre ni inventario');
echo "V73_TRANSACCION_OK\n";

DB::statement("ALTER TABLE productos ADD sku VARCHAR(80)");
DB::statement("ALTER TABLE detalles_ordenes_compra ADD cantidad DECIMAL(28,8) NOT NULL DEFAULT 2, ADD costo_unitario_neto DECIMAL(28,8) NOT NULL DEFAULT 120.1234");
DB::table('productos')->where('id',1)->update(['sku'=>'SKU-CI']);
DB::table('estados')->insert(['id'=>3,'codigo'=>'NULA','activo'=>1]);
DB::table('modulos_estados')->insert([['modulo_id'=>2,'estado_id'=>3,'activo'=>1],['modulo_id'=>3,'estado_id'=>3,'activo'=>1]]);
DB::statement("CREATE TABLE libro_compras (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,compra_id BIGINT UNSIGNED UNIQUE)");
DB::statement("CREATE TABLE kardex (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,origen_tipo VARCHAR(50),origen_detalle_id BIGINT UNSIGNED)");
DB::statement("CREATE TABLE registros_auditoria (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,usuario_id BIGINT UNSIGNED,accion VARCHAR(80),tipo_entidad VARCHAR(80),entidad_id BIGINT UNSIGNED,valores_anteriores LONGTEXT,valores_nuevos LONGTEXT)");
$review=$c->revisar($id)->getData(true);check($review['resultado_previsto']==='RECEPCIONADA'&&count($review['diferencias'])===0&&$review['cierre_habilitado'],'Revisión cuadrada, sin cierre');
DB::table('recepcion_detalle')->where('recepcion_id',$id)->update(['cantidad_recibida'=>'1.5']);
$review=$c->revisar($id)->getData(true);check($review['resultado_previsto']==='DESCUADRADA'&&$review['diferencias'][0]['tipo']==='FALTANTE','Faltante');
check((float)$review['diferencias'][0]['cantidad_diferencia']===0.5,'Magnitud faltante');
check(count($review['saldos_oc'])===1,'Saldo OC pendiente');
DB::table('recepcion_detalle')->where('recepcion_id',$id)->update(['cantidad_recibida'=>'3']);
$review=$c->revisar($id)->getData(true);check(count(array_filter($review['diferencias'],fn($x)=>$x['tipo']==='EXCEDENTE'))===2,'Exceso contra documento y orden');
DB::table('recepcion_detalle')->where('recepcion_id',$id)->update(['cantidad_recibida'=>'2']);
DB::table('compras')->where('id',$v['compra_id'])->update(['total_informado'=>285]);
$review=$c->revisar($id)->getData(true);check($review['resultado_previsto']==='DESCUADRADA'&&$review['diferencias'][0]['tipo']==='DOCUMENTAL','Diferencia monetaria');
$cancel=['version_borrador'=>2,'motivo'=>'Anulación de prueba CI'];
reject(fn()=>$c->anular(req(['version_borrador'=>1,'motivo'=>'Versión antigua']),$id),409);
DB::table('kardex')->insert(['origen_tipo'=>'RECEPCION','origen_detalle_id'=>$line->id]);
reject(fn()=>$c->anular(req($cancel),$id),409);
DB::table('kardex')->where('origen_detalle_id',$line->id)->delete();
DB::table('libro_compras')->insert(['compra_id'=>$v['compra_id']]);
reject(fn()=>$c->anular(req($cancel),$id),409);
DB::table('libro_compras')->where('compra_id',$v['compra_id'])->delete();
$an=$c->anular(req($cancel),$id)->getData(true);check($an['estado']==='NULA','Anulación lógica');
check((int)DB::table('recepciones')->value('estado_id')===3&&(int)DB::table('compras')->value('estado_id')===3,'Estados NULA');
check(DB::table('recepcion_detalle')->count()===1&&DB::table('compras_detalle')->count()===1,'Historial conservado');
check(DB::table('registros_auditoria')->count()===1,'Auditoría persistida');
check(json_decode(DB::table('registros_auditoria')->value('valores_nuevos'),true)['motivo']===$cancel['motivo'],'Motivo conservado');
reject(fn()=>$c->anular(req($cancel),$id),409);
reject(fn()=>$c->revisar($id),404);
check((int)DB::table('ordenes_compra')->value('estado_id')===1,'OC intacta');
echo "V74A_CONTROL_OK\n";


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
DB::statement("CREATE TABLE kardex(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,producto_id BIGINT UNSIGNED NOT NULL,bodega_id BIGINT UNSIGNED NOT NULL,tipo_documento_id BIGINT UNSIGNED,folio_documento VARCHAR(60),tipo_movimiento TINYINT UNSIGNED NOT NULL,cantidad_anterior DECIMAL(28,8) NOT NULL,cantidad_documento DECIMAL(28,8) NOT NULL,cantidad_actual DECIMAL(28,8) NOT NULL,transito_anterior DECIMAL(28,8) NOT NULL DEFAULT 0,transito_actual DECIMAL(28,8) NOT NULL DEFAULT 0,comprometido_anterior DECIMAL(28,8) NOT NULL DEFAULT 0,comprometido_actual DECIMAL(28,8) NOT NULL DEFAULT 0,fisico_anterior DECIMAL(28,8) NOT NULL DEFAULT 0,fisico_actual DECIMAL(28,8) NOT NULL DEFAULT 0,costo_unitario_neto DECIMAL(28,8),ultimo_costo_anterior DECIMAL(15,4),ultimo_costo_actual DECIMAL(15,4),cpp_anterior DECIMAL(28,8),cpp_actual DECIMAL(28,8),origen_tipo VARCHAR(50) NOT NULL,origen_detalle_id BIGINT UNSIGNED NOT NULL,operacion_codigo VARCHAR(32) NOT NULL,token_idempotencia CHAR(36) NOT NULL UNIQUE,registrado_por BIGINT UNSIGNED,observaciones TEXT,UNIQUE KEY uq_origen(origen_tipo,origen_detalle_id,operacion_codigo)) ENGINE=InnoDB");
DB::statement("CREATE TABLE recepcion_diferencias(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,recepcion_id BIGINT UNSIGNED NOT NULL,recepcion_detalle_id BIGINT UNSIGNED,producto_id BIGINT UNSIGNED,tipo_diferencia VARCHAR(40) NOT NULL,cantidad_documentada DECIMAL(28,8),cantidad_oc_pendiente DECIMAL(28,8),cantidad_contada DECIMAL(28,8),cantidad_diferencia DECIMAL(28,8) NOT NULL DEFAULT 0,estado_resolucion VARCHAR(24) NOT NULL DEFAULT 'ABIERTA',motivo TEXT) ENGINE=InnoDB");
DB::table('estados')->insert(['id'=>6,'codigo'=>'DESCUADRADA','activo'=>1]);
DB::table('modulos_estados')->insert([['modulo_id'=>2,'estado_id'=>2,'activo'=>1],['modulo_id'=>3,'estado_id'=>2,'activo'=>1],['modulo_id'=>3,'estado_id'=>6,'activo'=>1]]);
DB::table('ordenes_compra')->insert(['id'=>2,'proveedor_id'=>1,'estado_id'=>1]);
DB::table('detalles_ordenes_compra')->insert(['id'=>2,'orden_compra_id'=>2,'producto_id'=>1,'cantidad'=>'3','costo_unitario_neto'=>'120.1234']);
$p=$payload;$p['orden_compra_id']=2;$p['folio']='CI-V75-1';$p['detalles'][0]['detalle_orden_compra_id']=2;$p['detalles'][0]['cantidad_recibida']='2';
$r1=$c->crear(req($p))->getData(true);$id1=$r1['id'];$rev=$c->revisar($id1)->getData(true);
check($rev['resultado_previsto']==='RECEPCIONADA'&&count($rev['saldos_oc'])===1,'Entrega parcial cuadrada');
function confirmPayload(array $rev,bool $accept=false,?string $reason=null): array{return ['version_borrador'=>$rev['version_borrador'],'huella_revision'=>$rev['huella_revision'],'token_confirmacion'=>(string)\Illuminate\Support\Str::uuid(),'aceptar_diferencias'=>$accept,'motivo'=>$reason];}
$cv=confirmPayload($rev);
$invalid=$cv;$invalid['huella_revision']=str_repeat('0',64);reject(fn()=>$c->confirmar(req($invalid),$id1),409);
DB::table('presentaciones_productos')->insert(['id'=>1,'producto_id'=>1]);DB::table('saldos_inventario')->insert(['bodega_id'=>1,'presentacion_id'=>1,'cantidad'=>2]);
reject(fn()=>$c->confirmar(req($cv),$id1),409);
DB::table('saldos_inventario')->where('presentacion_id',1)->update(['cantidad'=>0]);
DB::unprepared("CREATE TRIGGER ci_fail_libro BEFORE INSERT ON libro_compras FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo deliberado libro'");
try{$c->confirmar(req($cv),$id1);throw new RuntimeException('No se revirtió libro');}catch(\Illuminate\Database\QueryException $e){}
DB::unprepared("DROP TRIGGER ci_fail_libro");
check(DB::table('stock')->count()===0&&DB::table('kardex')->count()===0&&DB::table('libro_compras')->count()===0&&(int)DB::table('recepciones')->where('id',$id1)->value('estado_id')===4,'Rollback libro y stock');
$out=$c->confirmar(req($cv),$id1)->getData(true);check($out['estado']==='RECEPCIONADA'&&$out['movimientos']===1,'Confirmar cuadrada');
$stock=DB::table('stock')->first();check((float)$stock->disponible===0.0&&(float)$stock->transito===2.0&&(float)$stock->cantidad_fisica===2.0,'Entrada sólo tránsito');
$k=DB::table('kardex')->first();check((int)$k->tipo_movimiento===3&&$k->operacion_codigo==='ENTRADA_TRANSITO'&&(float)$k->cantidad_documento===2.0&&(float)$k->fisico_anterior===0.0&&(float)$k->fisico_actual===2.0,'Kardex entrada');
check($c->confirmar(req($cv),$id1)->getData(true)['repetida']===true&&DB::table('kardex')->count()===1,'Idempotencia');
reject(fn()=>$c->confirmar(req(confirmPayload($rev)),$id1),409);
reject(fn()=>$c->anular(req(['version_borrador'=>2,'motivo'=>'No anular cerrada']),$id1),409);
$p['folio']='CI-V75-2';$p['neto']='120';$p['total_informado']='143';$p['detalles'][0]['cantidad_documentada']='1';$p['detalles'][0]['cantidad_recibida']='0.5';
$r2=$c->crear(req($p))->getData(true);$id2=$r2['id'];$rev2=$c->revisar($id2)->getData(true);
check($rev2['resultado_previsto']==='DESCUADRADA','Faltante en segunda entrega');
reject(fn()=>$c->confirmar(req(confirmPayload($rev2)),$id2),422);
$cv2=confirmPayload($rev2,true,'Aceptar faltante CI');
DB::unprepared("CREATE TRIGGER ci_abort_k BEFORE INSERT ON kardex FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo deliberado Kardex'");
try{$c->confirmar(req($cv2),$id2);throw new RuntimeException('No se revirtió fallo');}catch(\Illuminate\Database\QueryException $e){}
DB::unprepared("DROP TRIGGER ci_abort_k");
check((float)DB::table('stock')->value('transito')===2.0&&DB::table('kardex')->count()===1&&DB::table('recepcion_diferencias')->count()===0&&(int)DB::table('recepciones')->where('id',$id2)->value('estado_id')===4,'Rollback de stock, estado y diferencias');
$out=$c->confirmar(req($cv2),$id2)->getData(true);check($out['estado']==='DESCUADRADA'&&$out['diferencias']===1,'Confirmar descuadrada');
check((float)DB::table('stock')->value('transito')===2.5&&(float)DB::table('stock')->value('cantidad_fisica')===2.5&&DB::table('kardex')->count()===2,'Acumulación en tránsito');
check(DB::table('recepcion_diferencias')->where('recepcion_id',$id2)->value('tipo_diferencia')==='FALTANTE','Diferencia persistida');
check((int)DB::table('ordenes_compra')->where('id',2)->value('estado_id')===1&&(float)DB::table('productos')->value('costo_promedio_ponderado')===0.0&&DB::table('libro_compras')->count()===2,'OC abierta, CPP intacto y libro con dos documentos');
$read=$c->ver($id2)->getData(true);check($read['recepcion']['estado']==='DESCUADRADA'&&count($read['diferencias'])===1,'Historial de cierre');
echo "V75_TRANSITO_CONFIRMACION_OK\n";

use App\Http\Controllers\ConsolidacionesControlador as Consolidaciones;
DB::statement("ALTER TABLE ordenes_compra ADD numero VARCHAR(50), ADD estado VARCHAR(40) NOT NULL DEFAULT 'activa', ADD recibida_en DATETIME NULL");
DB::statement("ALTER TABLE bodegas ADD nombre VARCHAR(120) NOT NULL DEFAULT 'Bodega CI'");
DB::statement("ALTER TABLE tipo_documentos ADD codigo VARCHAR(60) NULL");
DB::statement("ALTER TABLE recepciones ADD total_descuentos DECIMAL(28,8) NOT NULL DEFAULT 0");
DB::statement("ALTER TABLE recepcion_detalle ADD descuento DECIMAL(28,8) NOT NULL DEFAULT 0");
DB::statement("CREATE TABLE recepciones_consolidadas(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,numero_interno VARCHAR(60) NOT NULL UNIQUE,orden_compra_id BIGINT UNSIGNED,proveedor_id BIGINT UNSIGNED NOT NULL,bodega_id BIGINT UNSIGNED NOT NULL,recepcion_tipo TINYINT NOT NULL,estado_id BIGINT UNSIGNED NOT NULL,fecha_consolidacion DATETIME NULL,cantidad_recepciones SMALLINT UNSIGNED NOT NULL DEFAULT 0,subtotal_neto DECIMAL(28,8) NOT NULL DEFAULT 0,total_descuentos DECIMAL(28,8) NOT NULL DEFAULT 0,total_exento DECIMAL(28,8) NOT NULL DEFAULT 0,total_iva DECIMAL(28,8) NOT NULL DEFAULT 0,otros_impuestos DECIMAL(28,8) NOT NULL DEFAULT 0,total DECIMAL(28,8) NOT NULL DEFAULT 0,token_confirmacion CHAR(36) UNIQUE,observaciones TEXT,creado_por BIGINT UNSIGNED,consolidado_por BIGINT UNSIGNED)");
DB::statement("CREATE TABLE recepciones_consolidadas_detalle(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,recepcion_consolidada_id BIGINT UNSIGNED NOT NULL,numero_linea SMALLINT UNSIGNED NOT NULL,producto_id BIGINT UNSIGNED NOT NULL,detalle_orden_compra_id BIGINT UNSIGNED,cantidad_documentada DECIMAL(28,8) NOT NULL DEFAULT 0,cantidad_recibida DECIMAL(28,8) NOT NULL DEFAULT 0,cantidad_aceptada DECIMAL(28,8) NOT NULL DEFAULT 0,cantidad_rechazada DECIMAL(28,8) NOT NULL DEFAULT 0,costo_unitario_neto_ponderado DECIMAL(28,8) NOT NULL DEFAULT 0,descuento DECIMAL(28,8) NOT NULL DEFAULT 0,monto_neto DECIMAL(28,8) NOT NULL DEFAULT 0,monto_exento DECIMAL(28,8) NOT NULL DEFAULT 0,monto_iva DECIMAL(28,8) NOT NULL DEFAULT 0,total DECIMAL(28,8) NOT NULL DEFAULT 0,UNIQUE KEY uq_cd(recepcion_consolidada_id,numero_linea))");
DB::table('modulos')->insert([['id'=>1,'codigo'=>'ORDEN_COMPRA','activo'=>1],['id'=>4,'codigo'=>'CONSOLIDACION_RECEPCION','activo'=>1]]);
DB::table('estados')->insert(['id'=>7,'codigo'=>'CONSOLIDADA','activo'=>1]);
DB::table('modulos_estados')->insert([['modulo_id'=>1,'estado_id'=>2,'activo'=>1],['modulo_id'=>4,'estado_id'=>1,'activo'=>1],['modulo_id'=>4,'estado_id'=>7,'activo'=>1],['modulo_id'=>4,'estado_id'=>3,'activo'=>1]]);
DB::table('tipo_documentos')->insert(['id'=>10,'codigo_sii'=>0,'familia'=>'INTERNO','activo'=>1,'documento_compra'=>1,'codigo'=>'CONSOLIDACION_RECEPCION']);

DB::statement("ALTER TABLE proveedores ADD nombre VARCHAR(190) NOT NULL DEFAULT 'Proveedor CI', ADD correo_electronico VARCHAR(190) NULL");
DB::statement("ALTER TABLE compras_detalle ADD descuento DECIMAL(28,8) NOT NULL DEFAULT 0, ADD recargo DECIMAL(28,8) NOT NULL DEFAULT 0");
DB::statement("CREATE TABLE solicitudes_regularizacion(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,numero_interno VARCHAR(60) NOT NULL UNIQUE,recepcion_consolidada_id BIGINT UNSIGNED NOT NULL,recepcion_id BIGINT UNSIGNED NOT NULL,compra_id BIGINT UNSIGNED NOT NULL,proveedor_id BIGINT UNSIGNED NOT NULL,tipo_solicitud ENUM('NC','ND','AVISO_COMERCIAL') NOT NULL,tipo_documento_solicitado_id BIGINT UNSIGNED NULL,estado ENUM('PENDIENTE','ENVIADA','RESPONDIDA','RESUELTA','NULA') DEFAULT 'PENDIENTE',motivo TEXT NOT NULL,monto_neto DECIMAL(28,8) DEFAULT 0,monto_exento DECIMAL(28,8) DEFAULT 0,monto_iva DECIMAL(28,8) DEFAULT 0,otros_impuestos DECIMAL(28,8) DEFAULT 0,monto_total DECIMAL(28,8) DEFAULT 0,token_idempotencia CHAR(36) NOT NULL UNIQUE,creado_por BIGINT UNSIGNED,enviada_en DATETIME NULL) ENGINE=InnoDB");
DB::statement("CREATE TABLE solicitudes_regularizacion_detalle(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,solicitud_id BIGINT UNSIGNED NOT NULL,recepcion_diferencia_id BIGINT UNSIGNED NOT NULL,recepcion_detalle_id BIGINT UNSIGNED NULL,producto_id BIGINT UNSIGNED NULL,numero_linea SMALLINT UNSIGNED NOT NULL,descripcion VARCHAR(255) NOT NULL,tipo_diferencia VARCHAR(40),cantidad_documentada DECIMAL(28,8),cantidad_recibida DECIMAL(28,8),cantidad_diferencia DECIMAL(28,8) NOT NULL DEFAULT 0,costo_unitario_neto DECIMAL(28,8) DEFAULT 0,monto_neto DECIMAL(28,8) DEFAULT 0,monto_exento DECIMAL(28,8) DEFAULT 0,monto_iva DECIMAL(28,8) DEFAULT 0,otros_impuestos DECIMAL(28,8) DEFAULT 0,monto_total DECIMAL(28,8) DEFAULT 0,observaciones TEXT,UNIQUE KEY uq_linea(solicitud_id,numero_linea),UNIQUE KEY uq_diff(solicitud_id,recepcion_diferencia_id)) ENGINE=InnoDB");
DB::statement("CREATE TABLE cola_notificaciones_correo(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,modulo_codigo VARCHAR(60),origen_tipo VARCHAR(60),origen_id BIGINT UNSIGNED,solicitud_id BIGINT UNSIGNED,proveedor_id BIGINT UNSIGNED,destinatario VARCHAR(190) NOT NULL,destinatario_nombre VARCHAR(190),asunto VARCHAR(255),plantilla_codigo VARCHAR(60),cuerpo_html MEDIUMTEXT,estado ENUM('PENDIENTE','PROCESANDO','ENVIADA','ERROR','NULA') DEFAULT 'PENDIENTE',intentos SMALLINT UNSIGNED DEFAULT 0,maximo_intentos SMALLINT UNSIGNED DEFAULT 5,token_idempotencia CHAR(36) UNIQUE,clave_evento VARCHAR(190) UNIQUE,token_procesamiento CHAR(36),ultimo_error TEXT,creado_por BIGINT UNSIGNED,procesando_en DATETIME,ultimo_intento_en DATETIME,enviada_en DATETIME) ENGINE=InnoDB");
DB::statement("CREATE TABLE cola_notificaciones_adjuntos(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,notificacion_id BIGINT UNSIGNED NOT NULL,origen_tipo VARCHAR(60),origen_id BIGINT UNSIGNED,nombre_archivo VARCHAR(255),tipo_mime VARCHAR(100),UNIQUE KEY uq_adj(notificacion_id,origen_tipo,origen_id)) ENGINE=InnoDB");
DB::table('tipo_documentos')->insert([['id'=>8,'codigo_sii'=>61,'familia'=>'NOTA_CREDITO','activo'=>1,'documento_compra'=>1,'codigo'=>'NC'],['id'=>9,'codigo_sii'=>56,'familia'=>'NOTA_DEBITO','activo'=>1,'documento_compra'=>1,'codigo'=>'ND']]);

$con=new Consolidaciones();
$eligible=$con->candidatas(2)->getData(true);check(count($eligible['recepciones'])===2,'Candidatas finalizadas');
$draft=$con->crear(req(['orden_compra_id'=>2,'recepciones'=>[$id1,$id2],'observaciones'=>'Descuadre CI']))->getData(true);$cid=$draft['id'];
$view=$con->ver($cid)->getData(true);check($view['cierre_habilitado']&&$view['requiere_aceptacion']&&count($view['diferencias'])===1,'Descuadre exige aceptación');
reject(fn()=>$con->confirmar(req(['huella'=>$view['huella'],'token_confirmacion'=>(string)\Illuminate\Support\Str::uuid()]),$cid),422);
reject(fn()=>$con->crear(req(['orden_compra_id'=>2,'recepciones'=>[$id1]])),409);
$an=$con->anular(req(['huella'=>$view['huella'],'motivo'=>'Liberación CI']),$cid)->getData(true);
check($an['estado']==='NULA'&&DB::table('recepciones')->whereIn('id',[$id1,$id2])->whereNull('recepcion_consolidada_id')->count()===2,'Anulación sin borrar cabecera');
check((float)DB::table('stock')->value('transito')===2.5,'Anulación sin mover stock');
// Otra orden completa; 3 unidades a 120.1234 en dos entregas.
DB::table('ordenes_compra')->insert(['id'=>3,'proveedor_id'=>1,'estado_id'=>1,'numero'=>'OC-CI-3']);
DB::table('detalles_ordenes_compra')->insert(['id'=>3,'orden_compra_id'=>3,'producto_id'=>1,'cantidad'=>'3','costo_unitario_neto'=>'120.1234']);
$p=$payload;$p['orden_compra_id']=3;$p['folio']='CI-C3-1';$p['detalles'][0]['detalle_orden_compra_id']=3;$p['detalles'][0]['cantidad_recibida']='2';
$x=$c->crear(req($p))->getData(true)['id'];$rv=$c->revisar($x)->getData(true);$c->confirmar(req(confirmPayload($rv)),$x);
$p['folio']='CI-C3-2';$p['neto']='120';$p['total_informado']='143';$p['detalles'][0]['cantidad_documentada']='1';$p['detalles'][0]['cantidad_recibida']='1';
$y=$c->crear(req($p))->getData(true)['id'];$rv=$c->revisar($y)->getData(true);$c->confirmar(req(confirmPayload($rv)),$y);
$cid=$con->crear(req(['orden_compra_id'=>3,'recepciones'=>[$x]]))->getData(true)['id'];
$vw=$con->ver($cid)->getData(true);check(!$vw['cierre_habilitado'],'Selección incompleta no cierra');
$update=['orden_compra_id'=>3,'recepciones'=>[$x,$y],'observaciones'=>'Completa','huella'=>$vw['huella']];
$con->actualizar(req($update),$cid);reject(fn()=>$con->actualizar(req($update),$cid),409);
$vw=$con->ver($cid)->getData(true);check($vw['cierre_habilitado']&&(float)$vw['productos'][0]['cantidad_aceptada']===3.0,'Consolidación completa');
check((float)DB::table('stock')->value('transito')===5.5&&(float)DB::table('stock')->value('disponible')===0.0,'Borrador no mueve');
$cp=['huella'=>$vw['huella'],'token_confirmacion'=>(string)\Illuminate\Support\Str::uuid()];
DB::unprepared("CREATE TRIGGER ci_fail_con BEFORE UPDATE ON ordenes_compra FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo CI'");
try{$con->confirmar(req($cp),$cid);throw new RuntimeException('No se revirtió');}catch(\Illuminate\Database\QueryException $e){}
DB::unprepared("DROP TRIGGER ci_fail_con");
check((float)DB::table('stock')->value('transito')===5.5&&(float)DB::table('productos')->value('costo_promedio_ponderado')===0.0&&DB::table('kardex')->where('origen_tipo','CONSOLIDACION_RECEPCION')->count()===0,'Rollback de stock CPP y kardex');
$close=$con->confirmar(req($cp),$cid)->getData(true);check($close['estado']==='CONSOLIDADA','Cierre');
$s=DB::table('stock')->first();check((float)$s->disponible===3.0&&(float)$s->transito===2.5&&(float)$s->cantidad_fisica===5.5,'Físico no se duplica; otro tránsito intacto');
check((string)DB::table('productos')->value('costo_promedio_ponderado')==='120.12340000','CPP inicial cero');
check((int)DB::table('ordenes_compra')->where('id',3)->value('estado_id')===2,'OC cerrada');
check($con->confirmar(req($cp),$cid)->getData(true)['repetida']===true&&DB::table('kardex')->where('origen_tipo','CONSOLIDACION_RECEPCION')->count()===1,'Idempotencia de consolidación');
// Nueva orden con costo 200. CPP global de tres unidades previas y dos nuevas.
DB::table('ordenes_compra')->insert(['id'=>4,'proveedor_id'=>1,'estado_id'=>1,'numero'=>'OC-CI-4']);
DB::table('detalles_ordenes_compra')->insert(['id'=>4,'orden_compra_id'=>4,'producto_id'=>1,'cantidad'=>'2','costo_unitario_neto'=>'200']);
$p=$payload;$p['orden_compra_id']=4;$p['folio']='CI-C4-1';$p['neto']='400';$p['total_informado']='476';$p['detalles'][0]['detalle_orden_compra_id']=4;$p['detalles'][0]['cantidad_recibida']='2';$p['detalles'][0]['costo_unitario_neto']='200';
$z=$c->crear(req($p))->getData(true)['id'];$rv=$c->revisar($z)->getData(true);$c->confirmar(req(confirmPayload($rv)),$z);
$cid2=$con->crear(req(['orden_compra_id'=>4,'recepciones'=>[$z]]))->getData(true)['id'];$vw=$con->ver($cid2)->getData(true);
$con->confirmar(req(['huella'=>$vw['huella'],'token_confirmacion'=>(string)\Illuminate\Support\Str::uuid()]),$cid2);
check((string)DB::table('productos')->value('costo_promedio_ponderado')==='152.07404000','CPP ponderado exacto: (3*120.1234+2*200)/5');
check((float)DB::table('stock')->value('cantidad_fisica')===7.5&&(float)DB::table('stock')->value('disponible')===5.0,'Físico final correcto');
echo "V77_CONSOLIDACION_CPP_OK\n";

use App\Services\RegularizacionRecepcion;
$svc=new RegularizacionRecepcion();
$cid=$con->crear(req(['orden_compra_id'=>2,'recepciones'=>[$id1,$id2],'observaciones'=>'Descuadre CI']))->getData(true)['id'];
$view=$con->ver($cid)->getData(true);
$cp=['huella'=>$view['huella'],'token_confirmacion'=>(string)\Illuminate\Support\Str::uuid(),'aceptar_diferencias'=>true,'motivo'=>'Aceptar faltante CI'];
DB::unprepared("CREATE TRIGGER ci_fail_queue BEFORE INSERT ON cola_notificaciones_correo FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fallo CI'");
try{$con->confirmar(req($cp),$cid);throw new RuntimeException('No se revirtió la cola');}catch(\Illuminate\Database\QueryException $e){}
DB::unprepared("DROP TRIGGER ci_fail_queue");
check(DB::table('solicitudes_regularizacion')->count()===0&&(float)DB::table('stock')->value('transito')===2.5&&(int)DB::table('ordenes_compra')->where('id',2)->value('estado_id')===1,'Rollback de solicitud y stock');
$closed=$con->confirmar(req($cp),$cid)->getData(true);
check($closed['estado']==='CONSOLIDADA'&&DB::table('solicitudes_regularizacion')->count()===1,'Cierre descuadrado');
$s=DB::table('solicitudes_regularizacion')->first();check($s->tipo_solicitud==='NC'&&(float)$s->monto_total===71.0&&(int)$s->recepcion_id===$id2,'NC faltante 0.5, neto 60 IVA 11');
$n=DB::table('cola_notificaciones_correo')->first();check($n->estado==='ERROR'&&DB::table('cola_notificaciones_adjuntos')->count()===1,'Sin correo válido: queda error sin revertir cierre');
check($con->confirmar(req($cp),$cid)->getData(true)['repetida']===true&&DB::table('solicitudes_regularizacion')->count()===1,'Sin solicitudes duplicadas');
$pdf=$svc->pdf((int)$s->id);check(str_starts_with($pdf,'%PDF-1.4')&&str_contains($pdf,'TOTAL PROPUESTO CLP: 71.00000000'),'PDF de solicitud');
DB::table('proveedores')->where('id',1)->update(['correo_electronico'=>'proveedor@example.invalid']);
check($svc->enviar((int)$n->id,true)==='ENVIADA','Reintento con correo válido en mailer log');
check($svc->enviar((int)$n->id,true)==='ENVIADA'&&(int)DB::table('cola_notificaciones_correo')->where('id',$n->id)->value('intentos')===1,'No repetir correo enviado');
// Dos documentos descuadrados, NC y revisión ND independientes.
DB::table('ordenes_compra')->insert(['id'=>5,'proveedor_id'=>1,'estado_id'=>1,'numero'=>'OC-CI-5']);
DB::table('detalles_ordenes_compra')->insert(['id'=>5,'orden_compra_id'=>5,'producto_id'=>1,'cantidad'=>'3','costo_unitario_neto'=>'100']);
$p=$payload;$p['orden_compra_id']=5;$p['folio']='CI-5-1';$p['neto']='200';$p['total_informado']='238';$p['detalles'][0]['detalle_orden_compra_id']=5;$p['detalles'][0]['cantidad_recibida']='1';$p['detalles'][0]['costo_unitario_neto']='100';
$a=$c->crear(req($p))->getData(true)['id'];$r=$c->revisar($a)->getData(true);$c->confirmar(req(confirmPayload($r,true,'Faltante CI')),$a);
$p['folio']='CI-5-2';$p['neto']='100';$p['total_informado']='119';$p['detalles'][0]['cantidad_documentada']='1';$p['detalles'][0]['cantidad_recibida']='2';
$b=$c->crear(req($p))->getData(true)['id'];$r=$c->revisar($b)->getData(true);$c->confirmar(req(confirmPayload($r,true,'Exceso CI')),$b);
$cid=$con->crear(req(['orden_compra_id'=>5,'recepciones'=>[$a,$b]]))->getData(true)['id'];$view=$con->ver($cid)->getData(true);
$close=$con->confirmar(req(['huella'=>$view['huella'],'token_confirmacion'=>(string)\Illuminate\Support\Str::uuid(),'aceptar_diferencias'=>true,'motivo'=>'Regularizar dos documentos CI']),$cid)->getData(true);
$sol=DB::table('solicitudes_regularizacion')->where('recepcion_consolidada_id',$cid)->orderBy('id')->get();
check($sol->count()===2&&$sol[0]->tipo_solicitud==='NC'&&$sol[1]->tipo_solicitud==='ND'&&(int)$sol[0]->recepcion_id===$a&&(int)$sol[1]->recepcion_id===$b,'Solicitudes separadas por documento');
check((float)$sol[0]->monto_total===119.0&&(float)$sol[1]->monto_total===119.0,'Importes NC y ND');
check(count($close['correos'])===2&&$close['correos'][0]['estado']==='ENVIADA'&&$close['correos'][1]['estado']==='ENVIADA','Notificaciones posteriores a commit, mailer log');
check(DB::table('cola_notificaciones_adjuntos')->count()===3,'Adjuntos registrados');
echo "V79_REGULARIZACION_CORREO_OK\n";

use App\Services\PdfRecepcionCompra;
use App\Services\LibroComprasInterno;
check(DB::table('libro_compras')->count()===7,'Libro: siete compras reales, sin solicitudes ni consolidaciones');
$entry=DB::table('libro_compras')->where('folio','CI-V75-1')->first();
check($entry->periodo_tributario==='2026-11'&&$entry->rut_emisor==='11111111-1'&&$entry->estado_rcv==='INTERNO_NO_CONCILIADO'&&(float)$entry->monto_total===286.0,'Snapshot contable interno');
check(DB::table('libro_compras')->where('signo_contable','<>',1)->count()===0,'No se contabilizan solicitudes de NC');
$pdf=new PdfRecepcionCompra();
$recpdf=$pdf->generar('recepcion',$a);$conpdf=$pdf->generar('consolidacion',$cid);
check(str_starts_with($recpdf,'%PDF-1.4')&&str_contains($recpdf,'CI-5-1'),'PDF de recepción real');
check(str_starts_with($conpdf,'%PDF-1.4')&&str_contains($conpdf,'CI-5-1')&&str_contains($conpdf,'CI-5-2')&&str_contains($conpdf,'SOL-'),'PDF de consolidación y solicitudes');
reject(fn()=>$pdf->generar('recepcion',$id),404);
file_put_contents('/tmp/v81-recepcion.pdf',$recpdf);file_put_contents('/tmp/v81-consolidacion.pdf',$conpdf);
echo "V81_LIBRO_PDF_OK\n";
