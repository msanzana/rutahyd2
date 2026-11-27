<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;

/** V109E4C1: plan de consumo de reserva por despacho 712; no persiste ni emite documentos. */
final class PlanConsumoReserva
{
    private function decimal(mixed $v): BigDecimal {
        abort_unless(is_string($v)||is_int($v),422,'Se requiere decimal exacto.');
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),422,'Decimal fuera de formato.');
        return BigDecimal::of($v)->toScale(8);
    }
    public function calcular(object $motivo, mixed $cantidad, object $stock, mixed $cpp, mixed $reservada, mixed $liberada, mixed $despachada, mixed $pendienteComercial): array {
        abort_unless((int)($motivo->codigo??0)===712&&(int)($motivo->grupo_codigo??0)===700&&(int)($motivo->activo??0)===1
            &&($motivo->regla??null)==='DESPACHAR'&&($motivo->politica_cpp??null)==='VIGENTE'
            &&(int)($motivo->ajuste_rapido??-1)===0&&(int)($motivo->requiere_referencia??-1)===1
            &&(int)($motivo->requiere_aprobacion??-1)===1&&(int)($motivo->requiere_respaldo??-1)===0
            &&(int)($motivo->revision_tributaria??-1)===1&&(int)($motivo->indicador_traslado_sii??0)===1,409,'Motivo de despacho incompatible.');
        $q=$this->decimal($cantidad);$cost=$this->decimal($cpp);
        abort_unless($q->isPositive(),422,'Cantidad positiva requerida.');
        abort_unless($cost->isPositive(),409,'CPP no valorizado; requiere regularización.');
        $r=$this->decimal($reservada);$l=$this->decimal($liberada);$d=$this->decimal($despachada);$commercial=$this->decimal($pendienteComercial);
        $pending=$r->minus($l)->minus($d);
        $s=[];foreach(['disponible','transito','comprometido','cantidad_fisica'] as $key)$s[$key]=$this->decimal($stock->$key??null);
        abort_unless($s['cantidad_fisica']->isEqualTo($s['disponible']->plus($s['transito'])->plus($s['comprometido'])),409,'Stock físico inconsistente.');
        abort_unless($r->isPositive()&&!$pending->isNegative()&&$pending->isLessThanOrEqualTo($s['comprometido']),409,'Saldo propio no conciliable.');
        abort_unless($q->isLessThanOrEqualTo($pending)&&$q->isLessThanOrEqualTo($commercial),409,'Despacho excede reserva o demanda comercial pendiente.');
        $c=$s['comprometido']->minus($q);$f=$s['cantidad_fisica']->minus($q);
        abort_unless(!$c->isNegative()&&!$f->isNegative()&&$f->isEqualTo($s['disponible']->plus($s['transito'])->plus($c)),409,'Plan físico inconsistente.');
        return [
            'motivo_codigo'=>712,'regla'=>'DESPACHAR','cantidad'=>(string)$q,
            'delta_disponible'=>'0.00000000','delta_transito'=>'0.00000000','delta_comprometido'=>(string)$q->negated(),'delta_fisico'=>(string)$q->negated(),
            'anterior'=>array_map(fn($v)=>(string)$v,$s),
            'posterior'=>['disponible'=>(string)$s['disponible'],'transito'=>(string)$s['transito'],'comprometido'=>(string)$c,'cantidad_fisica'=>(string)$f],
            'reservada'=>(string)$r,'liberada'=>(string)$l,'despachada_anterior'=>(string)$d,'despachada_actual'=>(string)$d->plus($q),
            'pendiente_anterior'=>(string)$pending,'pendiente_actual'=>(string)$pending->minus($q),
            'pendiente_comercial_actual'=>(string)$commercial->minus($q),
            'cpp_anterior'=>(string)$cost,'cpp_actual'=>(string)$cost,'valor_salida'=>(string)$q->multipliedBy($cost)->toScale(16),
            'requiere_referencia'=>true,'requiere_aprobacion'=>true,'requiere_respaldo'=>false,'revision_tributaria'=>'PENDIENTE','indicador_traslado_sii'=>1,
            'solo_calculo'=>true,
        ];
    }
}
