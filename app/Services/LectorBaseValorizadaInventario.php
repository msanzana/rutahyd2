<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/** V109C5B: identifica tránsito interno acreditado bajo bloqueo de producto y saldos. */
final class LectorBaseValorizadaInventario
{
    public function leer(int $pid,iterable $stocks): array {
        abort_unless(DB::transactionLevel()>0,500,'Valorización sin transacción.');
        // Todos los escritores de inventario deben bloquear el producto antes de modificar saldos.
        abort_unless(DB::table('productos')->where('id',$pid)->lockForUpdate()->first(),409,'Producto inexistente.');
        $lines=DB::table('inventario_traspasos_detalle as d')->leftJoin('inventario_traspasos as t','t.id','=','d.traspaso_id')
            ->where('d.producto_id',$pid)->whereRaw('(d.cantidad_despachada-d.cantidad_recibida-d.cantidad_regularizada)>0')
            ->orderBy('d.id')->select('d.*','t.bodega_origen_id','t.bodega_destino_id','t.sucursal_origen_id','t.sucursal_destino_id','t.modalidad','t.estado')->lockForUpdate()->get();
        foreach($lines as $l){
            abort_unless($l->modalidad==='DESPACHO_RECEPCION'&&in_array($l->estado,['DESPACHADO','PARCIAL'],true)&&(int)$l->sucursal_origen_id>0&&(int)$l->sucursal_destino_id>0,409,'Traspaso pendiente sin cabecera válida.');
            $ops=DB::table('inventario_traspasos_operaciones as x')->join('inventario_operaciones as o','o.id','=','x.operacion_id')->join('estados as e','e.id','=','o.estado_id')
                ->where('x.traspaso_id',$l->traspaso_id)->where('x.etapa','DESPACHO')->where('o.clase','TRASPASO')->where('e.codigo','INV_CONFIRMADA')
                ->where('o.motivo_codigo_snapshot',709)->where('o.regla_snapshot','TRASPASO_INTERNO')->where('o.politica_cpp_snapshot','CONSERVAR')->where('o.sucursal_id',$l->sucursal_origen_id)
                ->select('o.id','o.confirmado_por','o.aprobado_por')->lockForUpdate()->get();
            $op=$ops->first();abort_unless($ops->count()===1&&$op->confirmado_por&&$op->aprobado_por,409,'Tránsito sin despacho confirmado.');
            $details=DB::table('inventario_operaciones_detalle')->where('operacion_id',$op->id)->where('producto_id',$pid)->lockForUpdate()->get();
            abort_unless($details->count()===2,409,'Detalle de despacho inválido.');
            abort_unless($l->costo_cpp_snapshot!==null,409,'Tránsito sin CPP acreditado.');
            $q=BigDecimal::of((string)$l->cantidad_despachada);$cpp=BigDecimal::of((string)$l->costo_cpp_snapshot);
            abort_unless($cpp->isPositive()&&BigDecimal::of((string)$l->cantidad_regularizada)->isZero(),409,'Regularización de tránsito aún no soportada.');
            foreach([[$l->bodega_origen_id,$l->sucursal_origen_id,false],[$l->bodega_destino_id,$l->sucursal_destino_id,true]] as [$bid,$sid,$dest]){
                $matches=$details->filter(fn($d)=>(int)$d->bodega_id===(int)$bid);$d=$matches->first();
                abort_unless($matches->count()===1&&$d->kardex_id&&BigDecimal::of((string)$d->cantidad)->isEqualTo($q)&&BigDecimal::of((string)$d->costo_unitario_snapshot)->isEqualTo($cpp),409,'Detalle de despacho sin respaldo contable.');
                $k=DB::table('kardex')->where('id',$d->kardex_id)->lockForUpdate()->first();
                abort_unless($k&&(int)$k->producto_id===$pid&&(int)$k->bodega_id===(int)$bid&&(int)$k->sucursal_id===(int)$sid&&$k->origen_tipo==='AJUSTE_INVENTARIO'&&(int)$k->origen_detalle_id===(int)$d->id&&$k->operacion_codigo==='TRASPASO_DESPACHO'&&(int)$k->tipo_movimiento===($dest?3:2)&&BigDecimal::of((string)$k->cantidad_documento)->isEqualTo($q)&&BigDecimal::of((string)$k->costo_unitario_neto)->isEqualTo($cpp),409,'Kardex de despacho no acreditado.');
                $da=$dest?BigDecimal::zero():$q->negated();$dt=$dest?$q:BigDecimal::zero();$df=$dest?$q:$q->negated();
                foreach(['disponible'=>[$da,'cantidad_anterior','cantidad_actual'],'transito'=>[$dt,'transito_anterior','transito_actual'],'comprometido'=>[BigDecimal::zero(),'comprometido_anterior','comprometido_actual'],'fisico'=>[$df,'fisico_anterior','fisico_actual']] as $field=>[$delta,$before,$after]){
                    $key='delta_'.$field;
                    abort_unless(BigDecimal::of((string)$d->$key)->isEqualTo($delta)&&BigDecimal::of((string)$k->$after)->minus((string)$k->$before)->isEqualTo($delta),409,'Variación de despacho no acreditada.');
                }
            }
            $received=BigDecimal::zero();
            $receipts=DB::table('inventario_traspasos_operaciones as x')->join('inventario_operaciones as o','o.id','=','x.operacion_id')->join('estados as e','e.id','=','o.estado_id')->join('inventario_operaciones_detalle as d','d.operacion_id','=','o.id')
              ->where('x.traspaso_id',$l->traspaso_id)->where('x.etapa','RECEPCION')->where('o.clase','TRASPASO')->where('e.codigo','INV_CONFIRMADA')->where('o.motivo_codigo_snapshot',709)->where('o.sucursal_id',$l->sucursal_destino_id)->where('d.producto_id',$pid)
              ->select('d.*')->orderBy('d.id')->lockForUpdate()->get();
            foreach($receipts as $d){
                $n=BigDecimal::of((string)$d->cantidad);$k=DB::table('kardex')->where('id',$d->kardex_id)->lockForUpdate()->first();
                abort_unless($n->isPositive()&&(int)$d->bodega_id===(int)$l->bodega_destino_id&&$k&&(int)$k->producto_id===$pid&&(int)$k->sucursal_id===(int)$l->sucursal_destino_id&&(int)$k->bodega_id===(int)$l->bodega_destino_id&&$k->origen_tipo==='AJUSTE_INVENTARIO'&&(int)$k->origen_detalle_id===(int)$d->id&&$k->operacion_codigo==='TRASPASO_RECEPCION'&&BigDecimal::of((string)$k->cantidad_documento)->isEqualTo($n),409,'Recepción de traspaso no acreditada.');
                foreach(['disponible'=>[$n,'cantidad_anterior','cantidad_actual'],'transito'=>[$n->negated(),'transito_anterior','transito_actual'],'comprometido'=>[BigDecimal::zero(),'comprometido_anterior','comprometido_actual'],'fisico'=>[BigDecimal::zero(),'fisico_anterior','fisico_actual']] as $field=>[$delta,$before,$after]){
                    $key='delta_'.$field;abort_unless(BigDecimal::of((string)$d->$key)->isEqualTo($delta)&&BigDecimal::of((string)$k->$after)->minus((string)$k->$before)->isEqualTo($delta),409,'Variación de recepción no acreditada.');
                }
                $received=$received->plus($n);
            }
            abort_unless($received->isEqualTo((string)$l->cantidad_recibida),409,'Cantidad recibida de traspaso sin respaldo.');
        }
        return (new BaseValorizadaInventario())->calcular($pid,$stocks,$lines);
    }
}
