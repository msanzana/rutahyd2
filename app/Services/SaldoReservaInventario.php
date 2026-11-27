<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;

/**
 * V109E2A. Conciliar saldo propio de una línea de reserva desde movimientos
 * normalizados por un futuro lector transaccional. No consulta ni escribe BD.
 * El lector debe acreditar los vínculos de reserva, operaciones, Kardex y
 * referencia comercial: esta clase no convierte datos de cliente en evidencia.
 */
final class SaldoReservaInventario
{
    private function cantidad(mixed $value): BigDecimal {
        abort_unless(is_string($value)||is_int($value),409,'Cantidad no exacta.');
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$value),409,'Cantidad fuera de formato.');
        return BigDecimal::of($value)->toScale(8);
    }
    public function conciliar(int $reserva,int $producto,int $bodega,int $sucursal,array $movimientos,mixed $comprometido,bool $permitirConsumo=false): array {
        abort_unless(min($reserva,$producto,$bodega,$sucursal)>0,409,'Identidad de reserva incompleta.');
        $total=$this->cantidad($comprometido);
        $alta=BigDecimal::of('0')->toScale(8);$liberada=$alta;$despachada=$alta;$pendiente=$alta;
        $seen=[];$kardex=[];$last=0;
        foreach($movimientos as $m){
            abort_unless(is_array($m)&&($m['reserva_id']??null)===$reserva
                &&($m['producto_id']??null)===$producto&&($m['bodega_id']??null)===$bodega
                &&($m['sucursal_id']??null)===$sucursal,409,'Movimiento de otra reserva, producto, bodega o sucursal.');
            $op=$m['operacion_id']??null;$k=$m['kardex_id']??null;
            abort_unless(is_int($op)&&$op>0&&!isset($seen[$op])&&is_int($k)&&$k>$last&&!isset($kardex[$k]),409,'Movimientos repetidos o sin orden Kardex.');
            abort_unless(($m['estado_operacion']??null)==='INV_CONFIRMADA',409,'Movimiento no confirmado.');
            $creator=$m['creado_por']??null;$approver=$m['aprobado_por']??null;
            abort_unless(is_int($creator)&&$creator>0&&is_int($approver)&&$approver>0&&$creator!==$approver,409,'Aprobación independiente no acreditada.');
            $q=$this->cantidad($m['cantidad']??null);
            abort_unless($q->isPositive(),409,'Movimiento sin cantidad positiva.');
            $code=$m['motivo_codigo']??null;
            $fisico='0.00000000';
            if($code===710){
                abort_unless($alta->isZero(),409,'Alta de reserva duplicada.');
                $alta=$q;$pendiente=$q;$delta=$q->negated();
            }elseif($code===711){
                abort_unless($alta->isPositive()&&$q->isLessThanOrEqualTo($pendiente),409,'Liberación superior a la reserva.');
                $liberada=$liberada->plus($q);$pendiente=$pendiente->minus($q);$delta=$q;
            }elseif($code===712&&$permitirConsumo){
                abort_unless($alta->isPositive()&&$q->isLessThanOrEqualTo($pendiente),409,'Despacho superior a la reserva.');
                $despachada=$despachada->plus($q);$pendiente=$pendiente->minus($q);$delta=BigDecimal::zero()->toScale(8);$fisico=(string)$q->negated();
            }else{
                abort(409,'Movimiento de reserva no soportado; requiere conciliación específica.');
            }
            // Los deltas de la operación normalizada deben coincidir con el Kardex acreditado.
            foreach(['delta_disponible'=>(string)$delta,'delta_comprometido'=>$code===712?(string)$q->negated():(string)$delta->negated(),'delta_transito'=>'0.00000000','delta_fisico'=>$fisico] as $key=>$expected){
                abort_unless(isset($m[$key])&&is_string($m[$key])&&$m[$key]===$expected,409,'Deltas de reserva inconsistentes.');
            }
            $seen[$op]=true;$kardex[$k]=true;$last=$k;
        }
        abort_unless($pendiente->isLessThanOrEqualTo($total),409,'Comprometido agregado inferior a la reserva acreditada.');
        return ['reserva_id'=>$reserva,'producto_id'=>$producto,'bodega_id'=>$bodega,'sucursal_id'=>$sucursal,
            'reservada'=>(string)$alta,'liberada'=>(string)$liberada,'despachada'=>(string)$despachada,'pendiente'=>(string)$pendiente,
            'operaciones'=>array_keys($seen),'ultimo_kardex_id'=>$last?:null,'solo_calculo'=>true];
    }
}
