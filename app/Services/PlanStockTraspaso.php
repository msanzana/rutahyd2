<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;

/** V109C2: plan físico y de costo para motivo 709. No persiste ni autoriza. */
final class PlanStockTraspaso
{
    private function decimal(string $v): BigDecimal {
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',$v),409,'Saldo o costo fuera de formato.');
        return BigDecimal::of($v)->toScale(8);
    }
    private function saldo(object $s): array {
        $v=[];
        foreach(['disponible','transito','comprometido','cantidad_fisica'] as $key)$v[$key]=$this->decimal((string)$s->$key);
        abort_unless($v['cantidad_fisica']->isEqualTo($v['disponible']->plus($v['transito'])->plus($v['comprometido'])),409,'Saldo físico inconsistente.');
        return $v;
    }
    private function aplicar(array $s,array $delta): array {
        $out=[];
        foreach($s as $k=>$v){
            $n=$v->plus($delta[$k]??'0');
            abort_if($n->isNegative()||$n->isGreaterThan('99999999999999999999.99999999'),409,'Saldo insuficiente o fuera de rango.');
            $out[$k]=['anterior'=>(string)$v,'delta'=>(string)$n->minus($v),'posterior'=>(string)$n];
        }
        abort_unless(BigDecimal::of($out['cantidad_fisica']['posterior'])->isEqualTo(BigDecimal::of($out['disponible']['posterior'])->plus($out['transito']['posterior'])->plus($out['comprometido']['posterior'])),409,'Plan físico inconsistente.');
        return $out;
    }
    public function calcular(object $motivo,object $line,string $modalidad,string $etapa,string $cantidad,object $origen,object $destino,string $cpp): array {
        abort_unless((int)$motivo->codigo===709&&(int)$motivo->grupo_codigo===700&&(int)$motivo->activo===1
            &&$motivo->regla==='TRASPASO_INTERNO'&&$motivo->politica_cpp==='CONSERVAR'&&$motivo->naturaleza==='TRASLADO'
            &&(int)$motivo->ajuste_rapido===0&&(int)$motivo->requiere_referencia===0&&(int)$motivo->requiere_aprobacion===1
            &&(int)$motivo->requiere_respaldo===0&&(int)$motivo->revision_tributaria===0&&(int)$motivo->evaluar_aviso_sii===0
            &&(int)$motivo->indicador_traslado_sii===5,409,'Motivo 709 no coincide con el catálogo oficial.');
        $qty=(new PlanCantidadesTraspaso())->calcular($line,$modalidad,$etapa,$cantidad);
        $q=$this->decimal($qty['cantidad_movimiento']);$cost=$this->decimal($cpp);
        abort_unless($cost->isPositive(),409,'CPP vigente no valorizado.');
        $a=$this->saldo($origen);$b=$this->saldo($destino);$neg=$q->negated();
        $da=[];$db=[];
        if($etapa==='INMEDIATO'){
            $da=['disponible'=>$neg,'cantidad_fisica'=>$neg];
            $db=['disponible'=>$q,'cantidad_fisica'=>$q];
        }elseif($etapa==='DESPACHO'){
            $da=['disponible'=>$neg,'cantidad_fisica'=>$neg];
            $db=['transito'=>$q,'cantidad_fisica'=>$q];
        }else{
            // El tránsito agregado de destino debe cubrir el pendiente propio.
            // La identidad del tránsito se verifica en persistencia mediante el detalle.
            abort_unless($b['transito']->isGreaterThanOrEqualTo($qty['anterior']['pendiente_recepcion']),409,'Tránsito de destino insuficiente para este traspaso.');
            $snapshot=$this->decimal((string)($line->costo_cpp_snapshot??'0'));
            abort_unless($snapshot->isPositive()&&$snapshot->isEqualTo($cost),409,'CPP cambió durante tránsito; requiere conciliación de valorización.');
            $db=['transito'=>$neg,'disponible'=>$q];
        }
        $oa=$this->aplicar($a,$da);$ob=$this->aplicar($b,$db);
        $physicalBefore=$a['cantidad_fisica']->plus($b['cantidad_fisica']);
        $physicalAfter=BigDecimal::of($oa['cantidad_fisica']['posterior'])->plus($ob['cantidad_fisica']['posterior']);
        abort_unless($physicalAfter->isEqualTo($physicalBefore),409,'El traspaso no conserva existencias totales.');
        return ['motivo_codigo'=>709,'indicador_traslado_sii'=>5,'requiere_aprobacion'=>true,'requiere_respaldo'=>false,
            'revision_tributaria'=>'NO_APLICA','evaluacion_aviso_sii'=>'NO_APLICA','cantidades'=>$qty,'origen'=>$oa,'destino'=>$ob,
            'cpp_anterior'=>(string)$cost,'cpp_actual'=>(string)$cost,'costo_cpp_snapshot'=>(string)$cost,
            'valor_movimiento'=>(string)$q->multipliedBy($cost)->toScale(16),'solo_calculo'=>true];
    }
}
