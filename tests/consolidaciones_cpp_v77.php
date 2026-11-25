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
check(DB::getDatabaseName()==='rutahd_v77_ci'&&config('app.env')==='testing','Prohibido ejecutar fuera de CI');
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
check((int)DB::table('ordenes_compra')->where('id',2)->value('estado_id')===1&&(float)DB::table('productos')->value('costo_promedio_ponderado')===0.0&&DB::table('libro_compras')->count()===0,'OC abierta, CPP y libro intactos');
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
$con=new Consolidaciones();
$eligible=$con->candidatas(2)->getData(true);check(count($eligible['recepciones'])===2,'Candidatas finalizadas');
$draft=$con->crear(req(['orden_compra_id'=>2,'recepciones'=>[$id1,$id2],'observaciones'=>'Descuadre CI']))->getData(true);$cid=$draft['id'];
$view=$con->ver($cid)->getData(true);check(!$view['cierre_habilitado']&&count($view['diferencias'])===1,'Descuadre bloqueado hasta regularización');
reject(fn()=>$con->confirmar(req(['huella'=>$view['huella'],'token_confirmacion'=>(string)\Illuminate\Support\Str::uuid()]),$cid),409);
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
