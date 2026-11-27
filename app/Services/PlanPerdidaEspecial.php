<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;

/** V109F4A: pérdidas adicionales sin referencia, según contrato explícito del catálogo. */
final class PlanPerdidaEspecial
{
    public const CODIGOS=[306,307,308,309,310,311,401,402,403,404,405,406,408,409];

    public function contrato(object $m,array $contratos): array {
        $code=(int)($m->codigo??0);$c=$contratos[$code]??null;
        abort_unless(in_array($code,self::CODIGOS,true)&&is_array($c),409,'Motivo especial sin contrato verificado.');
        $group=$code>=400?400:300;
        abort_unless(isset($c['regla'],$c['aviso'])&&is_string($c['regla'])&&preg_match('/^[A-Z_]+$/D',$c['regla'])===1
            &&in_array($c['aviso'],[0,1],true)&&($group!==400||$c['aviso']===1)
            &&(int)($m->grupo_codigo??0)===$group&&($m->regla??null)===$c['regla']
            &&($m->naturaleza??null)==='SALIDA'&&($m->politica_cpp??null)==='VIGENTE'
            &&(int)($m->activo??0)===1&&(int)($m->ajuste_rapido??-1)===0
            &&(int)($m->requiere_referencia??-1)===0&&(int)($m->requiere_aprobacion??-1)===1
            &&(int)($m->requiere_respaldo??-1)===1&&(int)($m->revision_tributaria??-1)===1
            &&(int)($m->evaluar_aviso_sii??-1)===$c['aviso'],409,'Catálogo de pérdida especial incompatible.');
        return $c;
    }
    private function decimal(mixed $v): BigDecimal {
        abort_unless(is_string($v)||is_int($v),422,'Se requiere decimal exacto.');
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),422,'Decimal fuera de rango.');
        return BigDecimal::of($v)->toScale(8);
    }
    public function calcular(object $m,mixed $cantidad,object $stock,mixed $cpp,array $contratos): array {
        $c=$this->contrato($m,$contratos);
        $q=$this->decimal($cantidad);$cost=$this->decimal($cpp);
        abort_unless($q->isPositive(),422,'Cantidad positiva requerida.');
        abort_unless($cost->isPositive(),409,'CPP vigente no valorizado.');
        $a=$this->decimal($stock->disponible??null);$t=$this->decimal($stock->transito??null);
        $committed=$this->decimal($stock->comprometido??null);$f=$this->decimal($stock->cantidad_fisica??null);
        abort_unless($f->isEqualTo($a->plus($t)->plus($committed))&&$q->isLessThanOrEqualTo($a),409,'Stock incoherente o disponible insuficiente.');
        return ['motivo_codigo'=>(int)$m->codigo,'cantidad'=>(string)$q,'delta_disponible'=>(string)$q->negated(),'delta_fisico'=>(string)$q->negated(),
            'delta_transito'=>'0.00000000','delta_comprometido'=>'0.00000000','disponible_anterior'=>(string)$a,'disponible_actual'=>(string)$a->minus($q),
            'fisico_anterior'=>(string)$f,'fisico_actual'=>(string)$f->minus($q),'transito'=>(string)$t,'comprometido'=>(string)$committed,
            'cpp_anterior'=>(string)$cost,'cpp_actual'=>(string)$cost,'costo_unitario_snapshot'=>(string)$cost,
            'valor_movimiento'=>(string)$q->multipliedBy($cost)->toScale(16),'requiere_respaldo'=>true,'requiere_aprobacion'=>true,
            'revision_tributaria'=>'PENDIENTE','evaluacion_aviso_sii'=>$c['aviso']===1?'PENDIENTE':'NO_APLICA','aviso_emitido'=>false];
    }
}
