<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\CostoPromedioPonderado;
use App\Services\RegularizacionRecepcion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Consolidaciones con OC. Borrador independiente, cierre atómico con CPP global. */
final class ConsolidacionesControlador
{
    private const FINAL=['RECEPCIONADA','DESCUADRADA','FINALIZADA'];
    private function decimal($v): BigDecimal {return BigDecimal::of((string)$v);}
    private function saldo(BigDecimal $v,int $scale=8): string {
        abort_if($v->isNegative()||$v->isGreaterThan($scale===4?'99999999999.9999':'99999999999999999999.99999999'),409,'Importe o saldo fuera de rango.');
        return (string)$v->toScale($scale,(defined(RoundingMode::class.'::HalfUp')?constant(RoundingMode::class.'::HalfUp'):constant(RoundingMode::class.'::HALF_UP')));
    }
    private function estado(string $m,string $e): int {
        $id=DB::table('modulos_estados as me')->join('modulos as m','m.id','=','me.modulo_id')->join('estados as e','e.id','=','me.estado_id')
          ->where('m.codigo',$m)->where('e.codigo',$e)->where('m.activo',1)->where('e.activo',1)->where('me.activo',1)->value('e.id');
        abort_unless($id,409,'Estado '.$e.' no configurado para '.$m.'.');return (int)$id;
    }
    private function codigo(int $id): string {return (string)DB::table('estados')->where('id',$id)->value('codigo');}
    private function usuario(Request $r): int {$u=(int)($r->user()?->id??0);abort_unless($u>0,401);return $u;}
    private function contexto(Request $r,bool $lock=false): int {
        return (int)app(\App\Services\ContextoCompras::class)->resolver($r,$lock)->id;
    }
    private function bodega(int $id,int $sid,bool $lock=false): void {
        $q=DB::table('inventario_bodegas_sucursales')->where('bodega_id',$id)->where('sucursal_id',$sid);
        if($lock)$q->lockForUpdate();
        abort_unless($q->first(),404,'Bodega no disponible en la sucursal.');
    }
    private function orden(int $id,int $sid,bool $lock=false): object {
        $q=DB::table('ordenes_compra')->where('id',$id)->where('sucursal_id',$sid);if($lock)$q->lockForUpdate();
        $o=$q->first();abort_unless($o,404,'Orden no encontrada.');return $o;
    }
    private function recibos(int $oc,int $sid): array {
        // Fallar de forma segura: no omitir recepciones previas sin atribuir.
        $bad=DB::table('recepciones as r')->leftJoin('compras as c','c.id','=','r.compra_id')
          ->leftJoin('inventario_bodegas_sucursales as bs','bs.bodega_id','=','r.bodega_id')
          ->where('r.orden_compra_id',$oc)->where(fn($q)=>$q->whereNull('r.sucursal_id')->orWhere('r.sucursal_id','<>',$sid)
            ->orWhereNull('c.sucursal_id')->orWhere('c.sucursal_id','<>',$sid)->orWhereNull('bs.sucursal_id')->orWhere('bs.sucursal_id','<>',$sid)
            ->orWhereNull('c.orden_compra_id')->orWhereColumn('c.orden_compra_id','<>','r.orden_compra_id'));
        abort_if($bad->exists(),409,'Orden con recepciones o compras sin atribución válida.');
        return DB::table('recepciones as r')->join('estados as e','e.id','=','r.estado_id')->join('compras as c','c.id','=','r.compra_id')
          ->join('bodegas as b','b.id','=','r.bodega_id')->where('r.orden_compra_id',$oc)->where('r.sucursal_id',$sid)->where('e.codigo','<>','NULA')
          ->orderBy('r.id')->get(['r.*','e.codigo as estado','c.folio','b.nombre as bodega'])->map(fn($r)=>(array)$r)->all();
    }
    private function eligible(array $r,?int $id=null): bool {
        return in_array($r['estado'],self::FINAL,true)&&(int)$r['stock_aplicado']===1
          &&!empty($r['token_confirmacion'])&&($r['recepcion_consolidada_id']===null||(int)$r['recepcion_consolidada_id']===$id);
    }
    public function candidatas(Request $request,int $oc)
    {
        $sid=$this->contexto($request);$o=$this->orden($oc,$sid);abort_unless($this->codigo($o->estado_id)==='ACTIVA',409,'Orden no activa.');
        $r=$this->recibos($oc,$sid);
        return response()->json(['orden'=>$o,'recepciones'=>array_values(array_filter($r,fn($x)=>$this->eligible($x))),
          'otras_recepciones'=>array_values(array_filter($r,fn($x)=>!$this->eligible($x)))]);
    }
    private function lineas(array $ids): array {
        return DB::table('recepcion_detalle as d')->join('productos as p','p.id','=','d.producto_id')
          ->whereIn('d.recepcion_id',$ids)->orderBy('d.id')->get(['d.*','p.sku','p.nombre'])->map(fn($x)=>(array)$x)->all();
    }
    private function aggregate(array $rows): array {
        $groups=[];$keys=['cantidad_documentada','cantidad_recibida','cantidad_aceptada','cantidad_rechazada','descuento','monto_neto','monto_exento','monto_iva','total'];
        foreach($rows as $d){
            $k=(int)$d['producto_id'].':'.(int)$d['detalle_orden_compra_id'];
            if(!isset($groups[$k])){
                $g=['producto_id'=>$d['producto_id'],'detalle_orden_compra_id'=>$d['detalle_orden_compra_id'],'sku'=>$d['sku'],'nombre'=>$d['nombre'],'valor'=>BigDecimal::zero()];
                foreach($keys as $f)$g[$f]=BigDecimal::zero();$groups[$k]=$g;
            }
            foreach($keys as $f)$groups[$k][$f]=$groups[$k][$f]->plus((string)($d[$f]??0));
            $groups[$k]['valor']=$groups[$k]['valor']->plus($this->decimal($d['cantidad_aceptada'])->multipliedBy((string)$d['costo_unitario_neto']));
        }
        ksort($groups,SORT_NATURAL);$out=[];
        foreach($groups as $g){
            $g['costo_unitario_neto_ponderado']=$g['cantidad_aceptada']->isZero()?'0.00000000':$this->saldo($g['valor']->dividedBy($g['cantidad_aceptada'],8,(defined(RoundingMode::class.'::HalfUp')?constant(RoundingMode::class.'::HalfUp'):constant(RoundingMode::class.'::HALF_UP'))));
            foreach($keys as $f)$g[$f]=$this->saldo($g[$f]);
            $g['valor']=$this->saldo($g['valor']);$out[]=$g;
        }return $out;
    }
    private function estadoRevision(object $o,array $all,array $selected,array $lines,?object $c): array {
        $ids=array_map('intval',array_column($selected,'id'));sort($ids);
        $diff=DB::table('recepcion_diferencias')->whereIn('recepcion_id',$ids)->orderBy('id')->get();
        $agg=$this->aggregate($lines);$sum=[];
        foreach($agg as $l){$k=(int)$l['detalle_orden_compra_id'];$sum[$k]=($sum[$k]??BigDecimal::zero())->plus($l['cantidad_aceptada']);}
        $remaining=[];$orderLines=DB::table('detalles_ordenes_compra as d')->join('productos as p','p.id','=','d.producto_id')->where('d.orden_compra_id',$o->id)->orderBy('d.id')->get(['d.id','d.producto_id','d.cantidad','p.sku','p.nombre']);
        foreach($orderLines as $l){$q=$sum[(int)$l->id]??BigDecimal::zero();$delta=$this->decimal($l->cantidad)->minus($q);if(!$delta->isZero())$remaining[]=['sku'=>$l->sku,'nombre'=>$l->nombre,'cantidad_oc'=>$l->cantidad,'cantidad_aceptada'=>(string)$q,'diferencia'=>(string)$delta];}
        $others=array_values(array_filter($all,fn($x)=>!in_array((int)$x['id'],$ids,true)));
        $block=[];
        if(!$ids||!$lines)$block[]='Seleccione recepciones confirmadas.';
        if($others)$block[]='Hay otras recepciones vigentes de la orden. Inclúyalas o resuelva sus borradores antes de cerrar.';
        if($remaining&&$diff->isEmpty())$block[]='La orden incompleta no tiene diferencias registradas que justifiquen su cierre.';
        if($diff->isEmpty()&&array_filter($selected,fn($r)=>$r['estado']==='DESCUADRADA'))$block[]='Hay recepciones descuadradas sin diferencias registradas.';
        if($this->codigo($o->estado_id)!=='ACTIVA')$block[]='La orden ya no está activa.';
        $hash=hash('sha256',json_encode([$o->id,$o->estado_id,$c?->id,$c?->observaciones,$all,$selected,$lines,$diff,$remaining],JSON_THROW_ON_ERROR));
        return ['orden'=>$o,'consolidacion'=>$c,'recepciones'=>$selected,'otras_recepciones'=>$others,'productos'=>$agg,'diferencias'=>$diff,'saldos_oc'=>$remaining,'bloqueos'=>$block,'cierre_habilitado'=>!$block,'requiere_aceptacion'=>$diff->isNotEmpty()||!empty($remaining),'huella'=>$hash];
    }
    private function current(int $id,int $sid): array {
        $c=DB::table('recepciones_consolidadas')->where('id',$id)->where('sucursal_id',$sid)->first();abort_unless($c&&$this->codigo($c->estado_id)!=='NULA',404,'Consolidación no encontrada.');
        $this->bodega((int)$c->bodega_id,$sid);
        $o=$this->orden((int)$c->orden_compra_id,$sid);$all=$this->recibos((int)$o->id,$sid);
        abort_if(DB::table('recepciones')->where('recepcion_consolidada_id',$id)->where(fn($q)=>$q->whereNull('sucursal_id')->orWhere('sucursal_id','<>',$sid)->orWhere('orden_compra_id','<>',$o->id)->orWhereNull('orden_compra_id'))->exists(),409,'Consolidación con referencias sin atribución.');
        $selected=array_values(array_filter($all,fn($r)=>(int)$r['recepcion_consolidada_id']===$id));
        $res=$this->estadoRevision($o,$all,$selected,$this->lineas(array_column($selected,'id')),$c);
        $res['estado']=$this->codigo($c->estado_id);
        $res['solicitudes']=DB::table('solicitudes_regularizacion as s')->leftJoin('cola_notificaciones_correo as n','n.solicitud_id','=','s.id')->where('s.recepcion_consolidada_id',$id)->where('s.estado','<>','NULA')->orderBy('s.id')->get(['s.id','s.numero_interno','s.tipo_solicitud','s.estado','s.monto_total','n.id as notificacion_id','n.estado as estado_correo','n.ultimo_error']);
        if($res['estado']!=='ACTIVA')$res['cierre_habilitado']=false;
        $res['candidatas']=array_values(array_filter($all,fn($r)=>$this->eligible($r,$id)));
        return $res;
    }
    private function solicitudContexto(int $id,int $sid): object {
        $s=DB::table('solicitudes_regularizacion as s')->join('recepciones_consolidadas as c','c.id','=','s.recepcion_consolidada_id')
          ->join('recepciones as r','r.id','=','s.recepcion_id')->join('compras as p','p.id','=','s.compra_id')
          ->where('s.id',$id)->where('s.estado','<>','NULA')->where('c.sucursal_id',$sid)->where('r.sucursal_id',$sid)->where('p.sucursal_id',$sid)
          ->whereColumn('r.recepcion_consolidada_id','c.id')->whereColumn('r.compra_id','p.id')->whereColumn('s.proveedor_id','c.proveedor_id')
          ->whereColumn('s.proveedor_id','p.proveedor_id')->whereColumn('s.proveedor_id','r.proveedor_id')->first(['s.*']);
        abort_unless($s,404,'Solicitud no encontrada.');return $s;
    }
    public function solicitudPdf(Request $r,int $id){$pdf=DB::transaction(function()use($r,$id){$this->solicitudContexto($id,$this->contexto($r,true));return (new RegularizacionRecepcion())->pdf($id);},3);return response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="solicitud-'.$id.'.pdf"','Cache-Control'=>'private, no-store']);}
    public function reenviarSolicitud(Request $r,int $id){$this->usuario($r);$r->validate(['verificar_envio'=>'accepted']);$sid=$this->contexto($r);
        $n=DB::table('cola_notificaciones_correo')->where('id',$id)->where('origen_tipo','SOLICITUD_REGULARIZACION')->first();
        abort_unless($n,404,'Notificación no encontrada.');$s=$this->solicitudContexto((int)$n->solicitud_id,$sid);
        abort_unless((int)$n->proveedor_id===(int)$s->proveedor_id&&(int)$n->origen_id===(int)$s->id,409,'Notificación inconsistente.');$estado=(new RegularizacionRecepcion())->enviar($id,true,$r);return response()->json(['estado'=>$estado,'mensaje'=>'Estado de envío: '.$estado]);}
    public function ver(Request $r,int $id){return response()->json($this->current($id,$this->contexto($r)));}
    public function crear(Request $r){return $this->guardar($r,null);}
    public function actualizar(Request $r,int $id){return $this->guardar($r,$id);}
    private function guardar(Request $r,?int $id){
        $v=$r->validate(['orden_compra_id'=>'required|integer|min:1','recepciones'=>'required|array|min:1|max:200','recepciones.*'=>'required|integer|min:1|distinct','observaciones'=>'nullable|string|max:5000','huella'=>'nullable|string|size:64']);
        $u=$this->usuario($r);$ids=array_map('intval',$v['recepciones']);sort($ids);
        return response()->json(DB::transaction(function()use($v,$u,$ids,$id,$r){
            $sid=$this->contexto($r,true);$o=$this->orden((int)$v['orden_compra_id'],$sid,true);abort_unless($this->codigo($o->estado_id)==='ACTIVA',409,'Orden no activa.');
            $activa=$this->estado('CONSOLIDACION_RECEPCION','ACTIVA');$c=null;
            if($id!==null){
                $c=DB::table('recepciones_consolidadas')->where('id',$id)->where('sucursal_id',$sid)->lockForUpdate()->first();
                abort_unless($c&&(int)$c->orden_compra_id===(int)$o->id&&(int)$c->estado_id===$activa&&!$c->token_confirmacion,409,'Consolidación no editable.');
                abort_unless(isset($v['huella'])&&hash_equals($this->current($id,$sid)['huella'],$v['huella']),409,'La consolidación cambió. Vuelva a abrirla.');
            }
            $q=DB::table('recepciones_consolidadas')->where('orden_compra_id',$o->id)->where('estado_id',$activa);
            if($id!==null)$q->where('id','<>',$id);
            abort_if($q->exists(),409,'La orden ya tiene una consolidación activa. Modifique ese borrador.');
            $all=$this->recibos((int)$o->id,$sid);$selected=array_values(array_filter($all,fn($r)=>in_array((int)$r['id'],$ids,true)&&$this->eligible($r,$id)));
            abort_unless(count($selected)===count($ids),409,'Una recepción ya no está disponible.');
            $b=(int)$selected[0]['bodega_id'];$this->bodega($b,$sid,true);
            foreach($selected as $x)abort_unless((int)$x['bodega_id']===$b&&(int)$x['proveedor_id']===(int)$o->proveedor_id&&(int)$x['recepcion_tipo']===1,422,'Seleccione recepciones del mismo proveedor y bodega.');
            abort_unless(DB::table('bodegas')->where('id',$b)->where('activo',1)->exists(),409,'Bodega no activa.');
            $rows=$this->lineas($ids);abort_unless(count($rows)>0,422,'No hay líneas de recepción.');
            $agg=$this->aggregate($rows);
            $header=['sucursal_id'=>$sid,'orden_compra_id'=>$o->id,'proveedor_id'=>$o->proveedor_id,'bodega_id'=>$b,'recepcion_tipo'=>1,'estado_id'=>$activa,'cantidad_recepciones'=>count($ids),'observaciones'=>$v['observaciones']??null];
            foreach(['subtotal_neto','total_descuentos','total_exento','total_iva','otros_impuestos','total'] as $f){$sum=BigDecimal::zero();foreach($selected as $x)$sum=$sum->plus((string)$x[$f]);$header[$f]=$this->saldo($sum);}
            if($c){$cid=(int)$c->id;DB::table('recepciones_consolidadas')->where('id',$cid)->update($header);}
            else{$cid=DB::table('recepciones_consolidadas')->insertGetId($header+['numero_interno'=>'TMP-'.Str::uuid(),'creado_por'=>$u]);DB::table('recepciones_consolidadas')->where('id',$cid)->update(['numero_interno'=>'CON-'.str_pad((string)$cid,8,'0',STR_PAD_LEFT)]);}
            DB::table('recepciones')->where('recepcion_consolidada_id',$cid)->whereNotIn('id',$ids)->update(['recepcion_consolidada_id'=>null]);
            DB::table('recepciones')->whereIn('id',$ids)->update(['recepcion_consolidada_id'=>$cid]);
            // Líneas derivadas del borrador, sin kardex ni referencias definitivas.
            abort_if(DB::table('kardex')->where('origen_tipo','CONSOLIDACION_RECEPCION')->whereIn('origen_detalle_id',DB::table('recepciones_consolidadas_detalle')->where('recepcion_consolidada_id',$cid)->select('id'))->exists(),409,'El borrador tiene movimientos.');
            DB::table('recepciones_consolidadas_detalle')->where('recepcion_consolidada_id',$cid)->delete();
            foreach($agg as $i=>$l){unset($l['sku'],$l['nombre'],$l['valor']);DB::table('recepciones_consolidadas_detalle')->insert($l+['recepcion_consolidada_id'=>$cid,'numero_linea'=>$i+1]);}
            return ['id'=>$cid,'estado'=>'ACTIVA','mensaje'=>'Consolidación guardada. Sin movimientos de inventario.'];
        },3),$id===null?201:200);
    }
    public function anular(Request $r,int $id){
        $v=$r->validate(['huella'=>'required|string|size:64','motivo'=>'required|string|min:3|max:1000']);$u=$this->usuario($r);
        $hint=DB::table('recepciones_consolidadas')->where('id',$id)->where('sucursal_id',$this->contexto($r))->first();abort_unless($hint,404);
        return response()->json(DB::transaction(function()use($hint,$id,$v,$u,$r){
            $sid=$this->contexto($r,true);$this->orden((int)$hint->orden_compra_id,$sid,true);
            $c=DB::table('recepciones_consolidadas')->where('id',$id)->where('sucursal_id',$sid)->lockForUpdate()->first();
            abort_unless($c&&$this->codigo($c->estado_id)==='ACTIVA'&&!$c->token_confirmacion,409,'Solo se anulan consolidaciones activas.');
            abort_unless(hash_equals($this->current($id,$sid)['huella'],$v['huella']),409,'Actualice la consolidación.');
            abort_if(DB::table('kardex')->where('origen_tipo','CONSOLIDACION_RECEPCION')->whereIn('origen_detalle_id',DB::table('recepciones_consolidadas_detalle')->where('recepcion_consolidada_id',$id)->select('id'))->exists(),409,'Existen movimientos.');
            $ids=DB::table('recepciones')->where('recepcion_consolidada_id',$id)->pluck('id')->all();
            DB::table('recepciones')->where('recepcion_consolidada_id',$id)->update(['recepcion_consolidada_id'=>null]);
            DB::table('recepciones_consolidadas')->where('id',$id)->update(['estado_id'=>$this->estado('CONSOLIDACION_RECEPCION','NULA')]);
            $this->audit($u,$id,'ANULAR_CONSOLIDACION',['recepciones'=>$ids],['estado'=>'NULA','motivo'=>trim($v['motivo'])]);
            return ['id'=>$id,'estado'=>'NULA','mensaje'=>'Consolidación anulada; recepciones liberadas.'];
        },3));
    }
    private function audit(int $u,int $id,string $accion,array $old,array $new): void {
        DB::table('registros_auditoria')->insert(['usuario_id'=>$u,'accion'=>$accion,'tipo_entidad'=>'recepciones_consolidadas','entidad_id'=>$id,'valores_anteriores'=>json_encode($old,JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode($new,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
    }
    public function confirmar(Request $r,int $id){
        $v=$r->validate(['huella'=>'required|string|size:64','token_confirmacion'=>'required|uuid','aceptar_diferencias'=>'sometimes|boolean','motivo'=>'nullable|string|max:1000']);$u=$this->usuario($r);$token=strtolower($v['token_confirmacion']);
        $hint=DB::table('recepciones_consolidadas')->where('id',$id)->where('sucursal_id',$this->contexto($r))->first();abort_unless($hint,404);
        $result=DB::transaction(function()use($hint,$v,$u,$token,$id,$r){
            $sid=$this->contexto($r,true);$o=$this->orden((int)$hint->orden_compra_id,$sid,true);$c=DB::table('recepciones_consolidadas')->where('id',$id)->where('sucursal_id',$sid)->lockForUpdate()->first();abort_unless($c,404);
            $this->bodega((int)$c->bodega_id,$sid,true);
            if($c->token_confirmacion===$token&&$this->codigo($c->estado_id)==='CONSOLIDADA')return ['id'=>$id,'estado'=>'CONSOLIDADA','repetida'=>true,'mensaje'=>'Consolidación ya confirmada.'];
            abort_unless($this->codigo($c->estado_id)==='ACTIVA'&&!$c->token_confirmacion,409,'Consolidación no confirmable.');
            $review=$this->current($id,$sid);
            abort_unless(hash_equals($review['huella'],$v['huella']),409,'Cambió la consolidación. Revise nuevamente.');
            abort_unless($review['cierre_habilitado'],409,implode(' ',$review['bloqueos']));
            $motivo=trim((string)($v['motivo']??''));
            abort_if($review['requiere_aceptacion']&&(!(bool)($v['aceptar_diferencias']??false)||mb_strlen($motivo)<3),422,'Acepte las diferencias e indique el motivo para generar solicitudes.');
            abort_unless(DB::table('bodegas')->where('id',$c->bodega_id)->where('activo',1)->lockForUpdate()->first(),409,'Bodega no activa.');
            $doc=DB::table('tipo_documentos')->where('codigo','CONSOLIDACION_RECEPCION')->where('codigo_sii',0)->where('activo',1)->where('documento_compra',1)->where('familia','INTERNO')->first();
            abort_unless($doc,409,'Documento interno de consolidación no configurado.');
            $target=$this->estado('CONSOLIDACION_RECEPCION','CONSOLIDADA');$ocTarget=$this->estado('ORDEN_COMPRA','RECEPCIONADA');
            $receipts=$review['recepciones'];$ids=array_column($receipts,'id');$rows=$this->lineas($ids);
            foreach($receipts as $rec)abort_unless($this->eligible($rec,$id)&&(int)$rec['bodega_id']===(int)$c->bodega_id&&(int)$rec['proveedor_id']===(int)$c->proveedor_id,409,'Recepción no elegible.');
            $pids=array_values(array_unique(array_map('intval',array_column($rows,'producto_id'))));sort($pids,SORT_NUMERIC);
            $products=DB::table('productos')->whereIn('id',$pids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($products->count()===count($pids),409,'Producto inexistente.');
            abort_if(DB::table('saldos_inventario as s')->join('presentaciones_productos as pp','pp.id','=','s.presentacion_id')->whereIn('pp.producto_id',$pids)->where('s.cantidad','<>',0)->exists(),409,'Hay saldo en inventario legado; requiere conciliación.');
            $byProduct=[];$verified=[];
            foreach($rows as $d){
                $q=$this->decimal($d['cantidad_aceptada']);$cost=$this->decimal($d['costo_unitario_neto']);
                abort_unless($d['presentacion_id']===null&&$this->decimal($d['unidades_por_presentacion_snapshot'])->isEqualTo('1')&&!$q->isNegative()&&!$cost->isNegative(),409,'Línea de inventario inconsistente.');
                if($q->isZero())continue;
                $k=DB::table('kardex')->where('origen_tipo','RECEPCION')->where('origen_detalle_id',$d['id'])->where('operacion_codigo','ENTRADA_TRANSITO')->where('sucursal_id',$sid)->first();
                abort_unless($k&&(int)$k->producto_id===(int)$d['producto_id']&&(int)$k->bodega_id===(int)$c->bodega_id&&(int)$k->tipo_movimiento===3&&$this->decimal($k->cantidad_documento)->isEqualTo($q)&&$this->decimal($k->costo_unitario_neto)->isEqualTo($cost),409,'No se acredita la entrada a tránsito.');
                $pid=(int)$d['producto_id'];$byProduct[$pid]['q']=($byProduct[$pid]['q']??BigDecimal::zero())->plus($q);
                $byProduct[$pid]['v']=($byProduct[$pid]['v']??BigDecimal::zero())->plus($q->multipliedBy($cost));
            }
            $details=DB::table('recepciones_consolidadas_detalle')->where('recepcion_consolidada_id',$id)->orderBy('numero_linea')->lockForUpdate()->get();
            $agg=$review['productos'];abort_unless(count($agg)===$details->count(),409,'Detalle del borrador inconsistente.');
            foreach($details as $i=>$l){$a=$agg[$i];abort_unless((int)$l->producto_id===(int)$a['producto_id']&&(int)$l->detalle_orden_compra_id===(int)$a['detalle_orden_compra_id']&&$this->decimal($l->cantidad_aceptada)->isEqualTo($a['cantidad_aceptada'])&&$this->decimal($l->costo_unitario_neto_ponderado)->isEqualTo($a['costo_unitario_neto_ponderado']),409,'Detalle del borrador cambió. Guarde nuevamente.');}
            foreach($pids as $pid){
                if(!isset($byProduct[$pid]))continue;$p=$products->get($pid);abort_unless($p->activo,409,'Producto inactivo.');
                $bals=DB::table('stock')->where('producto_id',$pid)->orderBy('bodega_id')->lockForUpdate()->get();
                $oldQty=BigDecimal::zero();$stock=null;
                foreach($bals as $s){$a=$this->decimal($s->disponible);$t=$this->decimal($s->transito);$m=$this->decimal($s->comprometido);$f=$this->decimal($s->cantidad_fisica);
                    abort_unless(!$a->isNegative()&&!$t->isNegative()&&!$m->isNegative()&&$f->isEqualTo($a->plus($t)->plus($m)),409,'Stock inconsistente.');
                    $oldQty=$oldQty->plus($a)->plus($m);if((int)$s->bodega_id===(int)$c->bodega_id)$stock=$s;}
                $q=$byProduct[$pid]['q'];$value=$byProduct[$pid]['v']->toScale(16);
                abort_unless($stock&&$this->decimal($stock->transito)->isGreaterThanOrEqualTo($q),409,'Tránsito insuficiente.');
                $oldCpp=$this->decimal($p->costo_promedio_ponderado);$base=$oldCpp;$source='CPP_VIGENTE';
                if($oldQty->isZero()){$base=BigDecimal::zero();$source='SIN_STOCK_VALORIZADO';}
                elseif($oldCpp->isZero()){
                    $last=DB::table('kardex')->where('producto_id',$pid)->where('tipo_movimiento',1)->whereNotNull('cpp_actual')->orderByDesc('id')->first();
                    if($last){$base=$this->decimal($last->cpp_actual);$source='CPP_ULTIMA_ENTRADA_KARDEX';}
                    else{$base=$this->decimal($p->costo_neto);$source='COSTO_NETO_INICIAL';abort_unless($base->isPositive(),409,'CPP cero con stock previo sin costo comprobable. Regularice antes de cerrar.');}
                }
                $prior=$oldQty->multipliedBy($base)->toScale(16);
                $calc=(new CostoPromedioPonderado())->calcular($this->saldo($oldQty),(string)$oldCpp,$this->saldo($q),(string)$value,(string)$prior);
                $newCpp=$calc['cpp_posterior'];$newCost=$this->saldo($value->dividedBy($q,4,(defined(RoundingMode::class.'::HalfUp')?constant(RoundingMode::class.'::HalfUp'):constant(RoundingMode::class.'::HALF_UP'))),4);
                $a=$this->decimal($stock->disponible);$t=$this->decimal($stock->transito);$m=$this->decimal($stock->comprometido);$f=$this->decimal($stock->cantidad_fisica);
                // Una entrada por línea consolidada; CPP final global, no promedio de promedios.
                $first=true;foreach($details->where('producto_id',$pid) as $l){
                    $n=$this->decimal($l->cantidad_aceptada);if($n->isZero())continue;
                    $na=$a->plus($n);$nt=$t->minus($n);
                    DB::table('kardex')->insert(['sucursal_id'=>$sid,'producto_id'=>$pid,'bodega_id'=>$c->bodega_id,'tipo_documento_id'=>$doc->id,'folio_documento'=>$c->numero_interno,'tipo_movimiento'=>1,
                      'cantidad_anterior'=>$this->saldo($a),'cantidad_documento'=>$this->saldo($n),'cantidad_actual'=>$this->saldo($na),
                      'transito_anterior'=>$this->saldo($t),'transito_actual'=>$this->saldo($nt),'comprometido_anterior'=>$this->saldo($m),'comprometido_actual'=>$this->saldo($m),'fisico_anterior'=>$this->saldo($f),'fisico_actual'=>$this->saldo($f),
                      'costo_unitario_neto'=>$l->costo_unitario_neto_ponderado,'ultimo_costo_anterior'=>$first?$p->costo_neto:$newCost,'ultimo_costo_actual'=>$newCost,'cpp_anterior'=>$first?$p->costo_promedio_ponderado:$newCpp,'cpp_actual'=>$newCpp,
                      'origen_tipo'=>'CONSOLIDACION_RECEPCION','origen_detalle_id'=>$l->id,'operacion_codigo'=>'CONSOLIDAR_TRANSITO','token_idempotencia'=>(string)Str::uuid(),'registrado_por'=>$u,
                      'observaciones'=>'Tránsito a disponible. CPP global; base '.$source.'.']);
                    $a=$na;$t=$nt;$first=false;
                }
                DB::table('stock')->where('id',$stock->id)->update(['disponible'=>$this->saldo($a),'transito'=>$this->saldo($t)]);
                DB::table('productos')->where('id',$pid)->update(['costo_neto'=>$newCost,'costo_promedio_ponderado'=>$newCpp]);
                $verified[]=['producto_id'=>$pid,'fuente_valor_anterior'=>$source,'calculo'=>$calc,'ultimo_costo'=>$newCost];
            }
            // Recupera documentos confirmados antes de V80 al consolidarlos, sin reescribir snapshots.
            foreach($receipts as $rec)(new \App\Services\LibroComprasInterno())->registrar((int)$rec['compra_id'],$rec['numero_interno']);
            $notificaciones=$review['requiere_aceptacion']?(new RegularizacionRecepcion())->generar($c,$review,$u,$motivo):[];
            DB::table('recepciones_consolidadas')->where('id',$id)->update(['estado_id'=>$target,'fecha_consolidacion'=>now(),'token_confirmacion'=>$token,'consolidado_por'=>$u]);
            DB::table('ordenes_compra')->where('id',$o->id)->update(['estado_id'=>$ocTarget,'estado'=>'recepcionada','recibida_en'=>now()]);
            $this->audit($u,$id,'CONFIRMAR_CONSOLIDACION',['estado'=>'ACTIVA'],['estado'=>'CONSOLIDADA','recepciones'=>$ids,'cpp'=>$verified,'token'=>$token,'notificaciones'=>$notificaciones,'motivo'=>$motivo]);
            return ['id'=>$id,'estado'=>'CONSOLIDADA','repetida'=>false,'notificaciones'=>$notificaciones,'mensaje'=>'Consolidación confirmada. Stock disponible, CPP y orden actualizados.'];
        },3);
        $result['correos']=[];
        // Un primer intento fuera de la transacción. La cola persiste aunque falle SMTP.
        foreach(array_slice($result['notificaciones']??[],0,3) as $nid){try{$result['correos'][]=['id'=>$nid,'estado'=>(new RegularizacionRecepcion())->enviar($nid,false,$r)];}catch(\Throwable $e){$result['correos'][]=['id'=>$nid,'estado'=>'PENDIENTE_VERIFICACION'];}}
        $result['correos_pendientes']=max(0,count($result['notificaciones']??[])-count($result['correos']));
        if(!empty($result['notificaciones']))$result['mensaje'].=' Revise las solicitudes y el estado de sus correos.';
        return response()->json($result);
    }
}
