<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;

/** V109D1: conciliación de conteo presencial. No persiste ni autoriza ajustes. */
final class PlanConteoInventario
{
    private function decimal(mixed $v): BigDecimal {
        abort_unless(is_int($v)||is_string($v),422,'Ingrese una cantidad decimal exacta.');
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),422,'Cantidad o costo fuera de formato.');
        return BigDecimal::of($v)->toScale(8);
    }
    private function saldo(object $s): array {
        $out=[];
        foreach(['disponible','transito','comprometido','cantidad_fisica'] as $k)$out[$k]=$this->decimal($s->$k??null);
        abort_unless($out['cantidad_fisica']->isEqualTo($out['disponible']->plus($out['transito'])->plus($out['comprometido'])),409,'Saldo de inventario inconsistente.');
        return $out;
    }
    public function calcular(object $stock,mixed $contado,mixed $cpp): array {
        $s=$this->saldo($stock);$q=$this->decimal($contado);$cost=$this->decimal($cpp);
        // Conteo de mercadería presente en la bodega; tránsito no se cuenta.
        // Comprometido se mantiene íntegro, no se libera ni se consume por conteo.
        $expected=$s['disponible']->plus($s['comprometido']);
        $delta=$q->minus($expected);$matches=$delta->isZero();
        $a=$s['disponible']->plus($delta);$f=$s['cantidad_fisica']->plus($delta);
        $max='99999999999999999999.99999999';
        $conflicts=[];
        if($a->isNegative())$conflicts[]='COMPROMETIDO_INSUFICIENTE';
        if($f->isGreaterThan($max)||$a->isGreaterThan($max))$conflicts[]='SALDO_FUERA_DE_RANGO';
        if(!$matches&&$cost->isZero())$conflicts[]='CPP_NO_VALORIZADO';
        // Un conteo puede registrar discrepancias aun cuando no sea ajustable.
        $adjustable=!$matches&&$conflicts===[];
        return [
            'alcance'=>'PRESENTE_EN_BODEGA',
            'esperado'=>(string)$expected,'contado'=>(string)$q,'diferencia'=>(string)$delta,
            'resultado'=>$matches?'CUADRADO':($delta->isPositive()?'SOBRANTE':'FALTANTE'),
            'motivo_sugerido'=>$matches?null:($delta->isPositive()?101:102),
            'requiere_ajuste'=>!$matches,'ajuste_calculable'=>$adjustable,'bloqueos'=>$conflicts,
            'requiere_aprobacion'=>!$matches,'requiere_respaldo'=>!$matches,
            'snapshot'=>array_map(fn($v)=>(string)$v,$s)+['cpp'=>(string)$cost],
            'posterior_propuesto'=>$adjustable?['disponible'=>(string)$a,'transito'=>(string)$s['transito'],'comprometido'=>(string)$s['comprometido'],'cantidad_fisica'=>(string)$f,'cpp'=>(string)$cost]:null,
            'valor_diferencia'=>$cost->isPositive()?(string)$delta->multipliedBy($cost)->toScale(16):null,
            'solo_calculo'=>true,
        ];
    }
    /** Bloquear cierres basados en cantidades o costos que cambiaron desde el registro. */
    public function verificarVigencia(array $snapshot,object $stock,mixed $cpp): void {
        $actual=$this->saldo($stock)+['cpp'=>$this->decimal($cpp)];
        foreach($actual as $k=>$v)abort_unless(array_key_exists($k,$snapshot)&&$v->isEqualTo($this->decimal($snapshot[$k])),409,'El inventario o CPP cambió desde el conteo. Requiere nueva conciliación.');
    }
}
