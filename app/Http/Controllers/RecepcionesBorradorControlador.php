<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/** Recepción con OC: borrador, revisión y confirmación física a tránsito. */
final class RecepcionesBorradorControlador
{
    private function estado(string $modulo,string $codigo='PENDIENTE'): int
    {
        $id=DB::table('modulos_estados as me')->join('modulos as m','m.id','=','me.modulo_id')
          ->join('estados as e','e.id','=','me.estado_id')->where('m.codigo',$modulo)
          ->where('m.activo',1)->where('me.activo',1)->where('e.activo',1)->where('e.codigo',$codigo)->value('e.id');
        abort_unless($id,409,'Estado '.$codigo.' no configurado.');
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
        return response()->json(['recepcion'=>$r,'detalles'=>DB::table('recepcion_detalle')->where('recepcion_id',$id)->orderBy('numero_linea')->get(),'diferencias'=>DB::table('recepcion_diferencias')->where('recepcion_id',$id)->orderBy('id')->get()]);
    }

    /** V74A: revisión de cierre, exclusivamente de lectura. No confirma existencias. */
    public function revisar(int $id)
    {
        $r=DB::table('recepciones as r')->join('estados as e','e.id','=','r.estado_id')
          ->join('compras as c','c.id','=','r.compra_id')->where('r.id',$id)->where('e.codigo','<>','NULA')
          ->select('r.*','e.codigo as estado','c.folio','c.total_informado','c.iva_recuperable','c.subtotal_neto as compra_neto','c.total_exento as compra_exento','c.total as compra_total')->first();
        abort_unless($r,404,'Recepción no encontrada.');
        abort_unless(in_array($r->estado,['ACTIVA','PENDIENTE'],true),409,'La revisión preliminar solo está disponible para recepciones activas.');
        $lines=DB::table('recepcion_detalle as d')->join('productos as p','p.id','=','d.producto_id')
          ->leftJoin('compras_detalle as cd','cd.id','=','d.compra_detalle_id')
          ->leftJoin('detalles_ordenes_compra as oc','oc.id','=','d.detalle_orden_compra_id')
          ->where('d.recepcion_id',$id)->orderBy('d.numero_linea')
          ->get(['d.*','p.sku','p.nombre','oc.cantidad as cantidad_oc','oc.costo_unitario_neto as costo_oc','cd.monto_neto as neto_documentado','cd.monto_exento as exento_documentado']);
        $dif=[];$net=BigDecimal::zero();$ex=BigDecimal::zero();
        $ocLines=DB::table('detalles_ordenes_compra')->where('orden_compra_id',$r->orden_compra_id)->get()->keyBy('id');
        // Sólo recepciones físicas definitivas: no contar otros borradores ni anuladas.
        $prev=DB::table('recepcion_detalle as d')->join('recepciones as rr','rr.id','=','d.recepcion_id')
          ->join('estados as e','e.id','=','rr.estado_id')->where('rr.orden_compra_id',$r->orden_compra_id)
          ->where('rr.id','<>',$id)->whereIn('e.codigo',['RECEPCIONADA','DESCUADRADA','FINALIZADA'])
          ->selectRaw('d.detalle_orden_compra_id, SUM(d.cantidad_aceptada) as cantidad')->groupBy('d.detalle_orden_compra_id')
          ->get()->keyBy('detalle_orden_compra_id');
        $remaining=[];foreach($ocLines as $o){$remaining[(int)$o->id]=BigDecimal::of((string)$o->cantidad)->minus((string)($prev->get($o->id)->cantidad??'0'));}
        foreach($lines as $d){
            $qd=BigDecimal::of((string)$d->cantidad_documentada);$qr=BigDecimal::of((string)$d->cantidad_recibida);
            $delta=$qr->minus($qd);
            $ocPending=$remaining[(int)$d->detalle_orden_compra_id]??null;
            $base=['recepcion_detalle_id'=>$d->id,'producto_id'=>$d->producto_id,'sku'=>$d->sku,'producto'=>$d->nombre,
                'cantidad_documentada'=>(string)$qd,'cantidad_contada'=>(string)$qr,'cantidad_oc_pendiente'=>$ocPending===null?null:(string)$ocPending];
            if(!$delta->isZero())$dif[]=$base+['tipo'=>$delta->isNegative()?'FALTANTE':'EXCEDENTE','cantidad_diferencia'=>(string)$delta->abs(),'motivo'=>'Cantidad física distinta del documento.'];
            if($ocPending===null)$dif[]=$base+['tipo'=>'PRODUCTO_ERRONEO','cantidad_diferencia'=>(string)$qr,'motivo'=>'Línea sin correspondencia en la orden.'];
            else {
                $exceso=$qr->minus($ocPending->isNegative()?'0':$ocPending);
                if($exceso->isPositive())$dif[]=$base+['tipo'=>'EXCEDENTE','cantidad_diferencia'=>(string)$exceso,'motivo'=>'Cantidad superior al saldo pendiente de la orden.'];
                $remaining[(int)$d->detalle_orden_compra_id]=$ocPending->minus($qr);
            }
            if($d->costo_oc!==null&&!BigDecimal::of((string)$d->costo_oc)->isEqualTo((string)$d->costo_unitario_neto))
                $dif[]=$base+['tipo'=>'DOCUMENTAL','cantidad_diferencia'=>'0','motivo'=>'Costo unitario del documento diferente al de la orden.'];
            $net=$net->plus((string)($d->neto_documentado??'0'));$ex=$ex->plus((string)($d->exento_documentado??'0'));
        }
        $money=[['Neto del documento versus líneas',$r->compra_neto,$net],['Exento del documento versus líneas',$r->compra_exento,$ex],['Total informado versus calculado',$r->total_informado,BigDecimal::of((string)$r->compra_total)]];
        foreach($money as [$label,$entered,$expected]){
            if($entered===null||!BigDecimal::of((string)$entered)->isEqualTo($expected)){
                $dif[]=['tipo'=>'DOCUMENTAL','recepcion_detalle_id'=>null,'producto_id'=>null,'sku'=>null,'producto'=>null,'cantidad_documentada'=>null,'cantidad_contada'=>null,'cantidad_oc_pendiente'=>null,'cantidad_diferencia'=>'0','motivo'=>$label.': informado '.($entered??'no registrado').', calculado '.$expected.'.'];
            }
        }
        if($lines->isEmpty())$dif[]=['tipo'=>'DOCUMENTAL','recepcion_detalle_id'=>null,'producto_id'=>null,'sku'=>null,'producto'=>null,'cantidad_documentada'=>null,'cantidad_contada'=>null,'cantidad_oc_pendiente'=>null,'cantidad_diferencia'=>'0','motivo'=>'La recepción no tiene líneas.'];
        $pendientes=[];foreach($remaining as $lineId=>$qty){if($qty->isPositive())$pendientes[]=['detalle_orden_compra_id'=>$lineId,'cantidad_pendiente'=>(string)$qty];}
        return response()->json(['id'=>$id,'numero_interno'=>$r->numero_interno,'estado'=>$r->estado,'version_borrador'=>(int)$r->version_borrador,
          'resultado_previsto'=>$dif?'DESCUADRADA':'RECEPCIONADA','diferencias'=>$dif,'saldos_oc'=>$pendientes,'lineas'=>$lines,
          'cierre_habilitado'=>!$lines->isEmpty(),'huella_revision'=>$this->huella($r,$lines,$dif,$pendientes),'mensaje'=>'Revise las diferencias antes de confirmar. Las unidades contadas ingresarán a tránsito.']);
    }


    private function huella(object $r,$lines,array $dif,array $pendientes): string
    {
        return hash('sha256',json_encode([$r->id,$r->version_borrador,$r->compra_total,$r->total_informado,$lines,$dif,$pendientes],JSON_THROW_ON_ERROR));
    }
    private function saldo(BigDecimal $n): string
    {
        abort_if($n->isNegative()||$n->isGreaterThan('99999999999999999999.99999999'),409,'Saldo de inventario fuera de rango.');
        return (string)$n->toScale(8,RoundingMode::UNNECESSARY);
    }

    /** Confirmación atómica: acepta conteo físico en tránsito, sin valorizar dos veces.
     * La OC permanece activa hasta consolidación. No emite documentos ni correo. */
    public function confirmar(Request $request,int $id)
    {
        $v=$request->validate([
          'version_borrador'=>'required|integer|min:0|max:4294967294',
          'huella_revision'=>['required','regex:/^[a-f0-9]{64}$/D'],
          'token_confirmacion'=>'required|uuid',
          'aceptar_diferencias'=>'required|boolean',
          'motivo'=>'nullable|string|max:1000',
        ]);
        $user=(int)($request->user()?->id??0);abort_unless($user>0,401);
        $token=strtolower($v['token_confirmacion']);
        // Obtener la OC antes de la transacción: el primer snapshot de lectura
        // debe establecerse DESPUÉS de bloquear la OC, no antes.
        $hint=DB::table('recepciones')->where('id',$id)->first(['orden_compra_id']);
        abort_unless($hint&&$hint->orden_compra_id,404,'Recepción con orden de compra no encontrada.');
        return response()->json(DB::transaction(function()use($id,$v,$user,$token,$hint){
            $oc=DB::table('ordenes_compra')->where('id',$hint->orden_compra_id)->lockForUpdate()->first();
            $r=DB::table('recepciones')->where('id',$id)->lockForUpdate()->first();
            abort_unless($oc&&$r&&(int)$r->orden_compra_id===(int)$oc->id,409,'La orden de la recepción cambió.');
            $estado=DB::table('estados')->where('id',$r->estado_id)->value('codigo');
            if($r->token_confirmacion===$token&&$r->stock_aplicado&&in_array($estado,['RECEPCIONADA','DESCUADRADA','FINALIZADA'],true)){
                return ['id'=>$id,'estado'=>$estado,'repetida'=>true,'mensaje'=>'Recepción ya confirmada. No se repitió el inventario.'];
            }
            abort_unless(in_array($estado,['ACTIVA','PENDIENTE'],true)&&!$r->stock_aplicado&&!$r->token_confirmacion&&!$r->recepcion_consolidada_id,409,'La recepción ya no puede confirmarse.');
            abort_unless((int)$r->version_borrador===(int)$v['version_borrador'],409,'El borrador cambió. Revise nuevamente.');
            abort_unless(DB::table('estados')->where('id',$oc->estado_id)->where('codigo','ACTIVA')->exists(),409,'La orden ya no está activa.');
            abort_unless((int)$oc->proveedor_id===(int)$r->proveedor_id&&(int)$r->recepcion_tipo===1,409,'Proveedor u origen de recepción inconsistente.');
            $c=DB::table('compras')->where('id',$r->compra_id)->lockForUpdate()->first();
            abort_unless($c&&(int)$c->proveedor_id===(int)$r->proveedor_id&&(int)$c->orden_compra_id===(int)$oc->id&&in_array(DB::table('estados')->where('id',$c->estado_id)->value('codigo'),['ACTIVA','PENDIENTE'],true),409,'Documento de compra no confirmable.');
            abort_if(DB::table('recepciones')->where('compra_id',$c->id)->where('id','<>',$id)->exists(),409,'El documento tiene otra recepción.');
            abort_if(DB::table('recepciones')->where('token_confirmacion',$token)->where('id','<>',$id)->exists(),409,'Confirmación utilizada por otro documento.');
            abort_unless(DB::table('bodegas')->where('id',$r->bodega_id)->where('activo',1)->lockForUpdate()->first(),409,'Bodega no activa.');
            $doc=DB::table('tipo_documentos')->where('id',$c->documento_id)->where('activo',1)->where('documento_compra',1)->first();
            abort_unless($doc&&in_array($doc->familia,['FACTURA','FACTURA_COMPRA','BOLETA','GUIA'],true),409,'Tipo de documento no confirmable.');
            // Revisión nuevamente en servidor bajo bloqueo de OC. Ningún importe
            // ni diferencia proviene del cliente.
            $review=$this->revisar($id)->getData(true);
            abort_unless(hash_equals($review['huella_revision'],$v['huella_revision']),409,'Cambió la revisión. Abra nuevamente las diferencias.');
            $dif=$review['diferencias'];$lines=$review['lineas'];
            abort_unless(count($lines)>0,422,'La recepción requiere líneas.');
            $motivo=trim((string)($v['motivo']??''));
            if($dif&&(!(bool)$v['aceptar_diferencias']||mb_strlen($motivo)<3))$this->error('motivo','Confirme los descuadres e indique el motivo.');
            $target=$dif?'DESCUADRADA':'RECEPCIONADA';
            $targetId=$this->estado('RECEPCION',$target);$compraEstado=$this->estado('FACTURA_COMPRA','RECEPCIONADA');
            abort_if(DB::table('recepcion_diferencias')->where('recepcion_id',$id)->exists(),409,'Ya existen diferencias persistidas. Revise el historial.');
            abort_if(DB::table('kardex')->where('origen_tipo','RECEPCION')->whereIn('origen_detalle_id',array_column($lines,'id'))->exists(),409,'Ya existen movimientos de esta recepción.');
            $ids=array_values(array_unique(array_map('intval',array_column($lines,'producto_id'))));sort($ids,SORT_NUMERIC);
            // Orden de bloqueo estable, también para crear saldos nuevos.
            $products=DB::table('productos')->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($products->count()===count($ids),409,'Producto inexistente.');
            // Inventario legado por presentación: no crear un segundo saldo para
            // un SKU con saldo anterior sin migración/reconciliación explícita.
            abort_if(DB::table('saldos_inventario as si')->join('presentaciones_productos as pp','pp.id','=','si.presentacion_id')->whereIn('pp.producto_id',$ids)->where('si.cantidad','<>',0)->exists(),409,'Hay existencias en el inventario anterior. Se requiere conciliación antes de confirmar.');
            $movimientos=0;
            foreach($lines as $d){
                $p=$products->get($d['producto_id']);
                abort_unless($p->activo,409,'Hay productos inactivos.');
                abort_unless($d['presentacion_id']===null&&BigDecimal::of((string)$d['unidades_por_presentacion_snapshot'])->isEqualTo('1'),409,'La recepción requiere conversión de presentación; revise las unidades.');
                $ocLine=DB::table('detalles_ordenes_compra')->where('id',$d['detalle_orden_compra_id'])->where('orden_compra_id',$oc->id)->where('producto_id',$d['producto_id'])->first();
                $cd=DB::table('compras_detalle')->where('id',$d['compra_detalle_id'])->where('compra_id',$c->id)->where('producto_id',$d['producto_id'])->first();
                abort_unless($ocLine&&$cd,409,'Una línea no corresponde a su orden o documento.');
                $q=BigDecimal::of((string)$d['cantidad_recibida']);$cost=BigDecimal::of((string)$d['costo_unitario_neto']);
                abort_if($q->isNegative()||$cost->isNegative(),409,'Cantidad o costo negativo.');
                DB::table('recepcion_detalle')->where('id',$d['id'])->update(['cantidad_aceptada'=>$this->saldo($q),'cantidad_rechazada'=>0]);
                if($q->isZero())continue;
                $stock=DB::table('stock')->where('producto_id',$p->id)->where('bodega_id',$r->bodega_id)->lockForUpdate()->first();
                if(!$stock){
                    $sid=DB::table('stock')->insertGetId(['producto_id'=>$p->id,'bodega_id'=>$r->bodega_id,'disponible'=>0,'transito'=>0,'comprometido'=>0,'cantidad_fisica'=>0]);
                    $stock=DB::table('stock')->where('id',$sid)->lockForUpdate()->first();
                }
                $a=BigDecimal::of((string)$stock->disponible);$t=BigDecimal::of((string)$stock->transito);$m=BigDecimal::of((string)$stock->comprometido);$f=BigDecimal::of((string)$stock->cantidad_fisica);
                abort_unless(!$a->isNegative()&&!$t->isNegative()&&!$m->isNegative()&&$f->isEqualTo($a->plus($t)->plus($m)),409,'Stock previo inconsistente. Regularice antes de confirmar.');
                $nt=$t->plus($q);$nf=$f->plus($q);
                DB::table('stock')->where('id',$stock->id)->update(['transito'=>$this->saldo($nt),'cantidad_fisica'=>$this->saldo($nf)]);
                DB::table('kardex')->insert(['producto_id'=>$p->id,'bodega_id'=>$r->bodega_id,'tipo_documento_id'=>$doc->id,
                  'folio_documento'=>$c->folio,'tipo_movimiento'=>3,'cantidad_anterior'=>$this->saldo($a),'cantidad_documento'=>$this->saldo($q),
                  'cantidad_actual'=>$this->saldo($a),'transito_anterior'=>$this->saldo($t),'transito_actual'=>$this->saldo($nt),
                  'comprometido_anterior'=>$this->saldo($m),'comprometido_actual'=>$this->saldo($m),'fisico_anterior'=>$this->saldo($f),'fisico_actual'=>$this->saldo($nf),
                  'costo_unitario_neto'=>(string)$cost,'ultimo_costo_anterior'=>$p->costo_neto,'ultimo_costo_actual'=>$p->costo_neto,
                  'cpp_anterior'=>$p->costo_promedio_ponderado,'cpp_actual'=>$p->costo_promedio_ponderado,
                  'origen_tipo'=>'RECEPCION','origen_detalle_id'=>$d['id'],'operacion_codigo'=>'ENTRADA_TRANSITO','token_idempotencia'=>(string)Str::uuid(),
                  'registrado_por'=>$user,'observaciones'=>'Entrada física pendiente de consolidación; sin cambio de CPP.']);
                $movimientos++;
            }
            foreach($dif as $d)DB::table('recepcion_diferencias')->insert([
              'recepcion_id'=>$id,'recepcion_detalle_id'=>$d['recepcion_detalle_id'],'producto_id'=>$d['producto_id'],
              'tipo_diferencia'=>$d['tipo'],'cantidad_documentada'=>$d['cantidad_documentada'],
              'cantidad_oc_pendiente'=>$d['cantidad_oc_pendiente'],'cantidad_contada'=>$d['cantidad_contada'],
              'cantidad_diferencia'=>$d['cantidad_diferencia'],'estado_resolucion'=>'ABIERTA',
              'motivo'=>$d['motivo']]);
            (new \App\Services\LibroComprasInterno())->registrar((int)$c->id,$r->numero_interno);
            DB::table('compras')->where('id',$c->id)->update(['estado_id'=>$compraEstado]);
            DB::table('recepciones')->where('id',$id)->update(['estado_id'=>$targetId,'stock_aplicado'=>1,'stock_aplicado_en'=>now(),
              'token_confirmacion'=>$token,'version_borrador'=>(int)$r->version_borrador+1,'finalizado_por'=>$user]);
            DB::table('registros_auditoria')->insert(['usuario_id'=>$user,'accion'=>'CONFIRMAR_RECEPCION','tipo_entidad'=>'recepciones','entidad_id'=>$id,
              'valores_anteriores'=>json_encode(['estado'=>$estado,'version_borrador'=>$r->version_borrador],JSON_THROW_ON_ERROR),
              'valores_nuevos'=>json_encode(['estado'=>$target,'diferencias'=>count($dif),'movimientos'=>$movimientos,'motivo'=>$motivo,'token'=>$token],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
            return ['id'=>$id,'estado'=>$target,'diferencias'=>count($dif),'movimientos'=>$movimientos,'repetida'=>false,
              'mensaje'=>'Recepción confirmada como '.$target.'. Unidades contadas en tránsito; orden abierta para consolidación.'];
        },3));
    }

    /** Anulación lógica exclusiva de documentos sin movimientos ni consolidación. */
    public function anular(Request $request,int $id)
    {
        $v=$request->validate(['version_borrador'=>'required|integer|min:0|max:4294967294','motivo'=>'required|string|min:3|max:1000']);
        $motivo=trim($v['motivo']);if(mb_strlen($motivo)<3)$this->error('motivo','Ingrese el motivo de anulación.');
        $user=(int)($request->user()?->id??0);abort_unless($user>0,401);
        return response()->json(DB::transaction(function()use($id,$v,$motivo,$user){
            $hint=DB::table('recepciones')->where('id',$id)->first(['orden_compra_id']);
            abort_unless($hint,404,'Recepción no encontrada.');
            if($hint->orden_compra_id!==null)DB::table('ordenes_compra')->where('id',$hint->orden_compra_id)->lockForUpdate()->first();
            $r=DB::table('recepciones')->where('id',$id)->lockForUpdate()->first();
            abort_unless($r,404,'Recepción no encontrada.');
            $codigo=DB::table('estados')->where('id',$r->estado_id)->value('codigo');
            abort_unless(in_array($codigo,['ACTIVA','PENDIENTE'],true)&&!$r->stock_aplicado&&!$r->recepcion_consolidada_id&&!$r->token_confirmacion,409,'Solo se anulan recepciones activas sin movimientos.');
            abort_unless((int)$r->version_borrador===(int)$v['version_borrador'],409,'La recepción cambió. Actualice el listado.');
            $c=DB::table('compras')->where('id',$r->compra_id)->lockForUpdate()->first();
            abort_unless($c&&(int)$c->proveedor_id===(int)$r->proveedor_id&&(string)$c->orden_compra_id===(string)$r->orden_compra_id&&in_array(DB::table('estados')->where('id',$c->estado_id)->value('codigo'),['PENDIENTE','ACTIVA'],true),409,'El documento de compra ya no es anulable.');
            abort_if(DB::table('recepciones')->where('compra_id',$r->compra_id)->where('id','<>',$id)->exists(),409,'El documento está asociado a otra recepción.');
            abort_if(DB::table('libro_compras')->where('compra_id',$r->compra_id)->exists(),409,'El documento ya está registrado en el libro de compras.');
            abort_if(DB::table('kardex')->where('origen_tipo','RECEPCION')->whereIn('origen_detalle_id',DB::table('recepcion_detalle')->where('recepcion_id',$id)->select('id'))->exists(),409,'La recepción tiene movimientos de inventario.');
            $nula=$this->estado('RECEPCION','NULA');$compNula=$this->estado('FACTURA_COMPRA','NULA');
            DB::table('recepciones')->where('id',$id)->update(['estado_id'=>$nula,'version_borrador'=>(int)$r->version_borrador+1]);
            DB::table('compras')->where('id',$c->id)->update(['estado_id'=>$compNula]);
            DB::table('registros_auditoria')->insert(['usuario_id'=>$user,'accion'=>'ANULAR_RECEPCION','tipo_entidad'=>'recepciones','entidad_id'=>$id,
                'valores_anteriores'=>json_encode(['estado'=>$codigo,'estado_compra_id'=>$c->estado_id,'version_borrador'=>$r->version_borrador],JSON_THROW_ON_ERROR),
                'valores_nuevos'=>json_encode(['estado'=>'NULA','compra_id'=>$c->id,'motivo'=>$motivo],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
            return ['id'=>$id,'estado'=>'NULA','mensaje'=>'Recepción y documento anulados. Se conservó su historial; no se modificó inventario.'];
        },3));
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
