<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;

/** V109G2A: concilia devoluciones confirmadas de un mismo origen.
 * Recibe registros normalizados por un lector transaccional, no datos del cliente.
 * No prueba por sí solo la autenticidad de documentos o movimientos.
 */
final class SaldoDevolucionReferencia
{
    private function decimal(mixed $v): BigDecimal {
        abort_unless((is_string($v)||is_int($v))&&preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),409,'Cantidad documental inválida.');
        return BigDecimal::of($v)->toScale(8);
    }
    public function conciliar(int $sucursal,int $producto,int $kardex,string $cantidad,array $movimientos,array $codigosCompatibles): array {
        abort_unless($sucursal>0&&$producto>0&&$kardex>0&&$codigosCompatibles!==[]
            &&array_diff($codigosCompatibles,[601,602,603,604,605,606,607,608,609])===[],409,'Origen de devolución no definido.');
        $original=$this->decimal($cantidad);abort_unless($original->isPositive(),409,'Origen sin cantidad.');
        $aplicada=BigDecimal::zero()->toScale(8);$pendiente=BigDecimal::zero()->toScale(8);
        $seen=[];$seenK=[];$last=0;
        foreach($movimientos as $m){
            abort_unless(is_array($m)&&is_int($m['id']??null)&&$m['id']>0&&!isset($seen[$m['id']])
                &&($m['sucursal_id']??null)===$sucursal&&($m['producto_id']??null)===$producto&&($m['referencia_kardex_id']??null)===$kardex
                &&in_array($m['motivo_codigo']??null,$codigosCompatibles,true)&&($m['referencia_acreditada']??null)===true,409,'Vínculo de devolución incompatible.');
            $seen[$m['id']]=true;$n=$this->decimal($m['cantidad']??null);
            abort_unless($n->isPositive(),409,'Cantidad de devolución inválida.');
            $state=$m['estado']??null;$movement=$m['kardex_id']??null;
            if($state==='INV_CONFIRMADA'){
                abort_unless(is_int($movement)&&$movement>$kardex&&$movement>$last&&!isset($seenK[$movement])
                    &&is_int($m['creado_por']??null)&&$m['creado_por']>0&&is_int($m['aprobado_por']??null)&&$m['aprobado_por']>0
                    &&$m['creado_por']!==$m['aprobado_por']&&($m['movimiento_acreditado']??null)===true,409,'Devolución confirmada no acreditada.');
                $seenK[$movement]=true;$last=$movement;$aplicada=$aplicada->plus($n);
            }elseif(in_array($state,['INV_PEND_APROBACION','INV_APROBADA'],true)){
                abort_unless($movement===null&&($m['movimiento_acreditado']??null)===false,409,'Solicitud pendiente con movimiento.');
                $pendiente=$pendiente->plus($n);
            }elseif($state==='INV_ANULADA'){
                abort_unless($movement===null&&($m['movimiento_acreditado']??null)===false,409,'Solicitud anulada con movimiento.');
            }else abort(409,'Estado documental no conciliable.');
            abort_unless($aplicada->plus($pendiente)->isLessThanOrEqualTo($original),409,'Devoluciones exceden cantidad original.');
        }
        return ['original'=>(string)$original,'aplicada'=>(string)$aplicada,'solicitada_pendiente'=>(string)$pendiente,
            'disponible_para_solicitar'=>(string)$original->minus($aplicada)->minus($pendiente),
            'saldo_fisico_pendiente'=>(string)$original->minus($aplicada),'solo_conciliacion'=>true];
    }
}
