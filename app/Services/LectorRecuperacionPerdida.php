<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/** V109F5B: lectura bloqueada del origen y base global de valorización.
 * Primera etapa: una recuperación activa por salida; no admite recuperaciones sucesivas.
 */
final class LectorRecuperacionPerdida
{
    private function decimal(mixed $v): BigDecimal {
        abort_unless(is_string($v)||is_int($v),409,'Valor de origen no exacto.');
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),409,'Valor de origen inválido.');
        return BigDecimal::of($v)->toScale(8);
    }
    public function leer(int $kid,int $sid,?int $propia=null): array {
        abort_unless(config('perdidas.recuperacion_verificada')===true&&DB::transactionLevel()>0&&$sid>0&&$kid>0,409,'Recuperación no habilitada.');
        $initial=DB::table('kardex')->where('id',$kid)->first();
        abort_unless($initial&&(int)$initial->sucursal_id===$sid,404,'Movimiento no disponible.');
        $pid=(int)$initial->producto_id;$bid=(int)$initial->bodega_id;
        $p=DB::table('productos')->where('id',$pid)->where('activo',1)->lockForUpdate()->first();
        abort_unless($p,409,'Producto inactivo.');
        $b=DB::table('bodegas as b')->join('inventario_bodegas_sucursales as bs','bs.bodega_id','=','b.id')->where('b.id',$bid)->where('b.activo',1)->where('bs.sucursal_id',$sid)->lockForUpdate()->first();
        abort_unless($b,404,'Bodega no disponible.');
        $k=DB::table('kardex')->where('id',$kid)->lockForUpdate()->first();
        abort_unless($k&&(int)$k->sucursal_id===$sid&&(int)$k->producto_id===$pid&&(int)$k->bodega_id===$bid
            &&$k->origen_tipo==='AJUSTE_INVENTARIO'&&(int)$k->tipo_movimiento===2&&(int)$k->origen_detalle_id>0,409,'Origen incompatible.');
        $d=DB::table('inventario_operaciones_detalle')->where('id',$k->origen_detalle_id)->lockForUpdate()->first();
        abort_unless($d&&(int)$d->kardex_id===$kid&&(int)$d->producto_id===$pid&&(int)$d->bodega_id===$bid,409,'Detalle de origen incompatible.');
        $o=DB::table('inventario_operaciones as o')->join('estados as e','e.id','=','o.estado_id')->where('o.id',$d->operacion_id)->select('o.*','e.codigo as estado_codigo')->lockForUpdate()->first();
        abort_unless($o&&$o->clase==='AJUSTE'&&(int)$o->sucursal_id===$sid&&$o->estado_codigo==='INV_CONFIRMADA'
            &&in_array((int)$o->motivo_codigo_snapshot,array_merge(range(301,311),range(401,409)),true)&&$o->politica_cpp_snapshot==='VIGENTE'
            &&$o->regla_snapshot===$k->operacion_codigo&&(int)$o->creado_por>0&&(int)$o->aprobado_por>0
            &&(int)$o->creado_por!==(int)$o->aprobado_por&&(int)$o->confirmado_por>0&&$o->token_confirmacion!==null&&$o->anulado_por===null,409,'Pérdida no acreditada.');
        $q=$this->decimal($k->cantidad_documento);$cost=$this->decimal($k->costo_unitario_neto);
        abort_unless($q->isPositive()&&$cost->isPositive()&&$cost->isEqualTo($this->decimal($k->cpp_anterior))&&$cost->isEqualTo($this->decimal($k->cpp_actual))
            &&$cost->isEqualTo($this->decimal($d->costo_unitario_snapshot))&&$q->isEqualTo($this->decimal($d->cantidad))
            &&BigDecimal::of((string)$d->delta_disponible)->isEqualTo($q->negated())&&BigDecimal::of((string)$d->delta_fisico)->isEqualTo($q->negated())
            &&$this->decimal($d->delta_transito)->isZero()&&$this->decimal($d->delta_comprometido)->isZero()
            &&$this->decimal($k->cantidad_anterior)->minus($this->decimal($k->cantidad_actual))->isEqualTo($q)
            &&$this->decimal($k->fisico_anterior)->minus($this->decimal($k->fisico_actual))->isEqualTo($q)
            &&$this->decimal($k->transito_anterior)->isEqualTo($this->decimal($k->transito_actual))
            &&$this->decimal($k->comprometido_anterior)->isEqualTo($this->decimal($k->comprometido_actual)),409,'Costo o cantidades históricas incompatibles.');
        $links=DB::table('inventario_operaciones as x')->leftJoin('estados as e','e.id','=','x.estado_id')->where('x.referencia_kardex_id',$kid)
            ->where(fn($z)=>$z->whereNull('e.codigo')->orWhere('e.codigo','<>','INV_ANULADA'));
        if($propia!==null)$links->where('x.id','<>',$propia);
        abort_if($links->lockForUpdate()->get(['x.id'])->isNotEmpty(),409,'Origen ya utilizado.');
        abort_if(DB::table('kardex')->where('movimiento_reversado_id',$kid)->lockForUpdate()->get(['id'])->isNotEmpty(),409,'Origen revertido.');
        abort_if(DB::table('saldos_inventario as s')->join('presentaciones_productos as pp','pp.id','=','s.presentacion_id')->where('pp.producto_id',$pid)->where('s.cantidad','<>',0)->exists(),409,'Inventario legado no conciliado.');
        abort_if(DB::table('inventario_traspasos_detalle')->where('producto_id',$pid)->whereRaw('cantidad_despachada>cantidad_recibida+cantidad_regularizada')->exists(),409,'Traspaso pendiente de valorización.');
        $base=BigDecimal::zero()->toScale(8);$target=null;$seen=[];
        foreach(DB::table('stock')->where('producto_id',$pid)->orderBy('bodega_id')->lockForUpdate()->get() as $s){
            $key=(int)$s->bodega_id;abort_if(isset($seen[$key]),409,'Stock duplicado.');$seen[$key]=true;
            $a=$this->decimal($s->disponible);$t=$this->decimal($s->transito);$c=$this->decimal($s->comprometido);$f=$this->decimal($s->cantidad_fisica);
            abort_unless($f->isEqualTo($a->plus($t)->plus($c)),409,'Base global incoherente.');
            $base=$base->plus($a)->plus($c);if($key===$bid)$target=$s;
        }
        abort_unless($target,409,'Saldo de bodega no disponible.');
        return ['referencia_kardex_id'=>$kid,'operacion_origen_id'=>(int)$o->id,'producto_id'=>$pid,'bodega_id'=>$bid,'sucursal_id'=>$sid,
            'costo_historico'=>(string)$cost,'cantidad_perdida'=>(string)$q,'cantidad_recuperada'=>'0.00000000',
            'cpp'=>(string)$this->decimal($p->costo_promedio_ponderado),'base_valorizada'=>(string)$base,'stock'=>$target,
            'solo_lectura'=>true];
    }
}
