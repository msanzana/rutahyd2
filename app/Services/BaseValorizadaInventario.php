<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;

/**
 * V109C5A. Separa tránsito ya valorizado (traspasos) del tránsito de compras.
 * Recibe saldos y detalles previamente verificados y bloqueados por el llamador.
 * No lee ni modifica la base de datos.
 */
final class BaseValorizadaInventario
{
    private function decimal(mixed $v): BigDecimal {
        abort_unless(is_int($v)||is_string($v),409,'Se requiere decimal exacto.');
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),409,'Cantidad fuera de formato.');
        return BigDecimal::of($v)->toScale(8);
    }
    public function calcular(int $productoId,iterable $stocks,iterable $traspasos): array {
        abort_unless($productoId>0,422,'Producto inválido.');
        $bodegas=[];$total=BigDecimal::zero()->toScale(8);
        foreach($stocks as $s){
            $bid=(int)$s->bodega_id;
            abort_unless((int)$s->producto_id===$productoId&&$bid>0&&!isset($bodegas[$bid]),409,'Saldo duplicado o ajeno al producto.');
            $a=$this->decimal($s->disponible);$t=$this->decimal($s->transito);$c=$this->decimal($s->comprometido);$f=$this->decimal($s->cantidad_fisica);
            abort_unless($f->isEqualTo($a->plus($t)->plus($c)),409,'Stock físico inconsistente.');
            $bodegas[$bid]=['disponible'=>$a,'transito'=>$t,'comprometido'=>$c,'traspaso'=>BigDecimal::zero()->toScale(8)];
            $total=$total->plus($a)->plus($c);
        }
        $seen=[];$plan=new PlanCantidadesTraspaso();
        foreach($traspasos as $l){
            $id=(int)$l->id;$from=(int)$l->bodega_origen_id;$to=(int)$l->bodega_destino_id;
            abort_unless($id>0&&!isset($seen[$id])&&(int)$l->producto_id===$productoId&&$from>0&&$to>0&&$from!==$to,409,'Detalle de traspaso inválido o duplicado.');
            $seen[$id]=true;
            // Comprobar decimales antes de convertir a cadena en la calculadora anterior.
            foreach(['cantidad_solicitada','cantidad_despachada','cantidad_recibida','cantidad_regularizada'] as $k)$this->decimal($l->$k);
            $r=$plan->resumen($l);$q=$this->decimal($r['pendiente_recepcion']);
            if($q->isZero())continue;
            abort_unless($l->modalidad==='DESPACHO_RECEPCION'&&in_array($l->estado,['DESPACHADO','PARCIAL'],true)&&(int)$l->sucursal_origen_id>0&&(int)$l->sucursal_destino_id>0&&isset($bodegas[$to]),409,'Tránsito interno sin destino o estado acreditado.');
            abort_unless($this->decimal($l->costo_cpp_snapshot)->isPositive(),409,'Tránsito sin costo histórico acreditado.');
            $bodegas[$to]['traspaso']=$bodegas[$to]['traspaso']->plus($q);
            $total=$total->plus($q);
        }
        $result=[];$interno=BigDecimal::zero()->toScale(8);$compras=BigDecimal::zero()->toScale(8);
        foreach($bodegas as $bid=>$b){
            abort_unless($b['traspaso']->isLessThanOrEqualTo($b['transito']),409,'Tránsito de traspasos supera el saldo de la bodega.');
            $unvalued=$b['transito']->minus($b['traspaso']);
            $interno=$interno->plus($b['traspaso']);$compras=$compras->plus($unvalued);
            $result[$bid]=['disponible'=>(string)$b['disponible'],'comprometido'=>(string)$b['comprometido'],'transito_interno'=>(string)$b['traspaso'],'transito_no_valorizado'=>(string)$unvalued];
        }
        abort_if($total->isGreaterThan('99999999999999999999.99999999'),409,'Base global fuera de rango.');
        return ['stock_valorizado'=>(string)$total,'transito_interno'=>(string)$interno,'transito_no_valorizado'=>(string)$compras,'bodegas'=>$result];
    }
    public function verificarLiberacionCompra(array $base,int $bodegaId,string $cantidad): void {
        $q=$this->decimal($cantidad);
        abort_unless($q->isPositive()&&isset($base['bodegas'][$bodegaId])&&$this->decimal($base['bodegas'][$bodegaId]['transito_no_valorizado'])->isGreaterThanOrEqualTo($q),409,'Tránsito de compras insuficiente; no puede utilizarse tránsito interno.');
    }
}
