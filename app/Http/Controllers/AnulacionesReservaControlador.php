<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\ContextoCompras;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** V109E4B1: anula exclusivamente solicitudes no ejecutadas. */
final class AnulacionesReservaControlador extends Controller
{
    private function estado(string $code): int {
        $x=DB::table('modulos_estados as me')->join('modulos as m','m.id','=','me.modulo_id')->join('estados as e','e.id','=','me.estado_id')->where('m.codigo','INVENTARIO_OPERACION')->where('m.activo',1)->where('me.activo',1)->where('e.activo',1)->where('e.codigo',$code)->value('e.id');
        abort_unless($x,409,'Estado no configurado.');return (int)$x;
    }
    public function anular(Request $r,int $id,int $oid){
        abort_unless(config('reservas.solicitudes_verificadas')===true&&config('reservas.clase_operacion')==='RESERVA'&&config('reservas.anulacion_verificada')===true&&config('reservas.estado_anulada')==='ANULADA',409,'Anulaciones no habilitadas.');
        $v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','motivo'=>'required|string|min:10|max:1000']);
        $obs=trim($v['motivo']);abort_if(mb_strlen($obs)<10,422,'Motivo insuficiente.');
        return response()->json(DB::transaction(function()use($r,$id,$oid,$v,$obs){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $h=DB::table('inventario_reservas')->where('id',$id)->where('sucursal_id',$sid)->lockForUpdate()->first();abort_unless($h,404);
            $links=DB::table('inventario_reservas_operaciones')->where('operacion_id',$oid)->lockForUpdate()->get();
            abort_unless($links->count()===1&&(int)$links->first()->reserva_id===$id,404);
            $o=DB::table('inventario_operaciones')->where('id',$oid)->where('sucursal_id',$sid)->lockForUpdate()->first();
            abort_unless($o&&$o->clase==='RESERVA'&&in_array((int)$o->motivo_codigo_snapshot,[710,711],true),409,'Operación incompatible.');
            $alta=(int)$o->motivo_codigo_snapshot===710;
            $pending=$this->estado('INV_PEND_APROBACION');$approved=$this->estado('INV_APROBADA');$cancelled=$this->estado('INV_ANULADA');
            abort_unless($o->confirmado_por===null&&$o->token_confirmacion===null,409,'Operación ejecutada; requiere liberación.');
            $rows=DB::table('inventario_operaciones_detalle')->where('operacion_id',$oid)->lockForUpdate()->get();
            abort_unless($rows->isNotEmpty(),409,'Detalle ausente.');
            foreach($rows as $d)abort_unless($d->kardex_id===null&&$d->kardex_referencia_id===null&&!DB::table('kardex')->where('origen_tipo','AJUSTE_INVENTARIO')->where('origen_detalle_id',$d->id)->exists(),409,'Operación con Kardex.');
            if($alta){
                abort_unless(in_array($h->estado,['PENDIENTE','ANULADA'],true)&&DB::table('inventario_reservas_operaciones')->where('reserva_id',$id)->lockForUpdate()->get()->count()===1,409,'Reserva no anulable.');
                $lines=DB::table('inventario_reservas_detalle')->where('reserva_id',$id)->lockForUpdate()->get();
                abort_unless($lines->isNotEmpty()&&$lines->every(fn($l)=>BigDecimal::of((string)$l->cantidad_liberada)->isZero()&&BigDecimal::of((string)$l->cantidad_despachada)->isZero()),409,'Reserva con movimientos.');
            }else abort_unless(in_array($h->estado,['ACTIVA','PARCIAL','LIBERADA'],true),409,'Reserva incompatible.');
            if((int)$o->estado_id===$cancelled){
                abort_unless((int)$o->anulado_por===$uid&&$o->motivo_anulacion===$obs&&(int)$o->version===(int)$v['version']+1&&(!$alta||$h->estado==='ANULADA'),409,'Anulación previa incompatible.');
                return ['id'=>$id,'operacion_id'=>$oid,'estado'=>$h->estado,'repetida'=>true,'movimiento_aplicado'=>false];
            }
            abort_unless(in_array((int)$o->estado_id,[$pending,$approved],true)&&(int)$o->version===(int)$v['version']&&$o->anulado_por===null&&(!$alta||$h->estado==='PENDIENTE')&&($alta||$h->estado!=='LIBERADA'),409,'Estado o versión incompatible.');
            DB::table('inventario_operaciones')->where('id',$oid)->update(['estado_id'=>$cancelled,'version'=>(int)$o->version+1,'anulado_por'=>$uid,'anulado_en'=>now(),'motivo_anulacion'=>$obs]);
            $state=$alta?'ANULADA':$h->estado;
            if($alta){
                DB::table('inventario_reservas')->where('id',$id)->update(['estado'=>$state]);
                abort_unless(DB::table('inventario_reservas')->where('id',$id)->value('estado')===$state,409,'Estado de reserva no admitido.');
            }
            DB::table('inventario_operaciones_historial')->insert(['operacion_id'=>$oid,'estado_anterior_id'=>$o->estado_id,'estado_nuevo_id'=>$cancelled,'usuario_id'=>$uid,'accion'=>'ANULAR','observaciones'=>$obs]);
            DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>'RESERVA_ANULACION','tipo_entidad'=>'inventario_operaciones','entidad_id'=>$oid,'valores_anteriores'=>json_encode(['estado_id'=>$o->estado_id,'reserva_estado'=>$h->estado],JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['estado_id'=>$cancelled,'reserva_estado'=>$state,'motivo'=>$obs],JSON_THROW_ON_ERROR)]);
            return ['id'=>$id,'operacion_id'=>$oid,'estado'=>$state,'repetida'=>false,'movimiento_aplicado'=>false];
        },3))->header('Cache-Control','no-store');
    }
}
