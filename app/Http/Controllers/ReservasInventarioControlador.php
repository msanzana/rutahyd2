<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\ContextoCompras;
use App\Services\PlanReservaInventario;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** V109E2B2: solicitudes pendientes; sin escrituras de stock ni rutas públicas. */
final class ReservasInventarioControlador extends Controller
{
    public function crear(Request $r){
        // Contratos de referencia/clase pendientes en producción. Solo fixture CI hasta acreditarlos.
        abort_unless(config('reservas.solicitudes_verificadas')===true&&config('reservas.clase_operacion')==='RESERVA',409,'Solicitudes de reserva no habilitadas.');
        $v=$r->validate(['nota_venta_id'=>'required|integer|min:1','items'=>'required|array|min:1|max:50','items.*.producto_id'=>'required|integer|min:1','items.*.bodega_id'=>'required|integer|min:1','items.*.cantidad'=>['required','string','regex:/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D'],'justificacion'=>'required|string|min:10|max:1000','token_creacion'=>'required|uuid']);
        $just=trim($v['justificacion']);abort_if(mb_strlen($just)<10,422,'Justificación insuficiente.');
        $token=strtolower($v['token_creacion']);$noteId=(int)$v['nota_venta_id'];$items=[];
        foreach($v['items'] as $item){
            $pid=(int)$item['producto_id'];$bid=(int)$item['bodega_id'];$q=BigDecimal::of($item['cantidad'])->toScale(8);
            abort_unless($q->isPositive(),422,'Cantidad positiva requerida.');
            $key=$pid.':'.$bid;abort_if(isset($items[$key]),422,'Producto y bodega repetidos.');
            $items[$key]=['producto_id'=>$pid,'bodega_id'=>$bid,'cantidad'=>(string)$q];
        }
        $items=array_values($items);usort($items,fn($a,$b)=>[$a['producto_id'],$a['bodega_id']]<=>[$b['producto_id'],$b['bodega_id']]);
        $out=DB::transaction(function()use($r,$just,$token,$noteId,$items){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $old=DB::table('inventario_operaciones')->where('token_creacion',$token)->lockForUpdate()->first();
            if($old){
                abort_unless($old->clase==='RESERVA'&&(int)$old->motivo_codigo_snapshot===710&&(int)$old->sucursal_id===$sid&&(int)$old->creado_por===$uid&&$old->justificacion===$just,409,'Token de otra solicitud.');
                $links=DB::table('inventario_reservas_operaciones')->where('operacion_id',$old->id)->lockForUpdate()->get();
                abort_unless($links->count()===1,409,'Vínculo inválido.');
                $h=DB::table('inventario_reservas')->where('id',$links->first()->reserva_id)->where('sucursal_id',$sid)->lockForUpdate()->first();
                abort_unless($h&&(int)$h->nota_venta_id===$noteId&&(int)$h->creado_por===$uid,409,'Token de otra solicitud.');
                $saved=DB::table('inventario_reservas_detalle')->where('reserva_id',$h->id)->orderBy('producto_id')->orderBy('bodega_id')->lockForUpdate()->get()->map(fn($l)=>['producto_id'=>(int)$l->producto_id,'bodega_id'=>(int)$l->bodega_id,'cantidad'=>(string)BigDecimal::of((string)$l->cantidad_reservada)->toScale(8)])->all();
                abort_unless($saved===$items,409,'Token de otra solicitud.');
                return ['id'=>(int)$h->id,'operacion_id'=>(int)$old->id,'numero_interno'=>$h->numero_interno,'estado'=>$h->estado,'repetida'=>true,'movimiento_aplicado'=>false];
            }
            $note=DB::table('notas_venta')->where('id',$noteId)->where('sucursal_id',$sid)->lockForUpdate()->first();
            abort_unless($note,404,'Nota de venta no atribuida a esta sucursal.');
            abort_unless(in_array($note->estado,['recibida','por_preparar'],true)&&is_string($note->numero)&&trim($note->numero)!==''&&mb_strlen($note->numero)<=60,409,'Nota de venta no disponible para reserva.');
            // Alcance inicial: una única solicitud por nota, incluyendo histórico. Reapertura posterior requiere política explícita.
            abort_if(DB::table('inventario_reservas')->where('nota_venta_id',$noteId)->lockForUpdate()->first(),409,'Ya existe una reserva asociada a esta nota.');
            $demand=[];
            $lines=DB::table('detalles_notas_venta as d')->join('presentaciones_productos as p','p.id','=','d.presentacion_id')->where('d.nota_venta_id',$noteId)->select('d.cantidad_solicitada','d.cantidad_entregada','p.producto_id','p.unidades_por_envase','p.activo')->lockForUpdate()->get();
            abort_unless($lines->isNotEmpty(),409,'Nota sin detalle.');
            foreach($lines as $l){
                // No inferir conversiones de presentaciones legadas: solo equivalencia unitaria.
                abort_unless((int)$l->activo===1&&BigDecimal::of((string)$l->unidades_por_envase)->isEqualTo(1)&&BigDecimal::of((string)$l->cantidad_entregada)->isZero(),409,'Presentación no unitaria o nota con entrega previa.');
                $q=BigDecimal::of((string)$l->cantidad_solicitada)->toScale(8);$pid=(int)$l->producto_id;
                abort_unless($pid>0&&$q->isPositive(),409,'Detalle comercial inválido.');
                $demand[$pid]=($demand[$pid]??BigDecimal::zero())->plus($q);
            }
            $m=DB::table('inventario_motivos as m')->join('inventario_grupos_motivos as g','g.id','=','m.grupo_id')->join('inventario_tipos_movimiento as t','t.id','=','m.tipo_movimiento_id')->where('m.codigo',710)->where('m.activo',1)->where('g.activo',1)->where('t.activo',1)->select('m.*','g.codigo as grupo_codigo')->lockForUpdate()->first();
            abort_unless($m,409,'Motivo no disponible.');
            $state=DB::table('modulos_estados as me')->join('modulos as m','m.id','=','me.modulo_id')->join('estados as e','e.id','=','me.estado_id')->where('m.codigo','INVENTARIO_OPERACION')->where('m.activo',1)->where('me.activo',1)->where('e.activo',1)->where('e.codigo','INV_PEND_APROBACION')->value('e.id');
            abort_unless($state,409,'Estado no configurado.');
            $used=[];$plans=[];
            foreach($items as $i){
                $pid=$i['producto_id'];$bid=$i['bodega_id'];$q=BigDecimal::of($i['cantidad']);
                $used[$pid]=($used[$pid]??BigDecimal::zero())->plus($q);
                abort_unless(isset($demand[$pid])&&$used[$pid]->isLessThanOrEqualTo($demand[$pid]),409,'Cantidad superior a la solicitada en nota de venta.');
                $p=DB::table('productos')->where('id',$pid)->where('activo',1)->lockForUpdate()->first();abort_unless($p,404,'Producto no disponible.');
                abort_if(DB::table('saldos_inventario as s')->join('presentaciones_productos as pp','pp.id','=','s.presentacion_id')->where('pp.producto_id',$pid)->where('s.cantidad','<>',0)->exists(),409,'Inventario legado pendiente.');
                abort_unless(DB::table('bodegas as b')->join('inventario_bodegas_sucursales as bs','bs.bodega_id','=','b.id')->where('b.id',$bid)->where('b.activo',1)->where('bs.sucursal_id',$sid)->lockForUpdate()->first(['b.id']),404,'Bodega no autorizada.');
                $stocks=DB::table('stock')->where('producto_id',$pid)->where('bodega_id',$bid)->lockForUpdate()->get();abort_unless($stocks->count()===1,409,'Stock no disponible.');
                $plans[]=(new PlanReservaInventario())->calcular($m,$i['cantidad'],$stocks->first(),(string)$p->costo_promedio_ponderado,'0');
            }
            $id=DB::table('inventario_reservas')->insertGetId(['nota_venta_id'=>$noteId,'estado'=>'PENDIENTE','creado_por'=>$uid,'sucursal_id'=>$sid]);
            $num='RSV-'.str_pad((string)$id,8,'0',STR_PAD_LEFT);
            DB::table('inventario_reservas')->where('id',$id)->update(['numero_interno'=>$num]);
            $op=DB::table('inventario_operaciones')->insertGetId(['clase'=>'RESERVA','sucursal_id'=>$sid,'motivo_id'=>$m->id,'estado_id'=>$state,'motivo_codigo_snapshot'=>710,'motivo_nombre_snapshot'=>$m->nombre,'regla_snapshot'=>$m->regla,'politica_cpp_snapshot'=>$m->politica_cpp,'justificacion'=>$just,'referencia_folio'=>$note->numero,'revision_tributaria'=>'NO_APLICA','evaluacion_aviso_sii'=>'NO_APLICA','token_creacion'=>$token,'creado_por'=>$uid]);
            DB::table('inventario_operaciones')->where('id',$op)->update(['numero_interno'=>'RSV-OP-'.str_pad((string)$op,8,'0',STR_PAD_LEFT)]);
            DB::table('inventario_reservas_operaciones')->insert(['reserva_id'=>$id,'operacion_id'=>$op]);
            foreach($items as $n=>$i){
                $p=$plans[$n];
                DB::table('inventario_reservas_detalle')->insert(['reserva_id'=>$id,'producto_id'=>$i['producto_id'],'bodega_id'=>$i['bodega_id'],'cantidad_reservada'=>$i['cantidad'],'cantidad_despachada'=>0,'cantidad_liberada'=>0]);
                DB::table('inventario_operaciones_detalle')->insert(['operacion_id'=>$op,'numero_linea'=>$n+1,'producto_id'=>$i['producto_id'],'bodega_id'=>$i['bodega_id'],'cantidad'=>$p['cantidad'],'delta_disponible'=>$p['delta_disponible'],'delta_comprometido'=>$p['delta_comprometido'],'delta_transito'=>$p['delta_transito'],'delta_fisico'=>$p['delta_fisico'],'costo_unitario_snapshot'=>$p['cpp_anterior']]);
            }
            DB::table('inventario_operaciones_historial')->insert(['operacion_id'=>$op,'estado_anterior_id'=>null,'estado_nuevo_id'=>$state,'usuario_id'=>$uid,'accion'=>'CREAR','observaciones'=>'Solicitud '.$num.' sin movimiento de stock.']);
            DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>'RESERVA_SOLICITUD','tipo_entidad'=>'inventario_reservas','entidad_id'=>$id,'valores_anteriores'=>json_encode([],JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['estado'=>'PENDIENTE','nota_venta_id'=>$noteId,'operacion_id'=>$op,'items'=>$items],JSON_THROW_ON_ERROR)]);
            return ['id'=>(int)$id,'operacion_id'=>(int)$op,'numero_interno'=>$num,'estado'=>'PENDIENTE','repetida'=>false,'movimiento_aplicado'=>false];
        },3);
        return response()->json($out,$out['repetida']?200:201)->header('Cache-Control','no-store');
    }

    /** V109E3A: aprobación independiente; vuelve a validar la solicitud sin mover stock. */
    public function aprobar(Request $r,int $id){
        abort_unless(config('reservas.solicitudes_verificadas')===true&&config('reservas.clase_operacion')==='RESERVA',409,'Solicitudes de reserva no habilitadas.');
        $v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','motivo'=>'required|string|min:10|max:1000']);
        $obs=trim($v['motivo']);abort_if(mb_strlen($obs)<10,422,'Justificación insuficiente.');
        return response()->json(DB::transaction(function()use($r,$id,$v,$obs){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $h=DB::table('inventario_reservas')->where('id',$id)->where('sucursal_id',$sid)->lockForUpdate()->first();abort_unless($h,404);
            abort_unless($h->estado==='PENDIENTE'&&$h->vence_en===null&&(int)$h->nota_venta_id>0,409,'Reserva no disponible para aprobación.');
            $links=DB::table('inventario_reservas_operaciones')->where('reserva_id',$id)->lockForUpdate()->get();
            abort_unless($links->count()===1,409,'Vínculo de solicitud inválido.');
            $oid=(int)$links->first()->operacion_id;
            abort_unless(DB::table('inventario_reservas_operaciones')->where('operacion_id',$oid)->lockForUpdate()->get()->count()===1,409,'Operación compartida.');
            $o=DB::table('inventario_operaciones')->where('id',$oid)->where('sucursal_id',$sid)->lockForUpdate()->first();
            abort_unless($o&&$o->clase==='RESERVA'&&(int)$o->motivo_codigo_snapshot===710&&(int)$o->creado_por===(int)$h->creado_por&&(int)$o->creado_por!==$uid&&$o->confirmado_por===null&&$o->token_confirmacion===null&&$o->anulado_por===null,409,'Operación o aprobador inválido.');
            $approved=DB::table('modulos_estados as me')->join('modulos as m','m.id','=','me.modulo_id')->join('estados as e','e.id','=','me.estado_id')->where('m.codigo','INVENTARIO_OPERACION')->where('m.activo',1)->where('me.activo',1)->where('e.activo',1)->where('e.codigo','INV_APROBADA')->value('e.id');
            abort_unless($approved,409,'Estado aprobado no configurado.');
            $prior=DB::table('inventario_operaciones_historial')->where('operacion_id',$oid)->where('accion','APROBAR')->lockForUpdate()->get();
            if($prior->isNotEmpty()){
                $p=$prior->first();
                abort_unless($prior->count()===1&&(int)$o->estado_id===(int)$approved&&(int)$o->version===(int)$v['version']+1&&(int)$o->aprobado_por===$uid&&(int)$p->usuario_id===$uid&&(int)$p->estado_nuevo_id===(int)$approved&&$p->observaciones===$obs,409,'Aprobación previa incompatible.');
                return ['id'=>$id,'operacion_id'=>$oid,'estado'=>'PENDIENTE','estado_operacion'=>'INV_APROBADA','version'=>(int)$o->version,'repetida'=>true,'movimiento_aplicado'=>false];
            }
            abort_unless((int)$o->version===(int)$v['version']&&$o->aprobado_por===null,409,'Versión o aprobación previa incompatible.');
            [$items,$plans,$ops,$state]=$this->revisarSolicitud($h,$o,$sid,(int)$o->estado_id);
            abort_unless((int)$o->estado_id===(int)$state,409,'Operación no pendiente.');
            DB::table('inventario_operaciones')->where('id',$oid)->update(['estado_id'=>$approved,'version'=>(int)$o->version+1,'aprobado_por'=>$uid,'aprobado_en'=>now()]);
            DB::table('inventario_operaciones_historial')->insert(['operacion_id'=>$oid,'estado_anterior_id'=>$state,'estado_nuevo_id'=>$approved,'usuario_id'=>$uid,'accion'=>'APROBAR','observaciones'=>$obs]);
            DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>'RESERVA_APROBACION','tipo_entidad'=>'inventario_reservas','entidad_id'=>$id,'valores_anteriores'=>json_encode(['estado_operacion'=>'INV_PEND_APROBACION'],JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['estado_operacion'=>'INV_APROBADA','operacion_id'=>$oid,'motivo'=>$obs],JSON_THROW_ON_ERROR)]);
            return ['id'=>$id,'operacion_id'=>$oid,'estado'=>'PENDIENTE','estado_operacion'=>'INV_APROBADA','version'=>(int)$o->version+1,'repetida'=>false,'movimiento_aplicado'=>false];
        },3))->header('Cache-Control','no-store');
    }

    private function revisarSolicitud(object $h,object $o,int $sid,int $expectedState): array {
        $id=(int)$h->id;$oid=(int)$o->id;
            $noteId=(int)$h->nota_venta_id;
            $note=DB::table('notas_venta')->where('id',$noteId)->where('sucursal_id',$sid)->lockForUpdate()->first();
            abort_unless($note&&in_array($note->estado,['recibida','por_preparar'],true)&&$note->numero===$o->referencia_folio,409,'Referencia comercial modificada.');
            abort_unless(DB::table('inventario_reservas')->where('nota_venta_id',$noteId)->lockForUpdate()->get()->count()===1,409,'Nota con reservas duplicadas.');
            $details=DB::table('inventario_reservas_detalle')->where('reserva_id',$id)->orderBy('producto_id')->orderBy('bodega_id')->lockForUpdate()->get();
            abort_unless($details->count()>=1&&$details->count()<=50,409,'Detalle inválido.');
            $items=[];$seen=[];
            foreach($details as $l){
                $pid=(int)$l->producto_id;$bid=(int)$l->bodega_id;$key=$pid.':'.$bid;
                abort_unless($pid>0&&$bid>0&&!isset($seen[$key])&&BigDecimal::of((string)$l->cantidad_reservada)->isPositive()&&BigDecimal::of((string)$l->cantidad_despachada)->isZero()&&BigDecimal::of((string)$l->cantidad_liberada)->isZero(),409,'Detalle de reserva alterado.');
                $seen[$key]=true;$items[]=['producto_id'=>$pid,'bodega_id'=>$bid,'cantidad'=>(string)BigDecimal::of((string)$l->cantidad_reservada)->toScale(8)];
            }
            $audit=DB::table('registros_auditoria')->where('tipo_entidad','inventario_reservas')->where('entidad_id',$id)->where('accion','RESERVA_SOLICITUD')->lockForUpdate()->get();
            abort_unless($audit->count()===1&&(int)$audit->first()->usuario_id===(int)$h->creado_por,409,'Solicitud sin auditoría única.');
            $original=json_decode($audit->first()->valores_nuevos,true,512,JSON_THROW_ON_ERROR);
            abort_unless(($original['items']??null)===$items&&($original['nota_venta_id']??null)===$noteId&&($original['operacion_id']??null)===$oid&&($original['estado']??null)==='PENDIENTE',409,'Solicitud modificada.');
            $demand=[];
            $lines=DB::table('detalles_notas_venta as d')->join('presentaciones_productos as p','p.id','=','d.presentacion_id')->where('d.nota_venta_id',$noteId)->select('d.cantidad_solicitada','d.cantidad_entregada','p.producto_id','p.unidades_por_envase','p.activo')->lockForUpdate()->get();
            abort_unless($lines->isNotEmpty(),409,'Nota sin detalle.');
            foreach($lines as $l){
                // No inferir conversiones de presentaciones legadas: solo equivalencia unitaria.
                abort_unless((int)$l->activo===1&&BigDecimal::of((string)$l->unidades_por_envase)->isEqualTo(1)&&BigDecimal::of((string)$l->cantidad_entregada)->isZero(),409,'Presentación no unitaria o nota con entrega previa.');
                $q=BigDecimal::of((string)$l->cantidad_solicitada)->toScale(8);$pid=(int)$l->producto_id;
                abort_unless($pid>0&&$q->isPositive(),409,'Detalle comercial inválido.');
                $demand[$pid]=($demand[$pid]??BigDecimal::zero())->plus($q);
            }
            $m=DB::table('inventario_motivos as m')->join('inventario_grupos_motivos as g','g.id','=','m.grupo_id')->join('inventario_tipos_movimiento as t','t.id','=','m.tipo_movimiento_id')->where('m.codigo',710)->where('m.activo',1)->where('g.activo',1)->where('t.activo',1)->select('m.*','g.codigo as grupo_codigo')->lockForUpdate()->first();
            abort_unless($m,409,'Motivo no disponible.');
            $state=DB::table('modulos_estados as me')->join('modulos as m','m.id','=','me.modulo_id')->join('estados as e','e.id','=','me.estado_id')->where('m.codigo','INVENTARIO_OPERACION')->where('m.activo',1)->where('me.activo',1)->where('e.activo',1)->where('e.codigo','INV_PEND_APROBACION')->value('e.id');
            abort_unless($state,409,'Estado no configurado.');
            $used=[];$plans=[];
            foreach($items as $i){
                $pid=$i['producto_id'];$bid=$i['bodega_id'];$q=BigDecimal::of($i['cantidad']);
                $used[$pid]=($used[$pid]??BigDecimal::zero())->plus($q);
                abort_unless(isset($demand[$pid])&&$used[$pid]->isLessThanOrEqualTo($demand[$pid]),409,'Cantidad superior a la solicitada en nota de venta.');
                $p=DB::table('productos')->where('id',$pid)->where('activo',1)->lockForUpdate()->first();abort_unless($p,404,'Producto no disponible.');
                abort_if(DB::table('saldos_inventario as s')->join('presentaciones_productos as pp','pp.id','=','s.presentacion_id')->where('pp.producto_id',$pid)->where('s.cantidad','<>',0)->exists(),409,'Inventario legado pendiente.');
                abort_unless(DB::table('bodegas as b')->join('inventario_bodegas_sucursales as bs','bs.bodega_id','=','b.id')->where('b.id',$bid)->where('b.activo',1)->where('bs.sucursal_id',$sid)->lockForUpdate()->first(['b.id']),404,'Bodega no autorizada.');
                $stocks=DB::table('stock')->where('producto_id',$pid)->where('bodega_id',$bid)->lockForUpdate()->get();abort_unless($stocks->count()===1,409,'Stock no disponible.');
                $plans[]=(new PlanReservaInventario())->calcular($m,$i['cantidad'],$stocks->first(),(string)$p->costo_promedio_ponderado,'0');
            }

            abort_unless((int)$o->estado_id===$expectedState&&(int)$o->motivo_id===(int)$m->id&&$o->regla_snapshot===$m->regla&&$o->politica_cpp_snapshot===$m->politica_cpp,409,'Estado o motivo modificado.');
            $ops=DB::table('inventario_operaciones_detalle')->where('operacion_id',$oid)->lockForUpdate()->get();
            abort_unless($ops->count()===count($items),409,'Detalle de operación inconsistente.');
            foreach($items as $n=>$i){
                $p=$plans[$n];$matches=$ops->filter(fn($d)=>(int)$d->producto_id===$i['producto_id']&&(int)$d->bodega_id===$i['bodega_id']);$d=$matches->first();
                abort_unless($matches->count()===1&&$d->kardex_id===null&&$d->kardex_referencia_id===null&&!DB::table('kardex')->where('origen_tipo','AJUSTE_INVENTARIO')->where('origen_detalle_id',$d->id)->exists(),409,'Detalle ya ejecutado o duplicado.');
                foreach(['cantidad'=>'cantidad','delta_disponible'=>'delta_disponible','delta_comprometido'=>'delta_comprometido','delta_transito'=>'delta_transito','delta_fisico'=>'delta_fisico','costo_unitario_snapshot'=>'cpp_anterior'] as $field=>$key)abort_unless(BigDecimal::of((string)$d->$field)->isEqualTo($p[$key]),409,'Detalle o CPP modificado.');
            }

        return [$items,$plans,$ops,$state];
    }

    /** V109E3B2: confirmar reserva aprobada con reclasificación atómica. */
    public function confirmar(Request $r,int $id){
        $tipo=config('reservas.tipo_kardex_reservar');
        abort_unless(config('reservas.solicitudes_verificadas')===true&&config('reservas.clase_operacion')==='RESERVA'&&config('reservas.confirmacion_verificada')===true&&is_int($tipo)&&$tipo>0,409,'Confirmación de reservas no habilitada.');
        $v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','token_confirmacion'=>'required|uuid']);
        $token=strtolower($v['token_confirmacion']);
        return response()->json(DB::transaction(function()use($r,$id,$v,$token,$tipo){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $h=DB::table('inventario_reservas')->where('id',$id)->where('sucursal_id',$sid)->lockForUpdate()->first();abort_unless($h,404);
            $links=DB::table('inventario_reservas_operaciones')->where('reserva_id',$id)->lockForUpdate()->get();
            abort_unless($links->count()===1,409,'Vínculo de solicitud inválido.');
            $oid=(int)$links->first()->operacion_id;
            abort_unless(DB::table('inventario_reservas_operaciones')->where('operacion_id',$oid)->lockForUpdate()->get()->count()===1,409,'Operación compartida.');
            $o=DB::table('inventario_operaciones')->where('id',$oid)->where('sucursal_id',$sid)->lockForUpdate()->first();
            abort_unless($o&&$o->clase==='RESERVA'&&(int)$o->motivo_codigo_snapshot===710&&(int)$o->creado_por===(int)$h->creado_por&&(int)$o->aprobado_por>0&&(int)$o->aprobado_por!==(int)$o->creado_por&&(int)$o->aprobado_por===$uid&&$o->anulado_por===null,409,'Operación o confirmador inválido.');
            $state=function(string $code): int {
                $x=DB::table('modulos_estados as me')->join('modulos as m','m.id','=','me.modulo_id')->join('estados as e','e.id','=','me.estado_id')->where('m.codigo','INVENTARIO_OPERACION')->where('m.activo',1)->where('me.activo',1)->where('e.activo',1)->where('e.codigo',$code)->value('e.id');
                abort_unless($x,409,'Estado no configurado.');return (int)$x;
            };
            $approved=$state('INV_APROBADA');$done=$state('INV_CONFIRMADA');
            if((int)$o->estado_id===$done){
                abort_unless($h->estado==='ACTIVA'&&$o->token_confirmacion===$token&&(int)$o->confirmado_por===$uid&&(int)$o->version===(int)$v['version']+1,409,'Confirmación previa incompatible.');
                return ['id'=>$id,'operacion_id'=>$oid,'estado'=>'ACTIVA','estado_operacion'=>'INV_CONFIRMADA','version'=>(int)$o->version,'repetida'=>true,'movimiento_aplicado'=>true];
            }
            abort_unless($h->estado==='PENDIENTE'&&$h->vence_en===null&&(int)$o->estado_id===$approved&&(int)$o->version===(int)$v['version']&&$o->confirmado_por===null&&$o->token_confirmacion===null,409,'Solicitud no confirmable.');
            abort_if(DB::table('inventario_operaciones')->where('token_confirmacion',$token)->exists(),409,'Token ya utilizado.');
            $prior=DB::table('inventario_operaciones_historial')->where('operacion_id',$oid)->where('accion','APROBAR')->lockForUpdate()->get();
            abort_unless($prior->count()===1&&(int)$prior->first()->usuario_id===$uid&&(int)$prior->first()->estado_nuevo_id===$approved,409,'Aprobación no acreditada.');
            [$items,$plans,$ops]=$this->revisarSolicitud($h,$o,$sid,$approved);
            $conciliador=new \App\Services\ConciliadorComprometidoReservas();
            foreach($items as $i)$conciliador->verificar($i['producto_id'],$i['bodega_id'],$sid);
            foreach($items as $n=>$i){
                $pid=$i['producto_id'];$bid=$i['bodega_id'];$p=$plans[$n];$s=$p['anterior'];$after=$p['posterior'];
                $d=$ops->first(fn($d)=>(int)$d->producto_id===$pid&&(int)$d->bodega_id===$bid);
                $product=DB::table('productos')->where('id',$pid)->lockForUpdate()->first();
                $stock=DB::table('stock')->where('producto_id',$pid)->where('bodega_id',$bid)->lockForUpdate()->first();
                abort_unless($stock&&$product,409,'Stock no disponible.');
                foreach($s as $key=>$value)abort_unless(BigDecimal::of((string)$stock->$key)->isEqualTo($value),409,'Stock cambió durante confirmación.');
                $kid=DB::table('kardex')->insertGetId(['sucursal_id'=>$sid,'producto_id'=>$pid,'bodega_id'=>$bid,'tipo_documento_id'=>null,'folio_documento'=>$h->numero_interno,'tipo_movimiento'=>$tipo,'cantidad_anterior'=>$s['disponible'],'cantidad_documento'=>$p['cantidad'],'cantidad_actual'=>$after['disponible'],'transito_anterior'=>$s['transito'],'transito_actual'=>$after['transito'],'comprometido_anterior'=>$s['comprometido'],'comprometido_actual'=>$after['comprometido'],'fisico_anterior'=>$s['cantidad_fisica'],'fisico_actual'=>$after['cantidad_fisica'],'costo_unitario_neto'=>$p['cpp_anterior'],'ultimo_costo_anterior'=>$product->costo_neto,'ultimo_costo_actual'=>$product->costo_neto,'cpp_anterior'=>$p['cpp_anterior'],'cpp_actual'=>$p['cpp_actual'],'origen_tipo'=>'AJUSTE_INVENTARIO','origen_detalle_id'=>$d->id,'operacion_codigo'=>'RESERVAR','token_idempotencia'=>(string)\Illuminate\Support\Str::uuid(),'registrado_por'=>$uid,'observaciones'=>$o->justificacion]);
                DB::table('stock')->where('id',$stock->id)->update(['disponible'=>$after['disponible'],'comprometido'=>$after['comprometido']]);
                DB::table('inventario_operaciones_detalle')->where('id',$d->id)->update(['kardex_id'=>$kid]);
            }
            DB::table('inventario_operaciones')->where('id',$oid)->update(['estado_id'=>$done,'version'=>(int)$o->version+1,'confirmado_por'=>$uid,'confirmado_en'=>now(),'token_confirmacion'=>$token]);
            DB::table('inventario_reservas')->where('id',$id)->update(['estado'=>'ACTIVA']);
            foreach($items as $i)$conciliador->verificar($i['producto_id'],$i['bodega_id'],$sid);
            DB::table('inventario_operaciones_historial')->insert(['operacion_id'=>$oid,'estado_anterior_id'=>$approved,'estado_nuevo_id'=>$done,'usuario_id'=>$uid,'accion'=>'CONFIRMAR','observaciones'=>'Reserva confirmada; físico y CPP conservados.']);
            DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>'RESERVA_CONFIRMACION','tipo_entidad'=>'inventario_reservas','entidad_id'=>$id,'valores_anteriores'=>json_encode(['estado'=>'PENDIENTE'],JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['estado'=>'ACTIVA','operacion_id'=>$oid,'items'=>$items],JSON_THROW_ON_ERROR)]);
            return ['id'=>$id,'operacion_id'=>$oid,'estado'=>'ACTIVA','estado_operacion'=>'INV_CONFIRMADA','version'=>(int)$o->version+1,'repetida'=>false,'movimiento_aplicado'=>true];
        },3))->header('Cache-Control','no-store');
    }
}
