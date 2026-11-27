<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/** V109E2B1: lector de saldo de reserva vigente, sin escrituras ni autorización comercial. */
final class LectorSaldoReservaInventario
{
    private function decimal(mixed $v): BigDecimal {
        abort_unless(is_string($v)||is_int($v),409,'Decimal no exacto.');
        abort_unless(preg_match('/^-?(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),409,'Decimal fuera de formato.');
        return BigDecimal::of($v)->toScale(8);
    }
    public function leer(int $reserva,int $producto,int $bodega,int $sucursal): array {
        abort_unless(DB::transactionLevel()>0,500,'Lectura de reserva fuera de transacción.');
        abort_unless(min($reserva,$producto,$bodega,$sucursal)>0,409,'Identidad incompleta.');
        // Mismo bloqueo de producto que usan los escritores de inventario.
        abort_unless(DB::table('productos')->where('id',$producto)->lockForUpdate()->first(),404,'Producto inexistente.');
        $h=DB::table('inventario_reservas')->where('id',$reserva)->where('sucursal_id',$sucursal)->lockForUpdate()->first();
        abort_unless($h,404,'Reserva no disponible.');
        abort_unless(in_array($h->estado,['ACTIVA','PARCIAL'],true)&&(int)$h->nota_venta_id>0,409,'Reserva no vigente o sin referencia.');
        abort_unless(DB::table('inventario_bodegas_sucursales')->where('sucursal_id',$sucursal)->where('bodega_id',$bodega)->lockForUpdate()->first(),404,'Bodega no autorizada.');
        $lines=DB::table('inventario_reservas_detalle')->where('reserva_id',$reserva)->where('producto_id',$producto)->where('bodega_id',$bodega)->lockForUpdate()->get();
        abort_unless($lines->count()===1,409,'Detalle de reserva ausente o duplicado.');$l=$lines->first();
        $reserved=$this->decimal($l->cantidad_reservada);$released=$this->decimal($l->cantidad_liberada);
        abort_unless($reserved->isPositive()&&!$released->isNegative()&&$released->isLessThanOrEqualTo($reserved)&&$this->decimal($l->cantidad_despachada)->isZero(),409,'Detalle incompatible; despacho aún no soportado.');
        $stocks=DB::table('stock')->where('producto_id',$producto)->where('bodega_id',$bodega)->lockForUpdate()->get();
        abort_unless($stocks->count()===1,409,'Stock inexistente o duplicado.');$stock=$stocks->first();
        $a=$this->decimal($stock->disponible);$t=$this->decimal($stock->transito);$c=$this->decimal($stock->comprometido);$f=$this->decimal($stock->cantidad_fisica);
        abort_unless(!$a->isNegative()&&!$t->isNegative()&&!$c->isNegative()&&$f->isEqualTo($a->plus($t)->plus($c)),409,'Stock inconsistente.');
        $links=DB::table('inventario_reservas_operaciones')->where('reserva_id',$reserva)->orderBy('operacion_id')->lockForUpdate()->get();
        $rows=[];
        foreach($links as $link){
            $oid=(int)$link->operacion_id;
            $owners=DB::table('inventario_reservas_operaciones')->where('operacion_id',$oid)->lockForUpdate()->get();
            abort_unless($owners->count()===1,409,'Operación compartida entre reservas.');
            $o=DB::table('inventario_operaciones as o')->leftJoin('estados as e','e.id','=','o.estado_id')->where('o.id',$oid)->select('o.*','e.codigo as estado_codigo')->lockForUpdate()->first();
            $code=(int)($o->motivo_codigo_snapshot??0);$rule=match($code){710=>'RESERVAR',711=>'LIBERAR_RESERVA',default=>null};
            // RESERVA es contrato candidato, no confirmado en ENUM productivo.
            abort_unless($o&&$rule&&$o->clase==='RESERVA'&&(int)$o->sucursal_id===$sucursal&&$o->regla_snapshot===$rule&&$o->politica_cpp_snapshot==='CONSERVAR',409,'Operación de reserva incompatible.');
            $details=DB::table('inventario_operaciones_detalle')->where('operacion_id',$oid)->where('producto_id',$producto)->where('bodega_id',$bodega)->lockForUpdate()->get();
            abort_unless($details->count()<=1,409,'Detalle de operación duplicado.');
            if($details->isEmpty())continue;
            $d=$details->first();
            if(in_array($o->estado_codigo,['INV_BORRADOR','INV_PEND_APROBACION','INV_APROBADA','INV_ANULADA'],true)){
                abort_unless($d->kardex_id===null&&$o->confirmado_por===null&&$o->token_confirmacion===null&&!DB::table('kardex')->where('origen_tipo','AJUSTE_INVENTARIO')->where('origen_detalle_id',$d->id)->exists(),409,'Operación no confirmada con movimiento.');
                continue;
            }
            abort_unless($o->estado_codigo==='INV_CONFIRMADA'&&(int)$o->confirmado_por>0&&$o->token_confirmacion!==null&&(int)$d->kardex_id>0,409,'Confirmación de reserva no acreditada.');
            $k=DB::table('kardex')->where('id',$d->kardex_id)->lockForUpdate()->first();
            $q=$this->decimal($d->cantidad);$cost=$this->decimal($d->costo_unitario_snapshot);
            abort_unless($q->isPositive()&&!$cost->isNegative()&&$k&&(int)$k->producto_id===$producto&&(int)$k->bodega_id===$bodega&&(int)$k->sucursal_id===$sucursal&&$k->origen_tipo==='AJUSTE_INVENTARIO'&&(int)$k->origen_detalle_id===(int)$d->id&&$k->operacion_codigo===$rule&&$this->decimal($k->cantidad_documento)->isEqualTo($q)&&$this->decimal($k->costo_unitario_neto)->isEqualTo($cost)&&$this->decimal($k->cpp_anterior)->isEqualTo($cost)&&$this->decimal($k->cpp_actual)->isEqualTo($cost),409,'Kardex de reserva no acreditado.');
            $row=['reserva_id'=>$reserva,'producto_id'=>$producto,'bodega_id'=>$bodega,'sucursal_id'=>$sucursal,'operacion_id'=>$oid,'kardex_id'=>(int)$k->id,'estado_operacion'=>$o->estado_codigo,'creado_por'=>(int)$o->creado_por,'aprobado_por'=>(int)$o->aprobado_por,'motivo_codigo'=>$code,'cantidad'=>(string)$q];
            foreach(['disponible'=>['cantidad_anterior','cantidad_actual'],'comprometido'=>['comprometido_anterior','comprometido_actual'],'transito'=>['transito_anterior','transito_actual'],'fisico'=>['fisico_anterior','fisico_actual']] as $field=>[$before,$after]){
                $key='delta_'.$field;$delta=$this->decimal($d->$key);
                abort_unless($this->decimal($k->$after)->minus($this->decimal($k->$before))->isEqualTo($delta),409,'Variación Kardex no acreditada.');
                $row[$key]=(string)$delta;
            }
            $rows[]=$row;
        }
        usort($rows,fn($a,$b)=>$a['kardex_id']<=>$b['kardex_id']);
        $result=(new SaldoReservaInventario())->conciliar($reserva,$producto,$bodega,$sucursal,$rows,(string)$c);
        abort_unless($reserved->isEqualTo($result['reservada'])&&$released->isEqualTo($result['liberada']),409,'Cantidades registradas sin respaldo de movimientos.');
        return $result+['nota_venta_id'=>(int)$h->nota_venta_id,'referencia_comercial_verificada'=>false];
    }
}
