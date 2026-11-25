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
check(DB::getDatabaseName()==='rutahd_v74a_ci'&&config('app.env')==='testing','Prohibido ejecutar fuera de CI');
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
$review=$c->revisar($id)->getData(true);check($review['resultado_previsto']==='RECEPCIONADA'&&count($review['diferencias'])===0&&!$review['cierre_habilitado'],'Revisión cuadrada, sin cierre');
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
