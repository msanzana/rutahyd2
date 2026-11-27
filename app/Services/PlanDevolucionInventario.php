<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** V109G1: cálculo de devoluciones físicas. No acredita documentos ni persiste movimientos. */
final class PlanDevolucionInventario
{
    private const TIPOS=[
        601=>['ENTRADA','HISTORICO'],602=>['SALIDA','ESPECIFICA'],
        604=>['ENTRADA','ESPECIFICA'],605=>['SALIDA','VIGENTE'],
        606=>['ENTRADA','HISTORICO'],607=>['TRASLADO','CONSERVAR'],
        608=>['TRASLADO','CONSERVAR'],609=>['ENTRADA','ESPECIFICA'],
    ];
    private function decimal(mixed $v): BigDecimal {
        abort_unless((is_string($v)||is_int($v))&&preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),422,'Se requiere decimal exacto de hasta ocho decimales.');
        return BigDecimal::of($v)->toScale(8);
    }
    private function saldo(BigDecimal $v): string {
        abort_if($v->isNegative()||$v->isGreaterThan('99999999999999999999.99999999'),409,'Saldo o CPP fuera de rango.');
        return (string)$v->toScale(8);
    }
    private function contrato(object $m,array $contratos): array {
        $code=(int)($m->codigo??0);$c=$contratos[$code]??null;$type=self::TIPOS[$code]??null;
        abort_unless($type&&is_array($c)&&is_string($c['regla']??null)&&preg_match('/^[A-Z_]+$/D',$c['regla'])===1
            &&array_key_exists('indicador',$c)&&($c['indicador']===null||(is_int($c['indicador'])&&$c['indicador']>0)),409,'Motivo sin contrato verificado o flujo mixto pendiente.');
        $known=match($code){601,602,606=>7,607,608=>6,default=>$c['indicador']};
        $actual=$m->indicador_traslado_sii??null;
        abort_unless($c['indicador']===$known&&($actual===null?null:(int)$actual)===$known
            &&(int)($m->grupo_codigo??0)===600&&($m->regla??null)===$c['regla']
            &&($m->naturaleza??null)===$type[0]&&($m->politica_cpp??null)===$type[1]
            &&(int)($m->activo??0)===1&&(int)($m->ajuste_rapido??-1)===0
            &&(int)($m->requiere_referencia??-1)===1&&(int)($m->requiere_aprobacion??-1)===1
            &&(int)($m->requiere_respaldo??-1)===1&&(int)($m->revision_tributaria??-1)===1
            &&(int)($m->evaluar_aviso_sii??-1)===0,409,'Contrato de devolución incompatible.');
        return [$code,$type[0],$type[1],$known];
    }
    private function stock(object $s): array {
        $a=$this->decimal($s->disponible??null);$t=$this->decimal($s->transito??null);
        $c=$this->decimal($s->comprometido??null);$f=$this->decimal($s->cantidad_fisica??null);
        abort_unless($f->isEqualTo($a->plus($t)->plus($c)),409,'Stock físico incoherente.');
        return [$a,$t,$c,$f];
    }
    private function linea(array $s,BigDecimal $delta): array {
        [$a,$t,$c,$f]=$s;
        return ['disponible_anterior'=>(string)$a,'disponible_actual'=>$this->saldo($a->plus($delta)),
            'fisico_anterior'=>(string)$f,'fisico_actual'=>$this->saldo($f->plus($delta)),
            'transito'=>(string)$t,'comprometido'=>(string)$c,'delta_disponible'=>(string)$delta,'delta_fisico'=>(string)$delta,
            'delta_transito'=>'0.00000000','delta_comprometido'=>'0.00000000'];
    }
    public function calcular(object $m,array $contratos,string $cantidad,object $origen,string $cpp,string $baseValorizada,
        string $cantidadReferencia,string $cantidadYaAplicada,?string $costoAcreditado=null,?object $destino=null): array {
        [$code,$nature,$policy,$indicator]=$this->contrato($m,$contratos);
        $q=$this->decimal($cantidad);$current=$this->decimal($cpp);$base=$this->decimal($baseValorizada);
        $ref=$this->decimal($cantidadReferencia);$used=$this->decimal($cantidadYaAplicada);
        abort_unless($q->isPositive(),422,'Cantidad positiva requerida.');
        abort_unless($used->isLessThanOrEqualTo($ref)&&$q->isLessThanOrEqualTo($ref->minus($used)),409,'Cantidad excede referencia pendiente.');
        $s=$this->stock($origen);abort_unless($base->isGreaterThanOrEqualTo($s[0]->plus($s[2])),409,'Base valorizada insuficiente.');
        abort_if($base->isPositive()&&$current->isZero(),409,'CPP cero con inventario valorizable.');
        $cost=in_array($policy,['HISTORICO','ESPECIFICA'],true)?$this->decimal($costoAcreditado):$current;
        abort_unless($cost->isPositive(),409,'Costo acreditado positivo requerido.');
        $newCpp=$current;$newBase=$base;$lines=[];
        $round=defined(RoundingMode::class.'::HalfUp')?constant(RoundingMode::class.'::HalfUp'):constant(RoundingMode::class.'::HALF_UP');
        if($nature==='ENTRADA'){
            abort_unless($destino===null,422,'Entrada de una sola bodega.');
            $newBase=$base->plus($q);
            $newCpp=$base->multipliedBy($current)->plus($q->multipliedBy($cost))->dividedBy($newBase,8,$round);
            $lines['entrada']=$this->linea($s,$q);
        }elseif($nature==='SALIDA'){
            abort_unless($destino===null&&$q->isLessThanOrEqualTo($s[0])&&$q->isLessThanOrEqualTo($base),409,'Disponible insuficiente.');
            $newBase=$base->minus($q);
            $remainingValue=$base->multipliedBy($current)->minus($q->multipliedBy($cost));
            abort_unless(!$remainingValue->isNegative(),409,'Valor remanente negativo.');
            if($policy==='ESPECIFICA'){
                // No registrar diferencia contable implícita cuando la base queda vacía.
                if($newBase->isZero())abort_unless($remainingValue->isZero(),409,'Diferencia de valor sin stock; requiere regularización contable.');
                else{
                    abort_unless($remainingValue->isPositive(),409,'Existencias remanentes sin valor.');
                    $newCpp=$remainingValue->dividedBy($newBase,8,$round);
                    abort_unless($newCpp->isPositive(),409,'CPP redondeado a cero.');
                }
            }
            $lines['salida']=$this->linea($s,$q->negated());
        }else{
            abort_unless($destino!==null&&$destino!==$origen&&$q->isLessThanOrEqualTo($s[0]),409,'Traslado sin destino distinto o sin saldo.');
            $d=$this->stock($destino);
            abort_unless($base->isGreaterThanOrEqualTo($s[0]->plus($s[2])->plus($d[0])->plus($d[2])),409,'Base global incoherente.');
            $lines=['origen'=>$this->linea($s,$q->negated()),'destino'=>$this->linea($d,$q)];
        }
        return ['motivo_codigo'=>$code,'naturaleza'=>$nature,'politica_cpp'=>$policy,'indicador_traslado_sii'=>$indicator,'cantidad'=>(string)$q,
            'referencia_pendiente'=>$this->saldo($ref->minus($used)->minus($q)),'lineas'=>$lines,'cpp_anterior'=>(string)$current,
            'cpp_actual'=>$this->saldo($newCpp),'base_anterior'=>(string)$base,'base_actual'=>$this->saldo($newBase),
            'costo_unitario_snapshot'=>(string)$cost,'valor_movimiento'=>(string)$q->multipliedBy($cost)->toScale(16),
            'revision_tributaria'=>'PENDIENTE','evaluacion_aviso_sii'=>'NO_APLICA','requiere_referencia'=>true,'requiere_respaldo'=>true,
            'requiere_aprobacion'=>true,'documento_emitido'=>false,'solo_calculo'=>true];
    }
}
