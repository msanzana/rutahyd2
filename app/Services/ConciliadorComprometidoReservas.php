<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/** V109E3B1: conciliación estricta del comprometido por SKU/bodega. */
final class ConciliadorComprometidoReservas
{
    public function verificar(int $producto,int $bodega,int $sucursal): array {
        abort_unless(DB::transactionLevel()>0,500,'Conciliación fuera de transacción.');
        abort_unless(min($producto,$bodega,$sucursal)>0,409,'Identidad incompleta.');
        abort_unless(DB::table('productos')->where('id',$producto)->lockForUpdate()->first(),404,'Producto inexistente.');
        abort_unless(DB::table('inventario_bodegas_sucursales')->where('bodega_id',$bodega)->where('sucursal_id',$sucursal)->lockForUpdate()->first(),404,'Bodega no autorizada.');
        $stocks=DB::table('stock')->where('producto_id',$producto)->where('bodega_id',$bodega)->lockForUpdate()->get();
        abort_unless($stocks->count()===1,409,'Stock ausente o duplicado.');
        $stock=$stocks->first();$committed=BigDecimal::of((string)$stock->comprometido)->toScale(8);
        $sum=BigDecimal::zero()->toScale(8);$ids=[];$seen=[];
        $lines=DB::table('inventario_reservas_detalle as d')->leftJoin('inventario_reservas as r','r.id','=','d.reserva_id')
            ->where('d.producto_id',$producto)->where('d.bodega_id',$bodega)->orderBy('d.reserva_id')->orderBy('d.id')
            ->select('d.*','r.estado as estado_reserva','r.sucursal_id as sucursal_reserva')->lockForUpdate()->get();
        foreach($lines as $line){
            $id=(int)$line->reserva_id;
            abort_unless($id>0&&!isset($seen[$id])&&(int)$line->sucursal_reserva===$sucursal,409,'Reserva duplicada o no atribuida.');
            $seen[$id]=true;
            $q=BigDecimal::of((string)$line->cantidad_reservada)->toScale(8);
            $released=BigDecimal::of((string)$line->cantidad_liberada)->toScale(8);
            $sent=BigDecimal::of((string)$line->cantidad_despachada)->toScale(8);
            abort_unless($q->isPositive()&&!$released->isNegative()&&$released->isLessThanOrEqualTo($q)&&$sent->isZero(),409,'Detalle de reserva no conciliable.');
            if($line->estado_reserva==='PENDIENTE'){
                abort_unless($released->isZero(),409,'Solicitud pendiente con liberaciones.');
                $links=DB::table('inventario_reservas_operaciones')->where('reserva_id',$id)->lockForUpdate()->get();
                abort_unless($links->count()===1,409,'Solicitud pendiente sin vínculo único.');
                $oid=(int)$links->first()->operacion_id;
                abort_unless(DB::table('inventario_reservas_operaciones')->where('operacion_id',$oid)->lockForUpdate()->get()->count()===1,409,'Operación compartida.');
                $o=DB::table('inventario_operaciones as o')->join('estados as e','e.id','=','o.estado_id')->where('o.id',$oid)->select('o.*','e.codigo as estado_codigo')->lockForUpdate()->first();
                abort_unless($o&&$o->clase==='RESERVA'&&(int)$o->sucursal_id===$sucursal&&(int)$o->motivo_codigo_snapshot===710&&in_array($o->estado_codigo,['INV_PEND_APROBACION','INV_APROBADA'],true)&&$o->confirmado_por===null&&$o->token_confirmacion===null,409,'Solicitud pendiente con ejecución incompatible.');
                $details=DB::table('inventario_operaciones_detalle')->where('operacion_id',$oid)->lockForUpdate()->get();
                $matching=$details->filter(fn($d)=>(int)$d->producto_id===$producto&&(int)$d->bodega_id===$bodega);
                abort_unless($matching->count()===1&&BigDecimal::of((string)$matching->first()->cantidad)->isEqualTo($q),409,'Detalle pendiente incompatible.');
                foreach($details as $d)abort_unless($d->kardex_id===null&&!DB::table('kardex')->where('origen_tipo','AJUSTE_INVENTARIO')->where('origen_detalle_id',$d->id)->exists(),409,'Solicitud pendiente con Kardex.');
                continue;
            }
            // Estados terminales y consumo se incorporarán al acreditar el ENUM completo.
            abort_unless(in_array($line->estado_reserva,['ACTIVA','PARCIAL'],true),409,'Estado histórico de reserva aún no conciliable.');
            $r=(new LectorSaldoReservaInventario())->leer($id,$producto,$bodega,$sucursal);
            $sum=$sum->plus($r['pendiente']);$ids[]=$id;
        }
        abort_unless($sum->isEqualTo($committed),409,'Stock comprometido no coincide con las reservas acreditadas.');
        return ['producto_id'=>$producto,'bodega_id'=>$bodega,'sucursal_id'=>$sucursal,'comprometido'=>(string)$committed,'pendiente_acreditado'=>(string)$sum,'reservas'=>$ids];
    }
}
