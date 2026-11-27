<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\ContextoCompras;
use App\Services\ConciliadorComprometidoReservas;
use App\Services\LectorSaldoReservaInventario;
use App\Services\PlanReservaInventario;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** V109E4A: solicitudes y ejecución de liberaciones, sin despacho ni vencimiento. */
final class LiberacionesReservaControlador extends Controller
{
    private function habilitado(): int {
        $tipo=config('reservas.tipo_kardex_liberar');
        abort_unless(config('reservas.solicitudes_verificadas')===true&&config('reservas.clase_operacion')==='RESERVA'&&config('reservas.confirmacion_verificada')===true&&config('reservas.liberacion_verificada')===true&&config('reservas.estado_liberada')==='LIBERADA'&&is_int($tipo)&&$tipo>0,409,'Liberaciones no habilitadas.');
        return $tipo;
    }
    private function estado(string $code): int {
        $x=DB::table('modulos_estados as me')->join('modulos as m','m.id','=','me.modulo_id')->join('estados as e','e.id','=','me.estado_id')->where('m.codigo','INVENTARIO_OPERACION')->where('m.activo',1)->where('me.activo',1)->where('e.activo',1)->where('e.codigo',$code)->value('e.id');
        abort_unless($x,409,'Estado no configurado.');return (int)$x;
    }
    private function cabecera(int $id,int $sid): object {
        $h=DB::table('inventario_reservas')->where('id',$id)->where('sucursal_id',$sid)->lockForUpdate()->first();abort_unless($h,404);
        abort_unless(in_array($h->estado,['ACTIVA','PARCIAL','LIBERADA'],true)&&(int)$h->nota_venta_id>0,409,'Reserva no liberable.');
        return $h;
    }
    private function items(array $rows): array {
        $out=[];
        foreach($rows as $i){
            $pid=(int)$i['producto_id'];$bid=(int)$i['bodega_id'];$q=BigDecimal::of((string)$i['cantidad'])->toScale(8);
            $key=$pid.':'.$bid;abort_unless($pid>0&&$bid>0&&$q->isPositive()&&!isset($out[$key]),422,'Detalle de liberación inválido.');
            $out[$key]=['producto_id'=>$pid,'bodega_id'=>$bid,'cantidad'=>(string)$q];
        }
        $out=array_values($out);usort($out,fn($a,$b)=>[$a['producto_id'],$a['bodega_id']]<=>[$b['producto_id'],$b['bodega_id']]);return $out;
    }
    private function revisar(object $h,int $sid,array $items,?int $own=null): array {
        abort_unless(in_array($h->estado,['ACTIVA','PARCIAL'],true),409,'Reserva ya liberada.');
        $note=DB::table('notas_venta')->where('id',$h->nota_venta_id)->where('sucursal_id',$sid)->lockForUpdate()->first();
        abort_unless($note&&is_string($note->numero)&&trim($note->numero)!==''&&mb_strlen($note->numero)<=60,409,'Referencia comercial no acreditada.');
        $links=DB::table('inventario_reservas_operaciones')->where('reserva_id',$h->id)->lockForUpdate()->get();
        $pending=$this->estado('INV_PEND_APROBACION');$approved=$this->estado('INV_APROBADA');$done=$this->estado('INV_CONFIRMADA');
        $alta=0;
        foreach($links as $link){
            $oid=(int)$link->operacion_id;
            abort_unless(DB::table('inventario_reservas_operaciones')->where('operacion_id',$oid)->lockForUpdate()->get()->count()===1,409,'Operación compartida.');
            $o=DB::table('inventario_operaciones')->where('id',$oid)->lockForUpdate()->first();
            abort_unless($o&&$o->clase==='RESERVA'&&(int)$o->sucursal_id===$sid&&$o->referencia_folio===$note->numero,409,'Vínculo comercial incompatible.');
            if((int)$o->motivo_codigo_snapshot===710){abort_unless((int)$o->estado_id===$done,409,'Alta no confirmada.');$alta++;}
            else{
                abort_unless((int)$o->motivo_codigo_snapshot===711,409,'Movimiento no soportado.');
                abort_if($oid!==$own&&in_array((int)$o->estado_id,[$pending,$approved],true),409,'Ya existe una liberación pendiente.');
            }
        }
        abort_unless($alta===1,409,'Alta no acreditada.');
        $m=DB::table('inventario_motivos as m')->join('inventario_grupos_motivos as g','g.id','=','m.grupo_id')->join('inventario_tipos_movimiento as t','t.id','=','m.tipo_movimiento_id')->where('m.codigo',711)->where('m.activo',1)->where('g.activo',1)->where('t.activo',1)->select('m.*','g.codigo as grupo_codigo')->lockForUpdate()->first();
        abort_unless($m,409,'Motivo no disponible.');
        $plans=[];$conc=new ConciliadorComprometidoReservas();
        // Conciliar todas las líneas antes de decidir estado de cabecera.
        $all=DB::table('inventario_reservas_detalle')->where('reserva_id',$h->id)->orderBy('producto_id')->orderBy('bodega_id')->lockForUpdate()->get();
        abort_unless($all->isNotEmpty()&&$all->count()<=50,409,'Detalle de reserva inválido.');
        foreach($all as $l)$conc->verificar((int)$l->producto_id,(int)$l->bodega_id,$sid);
        foreach($items as $i){
            $pid=$i['producto_id'];$bid=$i['bodega_id'];
            $p=DB::table('productos')->where('id',$pid)->lockForUpdate()->first();abort_unless($p,404);
            $saldo=(new LectorSaldoReservaInventario())->leer((int)$h->id,$pid,$bid,$sid);
            $s=DB::table('stock')->where('producto_id',$pid)->where('bodega_id',$bid)->lockForUpdate()->first();
            $plans[]=(new PlanReservaInventario())->calcular($m,$i['cantidad'],$s,(string)$p->costo_promedio_ponderado,$saldo['pendiente']);
        }
        return [$m,$plans,$note,$all];
    }
    private function historial(int $oid,?int $before,int $after,int $uid,string $action,string $obs): void {
        DB::table('inventario_operaciones_historial')->insert(['operacion_id'=>$oid,'estado_anterior_id'=>$before,'estado_nuevo_id'=>$after,'usuario_id'=>$uid,'accion'=>$action,'observaciones'=>$obs]);
        DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>'LIBERACION_'.$action,'tipo_entidad'=>'inventario_operaciones','entidad_id'=>$oid,'valores_anteriores'=>json_encode(['estado_id'=>$before],JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['estado_id'=>$after,'observaciones'=>$obs],JSON_THROW_ON_ERROR)]);
    }
    private function detalle(int $oid): array {
        $rows=DB::table('inventario_operaciones_detalle')->where('operacion_id',$oid)->orderBy('producto_id')->orderBy('bodega_id')->lockForUpdate()->get();
        abort_unless($rows->isNotEmpty()&&$rows->count()<=50,409,'Detalle inválido.');
        $items=$this->items($rows->map(fn($d)=>['producto_id'=>$d->producto_id,'bodega_id'=>$d->bodega_id,'cantidad'=>$d->cantidad])->all());
        return [$rows,$items];
    }
    public function crear(Request $r,int $id){
        $this->habilitado();
        $v=$r->validate(['items'=>'required|array|min:1|max:50','items.*.producto_id'=>'required|integer|min:1','items.*.bodega_id'=>'required|integer|min:1','items.*.cantidad'=>['required','string','regex:/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D'],'motivo'=>'required|string|min:10|max:1000','token_creacion'=>'required|uuid']);
        $obs=trim($v['motivo']);abort_if(mb_strlen($obs)<10,422,'Motivo insuficiente.');
        $items=$this->items($v['items']);$token=strtolower($v['token_creacion']);
        $out=DB::transaction(function()use($r,$id,$obs,$items,$token){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $h=$this->cabecera($id,$sid);
            $old=DB::table('inventario_operaciones')->where('token_creacion',$token)->lockForUpdate()->first();
            if($old){
                abort_unless($old->clase==='RESERVA'&&(int)$old->motivo_codigo_snapshot===711&&(int)$old->sucursal_id===$sid&&(int)$old->creado_por===$uid&&$old->justificacion===$obs,409,'Token incompatible.');
                $links=DB::table('inventario_reservas_operaciones')->where('operacion_id',$old->id)->lockForUpdate()->get();
                abort_unless($links->count()===1&&(int)$links->first()->reserva_id===$id,409,'Token de otra reserva.');
                [, $saved]=$this->detalle((int)$old->id);abort_unless($saved===$items,409,'Token de otro detalle.');
                return ['id'=>$id,'operacion_id'=>(int)$old->id,'repetida'=>true,'movimiento_aplicado'=>false];
            }
            [$m,$plans,$note]=$this->revisar($h,$sid,$items);
            $state=$this->estado('INV_PEND_APROBACION');
            $oid=DB::table('inventario_operaciones')->insertGetId(['clase'=>'RESERVA','sucursal_id'=>$sid,'motivo_id'=>$m->id,'estado_id'=>$state,'motivo_codigo_snapshot'=>711,'motivo_nombre_snapshot'=>$m->nombre,'regla_snapshot'=>$m->regla,'politica_cpp_snapshot'=>$m->politica_cpp,'justificacion'=>$obs,'referencia_folio'=>$note->numero,'revision_tributaria'=>'NO_APLICA','evaluacion_aviso_sii'=>'NO_APLICA','token_creacion'=>$token,'creado_por'=>$uid]);
            DB::table('inventario_operaciones')->where('id',$oid)->update(['numero_interno'=>'LIB-OP-'.str_pad((string)$oid,8,'0',STR_PAD_LEFT)]);
            DB::table('inventario_reservas_operaciones')->insert(['reserva_id'=>$id,'operacion_id'=>$oid]);
            foreach($items as $n=>$i){$p=$plans[$n];DB::table('inventario_operaciones_detalle')->insert(['operacion_id'=>$oid,'numero_linea'=>$n+1,'producto_id'=>$i['producto_id'],'bodega_id'=>$i['bodega_id'],'cantidad'=>$p['cantidad'],'delta_disponible'=>$p['delta_disponible'],'delta_comprometido'=>$p['delta_comprometido'],'delta_transito'=>$p['delta_transito'],'delta_fisico'=>$p['delta_fisico'],'costo_unitario_snapshot'=>$p['cpp_actual']]);}
            $this->historial((int)$oid,null,$state,$uid,'CREAR',$obs);
            DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>'LIBERACION_SOLICITUD','tipo_entidad'=>'inventario_operaciones','entidad_id'=>$oid,'valores_anteriores'=>json_encode([],JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['reserva_id'=>$id,'items'=>$items,'motivo'=>$obs],JSON_THROW_ON_ERROR)]);
            return ['id'=>$id,'operacion_id'=>(int)$oid,'repetida'=>false,'movimiento_aplicado'=>false];
        },3);
        return response()->json($out,$out['repetida']?200:201)->header('Cache-Control','no-store');
    }
    private function validarGuardado(object $h,object $o,int $sid): array {
        [$rows,$items]=$this->detalle((int)$o->id);
        $audit=DB::table('registros_auditoria')->where('tipo_entidad','inventario_operaciones')->where('entidad_id',$o->id)->where('accion','LIBERACION_SOLICITUD')->lockForUpdate()->get();
        abort_unless($audit->count()===1&&(int)$audit->first()->usuario_id===(int)$o->creado_por,409,'Solicitud sin auditoría única.');
        $original=json_decode($audit->first()->valores_nuevos,true,512,JSON_THROW_ON_ERROR);
        abort_unless(($original['reserva_id']??null)===(int)$h->id&&($original['items']??null)===$items&&($original['motivo']??null)===$o->justificacion,409,'Solicitud modificada.');
        [$m,$plans,$note,$all]=$this->revisar($h,$sid,$items,(int)$o->id);
        abort_unless((int)$o->motivo_id===(int)$m->id&&$o->regla_snapshot===$m->regla&&$o->politica_cpp_snapshot===$m->politica_cpp&&$o->referencia_folio===$note->numero,409,'Motivo o referencia modificados.');
        foreach($rows as $n=>$d){
            $p=$plans[$n];
            abort_unless($d->kardex_id===null&&$d->kardex_referencia_id===null&&!DB::table('kardex')->where('origen_tipo','AJUSTE_INVENTARIO')->where('origen_detalle_id',$d->id)->exists(),409,'Detalle ya ejecutado.');
            foreach(['cantidad'=>'cantidad','delta_disponible'=>'delta_disponible','delta_comprometido'=>'delta_comprometido','delta_transito'=>'delta_transito','delta_fisico'=>'delta_fisico','costo_unitario_snapshot'=>'cpp_actual'] as $field=>$key)abort_unless(BigDecimal::of((string)$d->$field)->isEqualTo($p[$key]),409,'Detalle o CPP modificado.');
        }
        return [$rows,$items,$plans,$all];
    }
    private function operacion(int $id,int $oid,int $sid): object {
        $links=DB::table('inventario_reservas_operaciones')->where('operacion_id',$oid)->lockForUpdate()->get();
        abort_unless($links->count()===1&&(int)$links->first()->reserva_id===$id,404);
        $o=DB::table('inventario_operaciones')->where('id',$oid)->where('sucursal_id',$sid)->lockForUpdate()->first();
        abort_unless($o&&$o->clase==='RESERVA'&&(int)$o->motivo_codigo_snapshot===711&&$o->anulado_por===null,409,'Operación no disponible.');return $o;
    }
    public function aprobar(Request $r,int $id,int $oid){
        $this->habilitado();$v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','motivo'=>'required|string|min:10|max:1000']);
        $obs=trim($v['motivo']);abort_if(mb_strlen($obs)<10,422,'Motivo insuficiente.');
        return response()->json(DB::transaction(function()use($r,$id,$oid,$v,$obs){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $h=$this->cabecera($id,$sid);$o=$this->operacion($id,$oid,$sid);
            $pending=$this->estado('INV_PEND_APROBACION');$approved=$this->estado('INV_APROBADA');
            abort_unless((int)$o->creado_por!==$uid&&$o->confirmado_por===null&&$o->token_confirmacion===null,409,'Aprobador incompatible.');
            if((int)$o->estado_id===$approved){
                $hist=DB::table('inventario_operaciones_historial')->where('operacion_id',$oid)->where('accion','APROBAR')->lockForUpdate()->get();
                abort_unless($hist->count()===1&&(int)$o->aprobado_por===$uid&&(int)$o->version===(int)$v['version']+1&&(int)$hist->first()->usuario_id===$uid&&$hist->first()->observaciones===$obs,409,'Aprobación incompatible.');
                return ['id'=>$id,'operacion_id'=>$oid,'version'=>(int)$o->version,'repetida'=>true,'movimiento_aplicado'=>false];
            }
            abort_unless((int)$o->estado_id===$pending&&(int)$o->version===(int)$v['version']&&$o->aprobado_por===null,409,'Estado o versión incompatible.');
            $this->validarGuardado($h,$o,$sid);
            DB::table('inventario_operaciones')->where('id',$oid)->update(['estado_id'=>$approved,'version'=>(int)$o->version+1,'aprobado_por'=>$uid,'aprobado_en'=>now()]);
            $this->historial($oid,$pending,$approved,$uid,'APROBAR',$obs);
            return ['id'=>$id,'operacion_id'=>$oid,'version'=>(int)$o->version+1,'repetida'=>false,'movimiento_aplicado'=>false];
        },3))->header('Cache-Control','no-store');
    }
    public function confirmar(Request $r,int $id,int $oid){
        $tipo=$this->habilitado();$v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','token_confirmacion'=>'required|uuid']);$token=strtolower($v['token_confirmacion']);
        return response()->json(DB::transaction(function()use($r,$id,$oid,$v,$tipo,$token){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $h=$this->cabecera($id,$sid);$o=$this->operacion($id,$oid,$sid);$approved=$this->estado('INV_APROBADA');$done=$this->estado('INV_CONFIRMADA');
            abort_unless((int)$o->aprobado_por===$uid&&(int)$o->creado_por!==$uid,409,'Confirmador incompatible.');
            if((int)$o->estado_id===$done){
                abort_unless($o->token_confirmacion===$token&&(int)$o->confirmado_por===$uid&&(int)$o->version===(int)$v['version']+1,409,'Confirmación incompatible.');
                return ['id'=>$id,'operacion_id'=>$oid,'estado'=>$h->estado,'repetida'=>true,'movimiento_aplicado'=>true];
            }
            abort_unless((int)$o->estado_id===$approved&&(int)$o->version===(int)$v['version']&&$o->confirmado_por===null&&$o->token_confirmacion===null,409,'Operación no confirmable.');
            abort_if(DB::table('inventario_operaciones')->where('token_confirmacion',$token)->exists(),409,'Token ya usado.');
            $hist=DB::table('inventario_operaciones_historial')->where('operacion_id',$oid)->where('accion','APROBAR')->lockForUpdate()->get();
            abort_unless($hist->count()===1&&(int)$hist->first()->usuario_id===$uid&&(int)$hist->first()->estado_nuevo_id===$approved,409,'Aprobación no acreditada.');
            [$rows,$items,$plans,$all]=$this->validarGuardado($h,$o,$sid);
            foreach($rows as $n=>$d){
                $p=$plans[$n];$s=$p['anterior'];$a=$p['posterior'];$pid=(int)$d->producto_id;$bid=(int)$d->bodega_id;
                $product=DB::table('productos')->where('id',$pid)->lockForUpdate()->first();
                $stock=DB::table('stock')->where('producto_id',$pid)->where('bodega_id',$bid)->lockForUpdate()->first();
                $l=$all->first(fn($l)=>(int)$l->producto_id===$pid&&(int)$l->bodega_id===$bid);abort_unless($l&&$stock&&$product,409,'Detalle no disponible.');
                foreach($s as $k=>$value)abort_unless(BigDecimal::of((string)$stock->$k)->isEqualTo($value),409,'Stock modificado.');
                $kid=DB::table('kardex')->insertGetId(['sucursal_id'=>$sid,'producto_id'=>$pid,'bodega_id'=>$bid,'tipo_documento_id'=>null,'folio_documento'=>$h->numero_interno,'tipo_movimiento'=>$tipo,'cantidad_anterior'=>$s['disponible'],'cantidad_documento'=>$p['cantidad'],'cantidad_actual'=>$a['disponible'],'transito_anterior'=>$s['transito'],'transito_actual'=>$a['transito'],'comprometido_anterior'=>$s['comprometido'],'comprometido_actual'=>$a['comprometido'],'fisico_anterior'=>$s['cantidad_fisica'],'fisico_actual'=>$a['cantidad_fisica'],'costo_unitario_neto'=>$p['cpp_actual'],'ultimo_costo_anterior'=>$product->costo_neto,'ultimo_costo_actual'=>$product->costo_neto,'cpp_anterior'=>$p['cpp_anterior'],'cpp_actual'=>$p['cpp_actual'],'origen_tipo'=>'AJUSTE_INVENTARIO','origen_detalle_id'=>$d->id,'operacion_codigo'=>'LIBERAR_RESERVA','token_idempotencia'=>(string)Str::uuid(),'registrado_por'=>$uid,'observaciones'=>$o->justificacion]);
                DB::table('stock')->where('id',$stock->id)->update(['disponible'=>$a['disponible'],'comprometido'=>$a['comprometido']]);
                DB::table('inventario_operaciones_detalle')->where('id',$d->id)->update(['kardex_id'=>$kid]);
                DB::table('inventario_reservas_detalle')->where('id',$l->id)->update(['cantidad_liberada'=>(string)BigDecimal::of((string)$l->cantidad_liberada)->plus($p['cantidad'])->toScale(8)]);
            }
            DB::table('inventario_operaciones')->where('id',$oid)->update(['estado_id'=>$done,'version'=>(int)$o->version+1,'confirmado_por'=>$uid,'confirmado_en'=>now(),'token_confirmacion'=>$token]);
            $fresh=DB::table('inventario_reservas_detalle')->where('reserva_id',$id)->orderBy('producto_id')->orderBy('bodega_id')->lockForUpdate()->get();
            $complete=$fresh->every(fn($l)=>BigDecimal::of((string)$l->cantidad_reservada)->isEqualTo((string)$l->cantidad_liberada));
            $state=$complete?'LIBERADA':'PARCIAL';
            DB::table('inventario_reservas')->where('id',$id)->update(['estado'=>$state]);
            $conc=new ConciliadorComprometidoReservas();foreach($fresh as $l)$conc->verificar((int)$l->producto_id,(int)$l->bodega_id,$sid);
            $this->historial($oid,$approved,$done,$uid,'CONFIRMAR','Liberación '.$state.'; físico y CPP conservados.');
            return ['id'=>$id,'operacion_id'=>$oid,'estado'=>$state,'repetida'=>false,'movimiento_aplicado'=>true];
        },3))->header('Cache-Control','no-store');
    }
}
