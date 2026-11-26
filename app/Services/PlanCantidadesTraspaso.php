<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;

/** V109C1: cantidades de traspaso; no modifica stock, estados ni documentos. */
final class PlanCantidadesTraspaso
{
    private function decimal(string $v): BigDecimal {
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',$v),422,'Cantidad fuera del formato decimal.');
        return BigDecimal::of($v)->toScale(8);
    }
    private function saldo(object $line): array {
        $s=$this->decimal((string)$line->cantidad_solicitada);
        $d=$this->decimal((string)$line->cantidad_despachada);
        $r=$this->decimal((string)$line->cantidad_recibida);
        $g=$this->decimal((string)$line->cantidad_regularizada);
        abort_unless($s->isPositive()&&$d->isLessThanOrEqualTo($s)&&$r->plus($g)->isLessThanOrEqualTo($d),409,'Cantidades de traspaso inconsistentes.');
        return [$s,$d,$r,$g];
    }
    public function resumen(object $line): array {
        [$s,$d,$r,$g]=$this->saldo($line);
        return ['cantidad_solicitada'=>(string)$s,'cantidad_despachada'=>(string)$d,'cantidad_recibida'=>(string)$r,'cantidad_regularizada'=>(string)$g,'pendiente_despacho'=>(string)$s->minus($d),'pendiente_recepcion'=>(string)$d->minus($r)->minus($g)];
    }
    public function calcular(object $line,string $modalidad,string $etapa,string $cantidad): array {
        abort_unless(in_array($modalidad,['INMEDIATO','DESPACHO_RECEPCION'],true),422,'Modalidad no válida.');
        abort_unless(in_array($etapa,['INMEDIATO','DESPACHO','RECEPCION'],true),422,'Etapa aún no habilitada para cálculo.');
        [$s,$d,$r,$g]=$this->saldo($line);
        $q=$this->decimal($cantidad);abort_unless($q->isPositive(),422,'Cantidad positiva obligatoria.');
        // Conservador: despacho total inicial; recepción puede ser parcial.
        if($etapa==='INMEDIATO'||$etapa==='DESPACHO'){
            abort_unless($modalidad===($etapa==='INMEDIATO'?'INMEDIATO':'DESPACHO_RECEPCION'),409,'Etapa incompatible con modalidad.');
            abort_unless($d->isZero()&&$r->isZero()&&$g->isZero()&&$q->isEqualTo($s),409,'Se requiere un despacho inicial por la cantidad total solicitada.');
            $d=$q;if($etapa==='INMEDIATO')$r=$q;
        }else{
            abort_unless($modalidad==='DESPACHO_RECEPCION'&&$d->isPositive()&&$q->isLessThanOrEqualTo($d->minus($r)->minus($g)),409,'Recepción superior a cantidad pendiente o sin despacho.');
            $r=$r->plus($q);
        }
        $after=$this->resumen((object)['cantidad_solicitada'=>(string)$s,'cantidad_despachada'=>(string)$d,'cantidad_recibida'=>(string)$r,'cantidad_regularizada'=>(string)$g]);
        return ['etapa'=>$etapa,'cantidad_movimiento'=>(string)$q,'anterior'=>$this->resumen($line),'posterior'=>$after,'recepcion_completa'=>$d->isEqualTo($s)&&$r->plus($g)->isEqualTo($d),'solo_calculo'=>true];
    }
}
