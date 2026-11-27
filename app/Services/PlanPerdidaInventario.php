<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;

/** V109F1: cálculo de pérdidas a CPP vigente. No acredita referencias ni emite avisos. */
final class PlanPerdidaInventario
{
    public const MERMAS_SIMPLES=[301,302,303,304];
    private function decimal(mixed $v): BigDecimal {
        abort_unless(is_string($v)||is_int($v),422,'Se requiere cantidad exacta.');
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),422,'Decimal fuera de rango.');
        return BigDecimal::of($v)->toScale(8);
    }
    public function calcular(object $m,mixed $cantidad,object $stock,mixed $cpp): array {
        $code=(int)($m->codigo??0);
        abort_unless(in_array($code,self::MERMAS_SIMPLES,true)&&(int)($m->grupo_codigo??0)===300
            &&($m->regla??null)==='MERMA'&&($m->naturaleza??null)==='SALIDA'&&($m->politica_cpp??null)==='VIGENTE'
            &&(int)($m->activo??0)===1&&(int)($m->ajuste_rapido??-1)===0
            &&(int)($m->requiere_referencia??-1)===0&&(int)($m->requiere_aprobacion??-1)===1
            &&(int)($m->requiere_respaldo??-1)===1&&(int)($m->revision_tributaria??-1)===1
            &&(int)($m->evaluar_aviso_sii??-1)===0,409,'Motivo de pérdida no acreditado para este flujo.');
        $q=$this->decimal($cantidad);$cost=$this->decimal($cpp);
        abort_unless($q->isPositive(),422,'Cantidad positiva requerida.');
        abort_unless($cost->isPositive(),409,'CPP vigente no valorizado.');
        $a=$this->decimal($stock->disponible??null);$t=$this->decimal($stock->transito??null);
        $c=$this->decimal($stock->comprometido??null);$f=$this->decimal($stock->cantidad_fisica??null);
        abort_unless($f->isEqualTo($a->plus($t)->plus($c))&&$q->isLessThanOrEqualTo($a),409,'Stock incoherente o disponible insuficiente.');
        return ['motivo_codigo'=>$code,'cantidad'=>(string)$q,'delta_disponible'=>(string)$q->negated(),'delta_fisico'=>(string)$q->negated(),
            'delta_transito'=>'0.00000000','delta_comprometido'=>'0.00000000','disponible_anterior'=>(string)$a,'disponible_actual'=>(string)$a->minus($q),
            'fisico_anterior'=>(string)$f,'fisico_actual'=>(string)$f->minus($q),'transito'=>(string)$t,'comprometido'=>(string)$c,
            'cpp_anterior'=>(string)$cost,'cpp_actual'=>(string)$cost,'costo_unitario_snapshot'=>(string)$cost,
            'valor_movimiento'=>(string)$q->multipliedBy($cost)->toScale(16),'requiere_respaldo'=>true,'requiere_aprobacion'=>true,
            'revision_tributaria'=>'PENDIENTE','evaluacion_aviso_sii'=>'NO_APLICA'];
    }
}
