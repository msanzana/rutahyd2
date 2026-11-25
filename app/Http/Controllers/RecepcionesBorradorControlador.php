<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/** V73: borradores de recepción con OC. No cierra, mueve stock ni emite correos. */
final class RecepcionesBorradorControlador
{
    private function estado(string $modulo): int
    {
        $id=DB::table('modulos_estados as me')->join('modulos as m','m.id','=','me.modulo_id')
          ->join('estados as e','e.id','=','me.estado_id')->where('m.codigo',$modulo)
          ->where('m.activo',1)->where('me.activo',1)->where('e.activo',1)->where('e.codigo','PENDIENTE')->value('e.id');
        abort_unless($id,409,'Estado pendiente no configurado.');
        return (int)$id;
    }
    private function error(string $key,string $message): never
    {
        throw ValidationException::withMessages([$key=>[$message]]);
    }
    private function escala(string $v,int $places): int
    {
        $p=explode('.',$v,2);
        return (int)($p[0].str_pad($p[1]??'',$places,'0'));
    }
    private function importe(string $qty,string $cost): int
    {
        // Cantidad hasta 999999.999 y costo hasta 9999999.9999.
        // Producto exacto, descompuesto para evitar desbordamiento de int64.
        $q=$this->escala($qty,3); $c=$this->escala($cost,4);
        $a=$q*intdiv($c,10000);
        return intdiv($a,1000)+intdiv(($a%1000)*10000+$q*($c%10000)+5000000,10000000);
    }
    private function iva(int $neto): int { return intdiv($neto*19+50,100); }
    private function datos(Request $request): array
    {
        return $request->validate([
          'orden_compra_id'=>'required|integer|min:1',
          'bodega_id'=>'required|integer|min:1',
          'documento_id'=>'required|integer|min:1',
          'folio'=>'required|string|max:60',
          'fecha_documento'=>'required|date_format:Y-m-d',
          'fecha_recepcion'=>'required|date_format:Y-m-d',
          'neto'=>'required|integer|min:0|max:999999999999999',
          'exento'=>'required|integer|min:0|max:999999999999999',
          'otros'=>'required|integer|min:0|max:999999999999999',
          'total_informado'=>'required|integer|min:0|max:999999999999999',
          'observaciones'=>'nullable|string|max:5000',
          'version_borrador'=>'sometimes|integer|min:0|max:4294967294',
          'detalles'=>'required|array|min:1|max:200',
          'detalles.*.detalle_orden_compra_id'=>'required|integer|min:1|distinct',
          'detalles.*.cantidad_documentada'=>['required','regex:/^(?:0|[1-9]\d{0,5})(?:\.\d{1,3})?$/D'],
          'detalles.*.cantidad_recibida'=>['required','regex:/^(?:0|[1-9]\d{0,5})(?:\.\d{1,3})?$/D'],
          'detalles.*.costo_unitario_neto'=>['required','regex:/^(?:0|[1-9]\d{0,6})(?:\.\d{1,4})?$/D'],
          'detalles.*.confirmar_cero'=>'required|boolean',
        ]);
    }
    public function ver(int $id)
    {
        $r=DB::table('recepciones as r')->join('estados as e','e.id','=','r.estado_id')
          ->join('compras as c','c.id','=','r.compra_id')->where('r.id',$id)->where('e.codigo','<>','NULA')
          ->select('r.*','e.codigo as estado','c.documento_id','c.folio','c.fecha_documento','c.total_informado')->first();
        abort_unless($r,404,'Recepción no encontrada.');
        return response()->json(['recepcion'=>$r,'detalles'=>DB::table('recepcion_detalle')->where('recepcion_id',$id)->orderBy('numero_linea')->get()]);
    }
    public function crear(Request $request) { return $this->guardar($request,null); }
    public function actualizar(Request $request,int $id) { return $this->guardar($request,$id); }
    private function guardar(Request $request,?int $id)
    {
        $v=$this->datos($request);
        $user=(int)($request->user()?->id??0);
        abort_unless($user>0,401);
        try {
            $out=DB::transaction(function()use($v,$id,$user){
                $oc=DB::table('ordenes_compra')->where('id',$v['orden_compra_id'])->lockForUpdate()->first();
                abort_unless($oc,404,'Orden de compra inexistente.');
                abort_unless(DB::table('estados')->where('id',$oc->estado_id)->where('codigo','ACTIVA')->exists(),409,'La orden ya no está activa.');
                $prov=DB::table('proveedores')->where('id',$oc->proveedor_id)->lockForUpdate()->first();
                abort_unless($prov && $prov->activo,409,'Proveedor no activo.');
                $b=DB::table('bodegas')->where('id',$v['bodega_id'])->where('activo',1)->lockForUpdate()->first();
                abort_unless($b,422,'Seleccione una bodega activa.');
                $doc=DB::table('tipo_documentos')->where('id',$v['documento_id'])->where('activo',1)->where('documento_compra',1)->lockForUpdate()->first();
                // No registrar NC/ND como entradas físicas; su flujo corresponde a regularización.
                abort_unless($doc&&in_array($doc->familia,['FACTURA','FACTURA_COMPRA','BOLETA','GUIA'],true),422,'Tipo de documento no habilitado para recepción.');
                $recEstado=$this->estado('RECEPCION'); $compEstado=$this->estado('FACTURA_COMPRA');
                $r=null;$c=null;$old=collect();
                if($id!==null){
                    $r=DB::table('recepciones')->where('id',$id)->lockForUpdate()->first();
                    abort_unless($r,404,'Recepción no encontrada.');
                    abort_unless((int)$r->orden_compra_id===(int)$oc->id&&(int)$r->proveedor_id===(int)$prov->id,409,'No se puede cambiar la orden de un borrador.');
                    abort_unless((int)$r->estado_id===$recEstado&&!$r->stock_aplicado&&!$r->recepcion_consolidada_id&&!$r->token_confirmacion,409,'La recepción ya no es editable.');
                    abort_unless(isset($v['version_borrador'])&&(int)$v['version_borrador']===(int)$r->version_borrador,409,'Otro usuario modificó el borrador. Vuelva a abrirlo.');
                    $c=DB::table('compras')->where('id',$r->compra_id)->lockForUpdate()->first();
                    abort_unless($c&&(int)$c->estado_id===$compEstado&&(int)$c->proveedor_id===(int)$prov->id&&(int)$c->orden_compra_id===(int)$oc->id,409,'Documento de compra no editable.');
                    $old=DB::table('recepcion_detalle')->where('recepcion_id',$id)->lockForUpdate()->get()->keyBy('detalle_orden_compra_id');
                    abort_unless($old->count()===DB::table('compras_detalle')->where('compra_id',$c->id)->count(),409,'El documento tiene otras líneas; no puede editarse aquí.');
                }
                $folio=trim($v['folio']);if($folio==='')$this->error('folio','Ingrese el folio.');
                $dup=DB::table('compras')->where('proveedor_id',$prov->id)->where('documento_id',$doc->id)->where('folio',$folio);
                if($c)$dup->where('id','<>',$c->id);
                if($dup->exists())$this->error('folio','Este proveedor ya tiene registrado el documento y folio.');
                $isExempt=in_array((int)$doc->codigo_sii,[32,34,38,41],true);
                $net=(int)$v['neto'];$ex=(int)$v['exento'];$other=(int)$v['otros'];
                if($isExempt&&$net!==0)$this->error('neto','Un documento exento no admite monto neto afecto.');
                $iva=$isExempt?0:$this->iva($net);$total=$net+$ex+$iva+$other;
                $ocLines=DB::table('detalles_ordenes_compra as d')->join('productos as p','p.id','=','d.producto_id')
                  ->leftJoin('condiciones_tributarias as ct','ct.id','=','p.condicion_tributaria_id')
                  ->where('d.orden_compra_id',$oc->id)->select('d.id','d.producto_id','p.nombre','ct.codigo as tributo')->lockForUpdate()->get()->keyBy('id');
                $lines=[];$seen=[];
                foreach($v['detalles'] as $i=>$line){
                    $lineId=(int)$line['detalle_orden_compra_id'];$o=$ocLines->get($lineId);
                    if(!$o)$this->error('detalles','Hay productos que no pertenecen a la orden seleccionada.');
                    $qd=(string)$line['cantidad_documentada'];$qr=(string)$line['cantidad_recibida'];$cost=(string)$line['costo_unitario_neto'];
                    if($this->escala($qd,3)===0)$this->error('detalles','Cada línea incluida debe tener cantidad documentada mayor que cero.');
                    if($this->escala($cost,4)===0&&!$line['confirmar_cero'])$this->error('detalles','Confirme expresamente el costo cero.');
                    if(!$isExempt&&!in_array($o->tributo,['AFECTO','EXENTO'],true))$this->error('detalles','Un producto no tiene condición tributaria definida.');
                    $exLine=$isExempt||$o->tributo==='EXENTO';$docMoney=$this->importe($qd,$cost);$recMoney=$this->importe($qr,$cost);
                    $seen[$lineId]=true;
                    $lines[]=[$o,$lineId,$qd,$qr,$cost,$exLine,$docMoney,$recMoney];
                }
                foreach($old as $lineId=>$existing)if(!isset($seen[(int)$lineId]))$this->error('detalles','Las líneas guardadas no se eliminan. Corrija sus cantidades y costos.');
                $header=['proveedor_id'=>$prov->id,'documento_id'=>$doc->id,'folio'=>$folio,'orden_compra_id'=>$oc->id,
                  'fecha_documento'=>$v['fecha_documento'],'estado_id'=>$compEstado,'subtotal_neto'=>$net,'total_exento'=>$ex,
                  'iva_recuperable'=>$iva,'otros_impuestos'=>$other,'total'=>$total,'total_informado'=>(int)$v['total_informado'],
                  'signo_contable'=>1,'observaciones'=>$v['observaciones']??null];
                if($c){$compra=(int)$c->id;DB::table('compras')->where('id',$compra)->update($header);}
                else {$compra=DB::table('compras')->insertGetId($header+['creado_por'=>$user]);}
                $rh=['orden_compra_id'=>$oc->id,'compra_id'=>$compra,'proveedor_id'=>$prov->id,'bodega_id'=>$b->id,
                  'recepcion_tipo'=>1,'estado_id'=>$recEstado,'fecha_recepcion'=>$v['fecha_recepcion'].' 00:00:00',
                  'subtotal_neto'=>$net,'total_exento'=>$ex,'total_iva'=>$iva,'otros_impuestos'=>$other,'total'=>$total,
                  'observaciones'=>$v['observaciones']??null,'version_borrador'=>$r?(int)$r->version_borrador+1:1];
                if($r){$rid=(int)$r->id;DB::table('recepciones')->where('id',$rid)->update($rh);$numero=$r->numero_interno;}
                else {
                    $rid=DB::table('recepciones')->insertGetId($rh+['numero_interno'=>'TMP-'.Str::uuid(),'creado_por'=>$user]);
                    $numero='REC-'.str_pad((string)$rid,8,'0',STR_PAD_LEFT);
                    DB::table('recepciones')->where('id',$rid)->update(['numero_interno'=>$numero]);
                }
                $next=(int)($old->max('numero_linea')??0);
                foreach($lines as [$o,$lineId,$qd,$qr,$cost,$exLine,$docMoney,$recMoney]){
                    $prev=$old->get($lineId);$num=$prev?(int)$prev->numero_linea:++$next;
                    $tax=$exLine?0:$this->iva($docMoney);
                    $cd=['compra_id'=>$compra,'numero_linea'=>$num,'producto_id'=>$o->producto_id,'descripcion'=>$o->nombre,
                      'cantidad'=>$qd,'costo_unitario_neto'=>$cost,'es_exento'=>$exLine?1:0,'tasa_iva'=>$exLine?0:19,
                      'monto_neto'=>$exLine?0:$docMoney,'monto_exento'=>$exLine?$docMoney:0,'monto_iva'=>$tax,'total'=>$docMoney+$tax];
                    if($prev){$cdId=(int)$prev->compra_detalle_id;DB::table('compras_detalle')->where('id',$cdId)->where('compra_id',$compra)->update($cd);}
                    else $cdId=DB::table('compras_detalle')->insertGetId($cd);
                    $taxRec=$exLine?0:$this->iva($recMoney);
                    $rd=['recepcion_id'=>$rid,'numero_linea'=>$num,'producto_id'=>$o->producto_id,'compra_detalle_id'=>$cdId,
                      'detalle_orden_compra_id'=>$lineId,'cantidad_documentada'=>$qd,'cantidad_recibida'=>$qr,
                      'cantidad_aceptada'=>0,'cantidad_rechazada'=>0,'costo_unitario_neto'=>$cost,
                      'monto_neto'=>$exLine?0:$recMoney,'monto_exento'=>$exLine?$recMoney:0,'monto_iva'=>$taxRec,'total'=>$recMoney+$taxRec];
                    if($prev)DB::table('recepcion_detalle')->where('id',$prev->id)->update($rd);
                    else DB::table('recepcion_detalle')->insert($rd);
                }
                return ['id'=>$rid,'numero_interno'=>$numero,'compra_id'=>$compra,'estado'=>'PENDIENTE','version_borrador'=>$rh['version_borrador'],'mensaje'=>'Borrador guardado. No se modificó el inventario.'];
            },3);
        }catch(QueryException $e){
            if((int)($e->errorInfo[1]??0)===1062)$this->error('folio','Ya existe un documento con estos datos. Revise el listado.');
            throw $e;
        }
        return response()->json($out,$id===null?201:200);
    }
}
