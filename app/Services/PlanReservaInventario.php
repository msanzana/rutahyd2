<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;

/** V109E1. Plan puro de reserva y liberación; no ejecuta ni acredita referencias. */
final class PlanReservaInventario
{
    private function decimal(mixed $v): BigDecimal {
        abort_unless(is_int($v)||is_string($v),422,'Se requiere un decimal exacto.');
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),422,'Cantidad o costo fuera de formato.');
        return BigDecimal::of($v)->toScale(8);
    }
    public function calcular(object $motivo, mixed $cantidad, object $stock, mixed $cpp, mixed $pendientePropio): array {
        $code=(int)($motivo->codigo??0);
        $rule=match($code){710=>'RESERVAR',711=>'LIBERAR_RESERVA',default=>null};
        abort_unless($rule!==null&&(int)($motivo->activo??0)===1&&(int)($motivo->grupo_codigo??0)===700
            &&($motivo->regla??null)===$rule&&($motivo->politica_cpp??null)==='CONSERVAR'
            &&(int)($motivo->ajuste_rapido??-1)===0&&(int)($motivo->requiere_referencia??-1)===1
            &&(int)($motivo->requiere_aprobacion??-1)===1&&(int)($motivo->requiere_respaldo??-1)===0
            &&(int)($motivo->revision_tributaria??-1)===0&&(int)($motivo->evaluar_aviso_sii??-1)===0,409,'Motivo no coincide con el catálogo de reservas.');
        $q=$this->decimal($cantidad);$cost=$this->decimal($cpp);$pending=$this->decimal($pendientePropio);
        abort_unless($q->isPositive(),422,'Ingrese cantidad positiva.');
        $s=[];
        foreach(['disponible','transito','comprometido','cantidad_fisica'] as $key)$s[$key]=$this->decimal($stock->$key??null);
        abort_unless($s['cantidad_fisica']->isEqualTo($s['disponible']->plus($s['transito'])->plus($s['comprometido'])),409,'Saldo físico inconsistente.');
        abort_unless($pending->isLessThanOrEqualTo($s['comprometido']),409,'Reserva propia superior al comprometido agregado.');
        if($code===710){
            // Alta inicial; incrementos de una reserva existente requieren contrato propio.
            abort_unless($pending->isZero()&&$q->isLessThanOrEqualTo($s['disponible']),409,'Reserva existente o disponible insuficiente.');
            $delta=$q->negated();$remaining=$q;
        }else{
            // El llamador deberá acreditar este pendiente con la reserva y sus movimientos,
            // nunca deducirlo solamente del saldo comprometido global.
            abort_unless($q->isLessThanOrEqualTo($pending),409,'La liberación excede el saldo de la reserva propia.');
            $delta=$q;$remaining=$pending->minus($q);
        }
        $a=$s['disponible']->plus($delta);$c=$s['comprometido']->minus($delta);
        $max='99999999999999999999.99999999';
        abort_if($a->isNegative()||$c->isNegative()||$a->isGreaterThan($max)||$c->isGreaterThan($max),409,'Saldo fuera de rango.');
        abort_unless($a->plus($c)->isEqualTo($s['disponible']->plus($s['comprometido'])),409,'Base valorizada alterada.');
        return [
            'motivo_codigo'=>$code,'regla'=>$rule,'cantidad'=>(string)$q,
            'delta_disponible'=>(string)$delta,'delta_comprometido'=>(string)$delta->negated(),
            'delta_transito'=>'0.00000000','delta_fisico'=>'0.00000000',
            'pendiente_anterior'=>(string)$pending,'pendiente_actual'=>(string)$remaining,
            'anterior'=>array_map(fn($v)=>(string)$v,$s),
            'posterior'=>['disponible'=>(string)$a,'transito'=>(string)$s['transito'],'comprometido'=>(string)$c,'cantidad_fisica'=>(string)$s['cantidad_fisica']],
            'cpp_anterior'=>(string)$cost,'cpp_actual'=>(string)$cost,
            'requiere_referencia'=>true,'requiere_aprobacion'=>true,'requiere_respaldo'=>false,
            'revision_tributaria'=>'NO_APLICA','evaluacion_aviso_sii'=>'NO_APLICA','solo_calculo'=>true,
        ];
    }
}
