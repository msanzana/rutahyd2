<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** V109G3A: plan mixto 603, únicamente sobre datos normalizados por el servidor.
 * No acredita referencias, no escribe stock ni emite documentos.
 */
final class PlanDevolucionMixta
{
    private function dec(mixed $v): BigDecimal {
        abort_unless((is_string($v)||is_int($v))&&preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),422,'Decimal exacto requerido.');
        return BigDecimal::of($v)->toScale(8);
    }
    private function saldo(BigDecimal $v): string {
        abort_if($v->isNegative()||$v->isGreaterThan('99999999999999999999.99999999'),409,'Saldo o CPP fuera de rango.');
        return (string)$v->toScale(8);
    }
    private function id(mixed $v): int {
        abort_unless(is_int($v)&&$v>0,422,'Identidad normalizada requerida.');
        return $v;
    }
    public function calcular(object $m,array $contratos,int $sid,array $lineas,array $productos,array $stocks,array $referencias): array {
        $contract=$contratos[603]??null;
        abort_unless($sid>0&&is_array($contract)&&is_string($contract['regla']??null)
            &&preg_match('/^[A-Z_]+$/D',$contract['regla'])===1&&array_key_exists('indicador',$contract)
            &&($contract['indicador']===null||(is_int($contract['indicador'])&&$contract['indicador']>0))
            &&($contract['naturaleza']??null)==='MIXTO'&&($contract['valorizacion']??null)==='ESPECIFICA_NETO_POR_PRODUCTO'
            &&(int)($m->codigo??0)===603&&(int)($m->grupo_codigo??0)===600&&($m->regla??null)===$contract['regla']
            &&($m->naturaleza??null)===$contract['naturaleza']&&($m->politica_cpp??null)==='ESPECIFICA'
            &&(($m->indicador_traslado_sii??null)===null?null:(int)$m->indicador_traslado_sii)===$contract['indicador']
            &&(int)($m->activo??0)===1&&(int)($m->ajuste_rapido??-1)===0
            &&(int)($m->requiere_referencia??-1)===1&&(int)($m->requiere_aprobacion??-1)===1
            &&(int)($m->requiere_respaldo??-1)===1&&(int)($m->revision_tributaria??-1)===1
            &&(int)($m->evaluar_aviso_sii??-1)===0,409,'Contrato de devolución mixta no acreditado.');
        abort_unless(count($lineas)>=2&&count($lineas)<=50,422,'Se requieren entre dos y cincuenta líneas.');
        $zero=BigDecimal::zero()->toScale(8);
        $seen=[];$consumo=[];$groups=[];$balances=[];$planned=[];$directions=[];$groupRef=null;
        foreach($lineas as $l){
            abort_unless(is_array($l),422,'Línea inválida.');
            $pid=$this->id($l['producto_id']??null);$bid=$this->id($l['bodega_id']??null);$rid=$this->id($l['referencia_kardex_id']??null);
            $dir=$l['direccion']??null;
            abort_unless(in_array($dir,['ENTRADA','SALIDA'],true),422,'Dirección inválida.');
            $q=$this->dec($l['cantidad']??null);abort_unless($q->isPositive(),422,'Cantidad positiva requerida.');
            $key=$pid.':'.$bid;$unique=$key.':'.$rid.':'.$dir;
            abort_if(isset($seen[$unique]),422,'Línea de devolución duplicada.');$seen[$unique]=true;
            $ref=$referencias[$rid]??null;
            abort_unless(is_array($ref)&&($ref['acreditada']??null)===true&&($ref['sucursal_id']??null)===$sid
                &&($ref['producto_id']??null)===$pid&&($ref['bodega_id']??null)===$bid&&($ref['direccion']??null)===$dir
                &&is_string($ref['grupo_documental']??null)&&trim($ref['grupo_documental'])!=='',409,'Referencia de línea incompatible.');
            $groupRef??=$ref['grupo_documental'];abort_unless($groupRef===$ref['grupo_documental'],409,'Referencias sin vínculo comercial común.');
            $cost=$this->dec($ref['costo_especifico']??null);$limit=$this->dec($ref['saldo_solicitable']??null);
            abort_unless($cost->isPositive(),409,'Costo específico no acreditado.');
            $consumo[$rid]=($consumo[$rid]??$zero)->plus($q);
            abort_unless($consumo[$rid]->isLessThanOrEqualTo($limit),409,'Cantidad supera saldo documental.');
            $s=$stocks[$key]??null;$p=$productos[$pid]??null;
            abort_unless(is_object($s)&&is_array($p)&&($p['base_acreditada']??null)===true
                &&($s->sucursal_id??null)===$sid&&($s->producto_id??null)===$pid&&($s->bodega_id??null)===$bid,409,'Stock o base global no acreditados.');
            if(!isset($balances[$key])){
                $a=$this->dec($s->disponible??null);$t=$this->dec($s->transito??null);
                $c=$this->dec($s->comprometido??null);$f=$this->dec($s->cantidad_fisica??null);
                abort_unless($f->isEqualTo($a->plus($t)->plus($c)),409,'Identidad física incoherente.');
                $balances[$key]=['producto_id'=>$pid,'bodega_id'=>$bid,'a'=>$a,'t'=>$t,'c'=>$c,'f'=>$f,'in'=>$zero,'out'=>$zero];
            }
            if(!isset($groups[$pid])){
                $base=$this->dec($p['base_valorizada']??null);$cpp=$this->dec($p['cpp']??null);
                abort_if($base->isPositive()&&$cpp->isZero(),409,'CPP cero con stock valorizable.');
                $groups[$pid]=['base'=>$base,'cpp'=>$cpp,'in'=>$zero,'out'=>$zero,'vin'=>$zero,'vout'=>$zero,'local'=>$zero];
            }
            $v=$q->multipliedBy($cost)->toScale(16);
            $side=$dir==='ENTRADA'?'in':'out';$vside=$dir==='ENTRADA'?'vin':'vout';
            $balances[$key][$side]=$balances[$key][$side]->plus($q);
            $groups[$pid][$side]=$groups[$pid][$side]->plus($q);
            $groups[$pid][$vside]=$groups[$pid][$vside]->plus($v);
            $directions[$dir]=true;
            $delta=$dir==='ENTRADA'?$q:$q->negated();
            $planned[]=['producto_id'=>$pid,'bodega_id'=>$bid,'referencia_kardex_id'=>$rid,'direccion'=>$dir,
                'cantidad'=>(string)$q,'costo_unitario_snapshot'=>(string)$cost,'valor_movimiento'=>(string)$v,
                'delta_disponible'=>(string)$delta,'delta_fisico'=>(string)$delta,'delta_transito'=>'0.00000000','delta_comprometido'=>'0.00000000'];
        }
        abort_unless(isset($directions['ENTRADA'],$directions['SALIDA']),422,'El motivo mixto exige entrada y salida.');
        $stockPlans=[];
        foreach($balances as $key=>$s){
            // No usar el ingreso de la misma solicitud para justificar una salida sin stock.
            abort_unless($s['out']->isLessThanOrEqualTo($s['a']),409,'Disponible previo insuficiente.');
            $delta=$s['in']->minus($s['out']);$pid=$s['producto_id'];
            $groups[$pid]['local']=$groups[$pid]['local']->plus($s['a'])->plus($s['c']);
            $stockPlans[$key]=['producto_id'=>$pid,'bodega_id'=>$s['bodega_id'],
                'disponible_anterior'=>(string)$s['a'],'disponible_actual'=>$this->saldo($s['a']->plus($delta)),
                'fisico_anterior'=>(string)$s['f'],'fisico_actual'=>$this->saldo($s['f']->plus($delta)),
                'transito'=>(string)$s['t'],'comprometido'=>(string)$s['c'],
                'entrada'=>(string)$s['in'],'salida'=>(string)$s['out']];
        }
        $round=defined(RoundingMode::class.'::HalfUp')?constant(RoundingMode::class.'::HalfUp'):constant(RoundingMode::class.'::HALF_UP');
        $valuation=[];
        foreach($groups as $pid=>$g){
            abort_unless($g['base']->isGreaterThanOrEqualTo($g['local'])&&$g['out']->isLessThanOrEqualTo($g['base']),409,'Base global insuficiente.');
            $oldValue=$g['base']->multipliedBy($g['cpp'])->toScale(16);
            $remain=$oldValue->minus($g['vout']);
            abort_unless(!$remain->isNegative(),409,'Salida específica excede valor previo.');
            $nextBase=$g['base']->minus($g['out'])->plus($g['in']);
            $nextValue=$remain->plus($g['vin']);$nextCpp=$g['cpp'];
            if($nextBase->isZero())abort_unless($nextValue->isZero(),409,'Diferencia contable sin existencias.');
            else{
                abort_unless($nextValue->isPositive(),409,'Inventario resultante sin valor.');
                $nextCpp=$nextValue->dividedBy($nextBase,8,$round);
                abort_unless($nextCpp->isPositive(),409,'CPP redondeado a cero.');
            }
            $valuation[$pid]=['producto_id'=>$pid,'base_anterior'=>(string)$g['base'],'base_actual'=>$this->saldo($nextBase),
                'cpp_anterior'=>(string)$g['cpp'],'cpp_actual'=>$this->saldo($nextCpp),
                'valor_anterior'=>(string)$oldValue,'valor_entrada'=>(string)$g['vin']->toScale(16),
                'valor_salida'=>(string)$g['vout']->toScale(16),'valor_actual'=>(string)$nextValue->toScale(16)];
        }
        usort($planned,fn($a,$b)=>[$a['producto_id'],$a['bodega_id'],$a['direccion']==='SALIDA'?0:1,$a['referencia_kardex_id']]<=>[$b['producto_id'],$b['bodega_id'],$b['direccion']==='SALIDA'?0:1,$b['referencia_kardex_id']]);
        ksort($valuation,SORT_NUMERIC);ksort($stockPlans,SORT_STRING);ksort($consumo,SORT_NUMERIC);
        return ['motivo_codigo'=>603,'naturaleza'=>'MIXTO','politica_cpp'=>'ESPECIFICA','indicador_traslado_sii'=>$contract['indicador'],
            'sucursal_id'=>$sid,'grupo_documental'=>$groupRef,'lineas'=>$planned,'stocks'=>array_values($stockPlans),
            'valorizaciones'=>array_values($valuation),'consumo_referencias'=>array_map(fn($v)=>(string)$v,$consumo),
            'requiere_referencia'=>true,'requiere_respaldo'=>true,'requiere_aprobacion'=>true,'revision_tributaria'=>'PENDIENTE',
            'evaluacion_aviso_sii'=>'NO_APLICA','documento_emitido'=>false,'requiere_transaccion_atomica'=>true,'solo_calculo'=>true];
    }
}
