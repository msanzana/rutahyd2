<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;

/** Núcleo de cálculo V109B. No persiste ni autoriza movimientos. */
final class PlanAjusteGeneral
{
    private const REGLAS=[
        101=>['AJUSTE_POSITIVO','ENTRADA',1],
        102=>['AJUSTE_NEGATIVO','SALIDA',-1],
        107=>['RECUPERAR_EXISTENCIA','ENTRADA',1],
    ];
    private function decimal(string $v): BigDecimal {
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',$v),422,'Cantidad o valor fuera de formato.');
        return BigDecimal::of($v)->toScale(8);
    }
    private function saldo(BigDecimal $v): string {
        abort_if($v->isNegative()||$v->isGreaterThan('99999999999999999999.99999999'),409,'Saldo fuera de rango.');
        return (string)$v->toScale(8);
    }
    public function calcular(object $motivo, string $cantidad, object $stock, string $cpp): array {
        $code=(int)$motivo->codigo;$rule=self::REGLAS[$code]??null;
        abort_unless($rule&&(int)$motivo->activo===1&&(int)$motivo->grupo_codigo===100
          &&$motivo->regla===$rule[0]&&$motivo->naturaleza===$rule[1]&&$motivo->politica_cpp==='VIGENTE'
          &&(int)$motivo->ajuste_rapido===0&&(int)$motivo->requiere_referencia===0
          &&(int)$motivo->requiere_aprobacion===1&&(int)$motivo->requiere_respaldo===1
          &&(int)$motivo->revision_tributaria===1&&(int)$motivo->evaluar_aviso_sii===0,409,'Motivo no habilitado para este cálculo.');
        $q=$this->decimal($cantidad);abort_unless($q->isPositive(),422,'Ingrese una cantidad positiva.');
        $cost=$this->decimal($cpp);abort_unless($cost->isPositive(),409,'CPP vigente no valorizado. Requiere regularización autorizada.');
        $a=$this->decimal((string)$stock->disponible);$t=$this->decimal((string)$stock->transito);
        $c=$this->decimal((string)$stock->comprometido);$f=$this->decimal((string)$stock->cantidad_fisica);
        abort_unless($f->isEqualTo($a->plus($t)->plus($c)),409,'Saldo previo inconsistente.');
        $delta=$rule[2]===1?$q:$q->negated();
        return ['motivo_codigo'=>$code,'cantidad'=>(string)$q,'delta_disponible'=>(string)$delta,
          'delta_fisico'=>(string)$delta,'delta_transito'=>'0.00000000','delta_comprometido'=>'0.00000000',
          'disponible_anterior'=>(string)$a,'disponible_actual'=>$this->saldo($a->plus($delta)),
          'fisico_anterior'=>(string)$f,'fisico_actual'=>$this->saldo($f->plus($delta)),
          'transito'=>(string)$t,'comprometido'=>(string)$c,'cpp_anterior'=>(string)$cost,'cpp_actual'=>(string)$cost,
          'costo_unitario_snapshot'=>(string)$cost,'valor_movimiento'=>(string)$q->multipliedBy($cost)->toScale(16),
          'requiere_respaldo'=>true,'requiere_aprobacion'=>true,'revision_tributaria'=>'PENDIENTE'];
    }
}
