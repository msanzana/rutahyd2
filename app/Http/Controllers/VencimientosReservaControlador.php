<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\ContextoCompras;
use App\Services\ConciliadorComprometidoReservas;
use App\Services\LectorSaldoReservaInventario;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/** V109E4B2: vencimiento manual, sujeto a la misma aprobación 711. */
final class VencimientosReservaControlador extends Controller
{
    private function habilitado(): void {
        abort_unless(config('reservas.vencimiento_verificado')===true&&config('reservas.solicitudes_verificadas')===true&&config('reservas.clase_operacion')==='RESERVA'&&config('reservas.confirmacion_verificada')===true&&config('reservas.liberacion_verificada')===true&&config('reservas.estado_liberada')==='LIBERADA',409,'Vencimientos no habilitados.');
    }
    public function programar(Request $r,int $id){
        $this->habilitado();
        $v=$r->validate(['vence_en'=>'required|date_format:Y-m-d H:i:s','motivo'=>'required|string|min:10|max:1000']);
        $obs=trim($v['motivo']);abort_if(mb_strlen($obs)<10,422,'Motivo insuficiente.');
        // Interpretación en zona horaria de aplicación verificada antes de activar.
        $date=Carbon::createFromFormat('Y-m-d H:i:s',$v['vence_en'],config('app.timezone'));
        abort_unless($date&&$date->format('Y-m-d H:i:s')===$v['vence_en'],422,'Fecha inválida.');
        return response()->json(DB::transaction(function()use($r,$id,$v,$obs,$date){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $h=DB::table('inventario_reservas')->where('id',$id)->where('sucursal_id',$sid)->lockForUpdate()->first();abort_unless($h,404);
            abort_unless(in_array($h->estado,['ACTIVA','PARCIAL'],true)&&$h->vence_en===null&&$date->gt(now()),409,'Fecha pasada, reserva no vigente o vencimiento ya programado.');
            $lines=DB::table('inventario_reservas_detalle')->where('reserva_id',$id)->orderBy('producto_id')->orderBy('bodega_id')->lockForUpdate()->get();
            abort_unless($lines->isNotEmpty()&&$lines->count()<=50,409,'Detalle inválido.');
            $sum=BigDecimal::zero();$conc=new ConciliadorComprometidoReservas();
            foreach($lines as $l){
                $conc->verificar((int)$l->producto_id,(int)$l->bodega_id,$sid);
                $sum=$sum->plus((new LectorSaldoReservaInventario())->leer($id,(int)$l->producto_id,(int)$l->bodega_id,$sid)['pendiente']);
            }
            abort_unless($sum->isPositive(),409,'Reserva sin pendiente.');
            DB::table('inventario_reservas')->where('id',$id)->update(['vence_en'=>$v['vence_en']]);
            DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>'RESERVA_VENCIMIENTO_PROGRAMADO','tipo_entidad'=>'inventario_reservas','entidad_id'=>$id,'valores_anteriores'=>json_encode(['vence_en'=>null],JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['vence_en'=>$v['vence_en'],'motivo'=>$obs],JSON_THROW_ON_ERROR)]);
            return ['id'=>$id,'vence_en'=>$v['vence_en'],'movimiento_aplicado'=>false];
        },3))->header('Cache-Control','no-store');
    }
    public function solicitar(Request $r,int $id){
        $this->habilitado();$v=$r->validate(['token_creacion'=>'required|uuid']);
        return DB::transaction(function()use($r,$id,$v){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $h=DB::table('inventario_reservas')->where('id',$id)->where('sucursal_id',$sid)->lockForUpdate()->first();abort_unless($h,404);
            abort_unless(in_array($h->estado,['ACTIVA','PARCIAL'],true)&&$h->vence_en!==null&&Carbon::parse($h->vence_en,config('app.timezone'))->lte(now()),409,'Reserva no vencida o no vigente.');
            $audit=DB::table('registros_auditoria')->where('tipo_entidad','inventario_reservas')->where('entidad_id',$id)->where('accion','RESERVA_VENCIMIENTO_PROGRAMADO')->lockForUpdate()->get();
            abort_unless($audit->count()===1&&(json_decode($audit->first()->valores_nuevos,true,512,JSON_THROW_ON_ERROR)['vence_en']??null)===$h->vence_en,409,'Vencimiento no acreditado.');
            $items=[];$lines=DB::table('inventario_reservas_detalle')->where('reserva_id',$id)->orderBy('producto_id')->orderBy('bodega_id')->lockForUpdate()->get();
            abort_unless($lines->isNotEmpty()&&$lines->count()<=50,409,'Detalle inválido.');
            foreach($lines as $l){
                $pid=(int)$l->producto_id;$bid=(int)$l->bodega_id;
                (new ConciliadorComprometidoReservas())->verificar($pid,$bid,$sid);
                $p=(new LectorSaldoReservaInventario())->leer($id,$pid,$bid,$sid)['pendiente'];
                if(BigDecimal::of($p)->isPositive())$items[]=['producto_id'=>$pid,'bodega_id'=>$bid,'cantidad'=>$p];
            }
            abort_unless($items!==[],409,'No hay cantidades pendientes.');
            // La solicitud 711 conserva el mismo JWT/contexto y requiere aprobación independiente.
            $r->merge(['items'=>$items,'motivo'=>'Liberación por vencimiento acreditado '.$h->vence_en,'token_creacion'=>$v['token_creacion']]);
            $response=app(LiberacionesReservaControlador::class)->crear($r,$id);
            $data=$response->getData(true);
            if(!$data['repetida'])DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>'RESERVA_VENCIMIENTO_SOLICITADO','tipo_entidad'=>'inventario_reservas','entidad_id'=>$id,'valores_anteriores'=>json_encode(['vence_en'=>$h->vence_en],JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['operacion_id'=>$data['operacion_id'],'items'=>$items],JSON_THROW_ON_ERROR)]);
            return $response;
        },3);
    }
}
