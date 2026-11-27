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
}
