<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** V109F5A: recuperación 410, cálculo puro sobre origen y base valorizada acreditados. */
final class PlanRecuperacionPerdida
{
    private function decimal(mixed $v): BigDecimal {
        abort_unless(is_string($v)||is_int($v),422,'Se requiere decimal exacto.');
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),422,'Decimal fuera de rango.');
        return BigDecimal::of($v)->toScale(8);
    }
    private function saldo(BigDecimal $v): string {
        abort_if($v->isNegative()||$v->isGreaterThan('99999999999999999999.99999999'),409,'Saldo fuera de rango.');
        return (string)$v->toScale(8);
    }
    public function calcular(object $m,mixed $cantidad,object $stock,mixed $cpp,mixed $baseValorizada,mixed $costoHistorico,mixed $perdida,mixed $recuperada): array {
        abort_unless((int)($m->codigo??0)===410&&(int)($m->grupo_codigo??0)===400
            &&($m->regla??null)==='RECUPERAR_PERDIDA'&&($m->naturaleza??null)==='ENTRADA'&&($m->politica_cpp??null)==='HISTORICO'
            &&(int)($m->activo??0)===1&&(int)($m->ajuste_rapido??-1)===0&&(int)($m->requiere_referencia??-1)===1
            &&(int)($m->requiere_aprobacion??-1)===1&&(int)($m->requiere_respaldo??-1)===1
            &&(int)($m->revision_tributaria??-1)===1&&(int)($m->evaluar_aviso_sii??-1)===0,409,'Motivo de recuperación incompatible.');
        $q=$this->decimal($cantidad);$cost=$this->decimal($costoHistorico);$current=$this->decimal($cpp);
        $base=$this->decimal($baseValorizada);$lost=$this->decimal($perdida);$done=$this->decimal($recuperada);
        abort_unless($q->isPositive(),422,'Cantidad positiva requerida.');
        abort_unless($cost->isPositive()&&$lost->isPositive()&&$done->isLessThanOrEqualTo($lost)&&$q->isLessThanOrEqualTo($lost->minus($done)),409,'Costo histórico o saldo de pérdida inválido.');
        $a=$this->decimal($stock->disponible??null);$t=$this->decimal($stock->transito??null);
        $c=$this->decimal($stock->comprometido??null);$f=$this->decimal($stock->cantidad_fisica??null);
        abort_unless($f->isEqualTo($a->plus($t)->plus($c))&&$base->isGreaterThanOrEqualTo($a->plus($c)),409,'Base valorizada o stock incoherente.');
        abort_if($base->isPositive()&&$current->isZero(),409,'CPP cero con inventario valorizable; requiere regularización.');
        $newBase=$base->plus($q);
        $round=defined(RoundingMode::class.'::HalfUp')?constant(RoundingMode::class.'::HalfUp'):constant(RoundingMode::class.'::HALF_UP');
        $newCpp=$base->multipliedBy($current)->plus($q->multipliedBy($cost))->dividedBy($newBase,8,$round);
        return ['motivo_codigo'=>410,'cantidad'=>(string)$q,'delta_disponible'=>(string)$q,'delta_fisico'=>(string)$q,
            'delta_transito'=>'0.00000000','delta_comprometido'=>'0.00000000',
            'disponible_anterior'=>(string)$a,'disponible_actual'=>$this->saldo($a->plus($q)),
            'fisico_anterior'=>(string)$f,'fisico_actual'=>$this->saldo($f->plus($q)),'transito'=>(string)$t,'comprometido'=>(string)$c,
            'base_valorizada_anterior'=>(string)$base,'base_valorizada_actual'=>$this->saldo($newBase),
            'cpp_anterior'=>(string)$current,'cpp_actual'=>$this->saldo($newCpp),'costo_unitario_snapshot'=>(string)$cost,
            'valor_movimiento'=>(string)$q->multipliedBy($cost)->toScale(16),'perdida_original'=>(string)$lost,
            'recuperada_anterior'=>(string)$done,'recuperada_actual'=>$this->saldo($done->plus($q)),
            'pendiente_actual'=>$this->saldo($lost->minus($done)->minus($q)),
            'requiere_aprobacion'=>true,'requiere_respaldo'=>true,'revision_tributaria'=>'PENDIENTE','evaluacion_aviso_sii'=>'NO_APLICA','solo_calculo'=>true];
    }
}
