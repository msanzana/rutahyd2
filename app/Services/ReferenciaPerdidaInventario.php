<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/** V109F4B: candidato de referencia Kardex/documento, solo bajo contrato comprobado.
 * No interpreta por sí solo qué clase de documento exige cada motivo.
 */
final class ReferenciaPerdidaInventario
{
    private function decimal(mixed $v): BigDecimal {
        abort_unless(is_string($v)||is_int($v),409,'Cantidad de referencia no exacta.');
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),409,'Cantidad de referencia inválida.');
        return BigDecimal::of($v)->toScale(8);
    }
    public function verificar(int $motivo,int $kid,int $producto,int $bodega,int $sucursal,string $cantidad,?int $operacion=null): array {
        abort_unless(DB::transactionLevel()>0,500,'Referencia sin transacción.');
        $c=config('perdidas.contratos_referencia',[])[$motivo]??null;
        abort_unless(config('perdidas.referencias_verificadas')===true&&in_array($motivo,[305,407],true)&&is_array($c)
            &&($c['origen']??null)==='AJUSTE_INVENTARIO'
            &&is_string($c['regla_origen']??null)&&$c['regla_origen']!==''
            &&is_int($c['motivo_origen']??null)&&$c['motivo_origen']>0
            &&is_int($c['tipo_documento_id']??null)&&$c['tipo_documento_id']>0
            &&($c['tipo_movimiento']??null)===1,409,'Contrato de referencia documental no verificado.');
        $q=$this->decimal($cantidad);abort_unless($q->isPositive(),422,'Cantidad positiva requerida.');
        $p=DB::table('productos')->where('id',$producto)->where('activo',1)->lockForUpdate()->first();abort_unless($p,404,'Producto no disponible.');
        $b=DB::table('bodegas as b')->join('inventario_bodegas_sucursales as bs','bs.bodega_id','=','b.id')->where('b.id',$bodega)->where('b.activo',1)->where('bs.sucursal_id',$sucursal)->lockForUpdate()->first();
        abort_unless($b,404,'Bodega no disponible.');
        $k=DB::table('kardex')->where('id',$kid)->lockForUpdate()->first();
        abort_unless($k&&(int)$k->sucursal_id===$sucursal&&(int)$k->producto_id===$producto&&(int)$k->bodega_id===$bodega
            &&$k->origen_tipo===$c['origen']&&$k->operacion_codigo===$c['regla_origen']
            &&(int)$k->tipo_movimiento===$c['tipo_movimiento']&&(int)$k->tipo_documento_id===$c['tipo_documento_id']
            &&is_string($k->folio_documento)&&trim($k->folio_documento)!==''&&mb_strlen($k->folio_documento)<=60
            &&(int)$k->origen_detalle_id>0,409,'Kardex de referencia incompatible.');
        $d=DB::table('inventario_operaciones_detalle')->where('id',$k->origen_detalle_id)->lockForUpdate()->first();
        abort_unless($d&&(int)$d->kardex_id===$kid&&(int)$d->producto_id===$producto&&(int)$d->bodega_id===$bodega,409,'Vínculo de origen no acreditado.');
        $o=DB::table('inventario_operaciones as o')->join('estados as e','e.id','=','o.estado_id')->where('o.id',$d->operacion_id)->select('o.*','e.codigo as estado_codigo')->lockForUpdate()->first();
        abort_unless($o&&(int)$o->sucursal_id===$sucursal&&$o->estado_codigo==='INV_CONFIRMADA'&&$o->regla_snapshot===$c['regla_origen']
            &&(int)$o->motivo_codigo_snapshot===$c['motivo_origen']&&(int)$o->referencia_tipo_documento_id===$c['tipo_documento_id']&&$o->referencia_folio===$k->folio_documento
            &&(int)$o->creado_por>0&&(int)$o->aprobado_por>0&&(int)$o->aprobado_por!==(int)$o->creado_por&&(int)$o->confirmado_por>0&&$o->token_confirmacion!==null&&$o->anulado_por===null,409,'Operación de origen no acreditada.');
        $n=$this->decimal($k->cantidad_documento);
        abort_unless($n->isPositive()&&$this->decimal($d->cantidad)->isEqualTo($n)
            &&$this->decimal($d->delta_disponible)->isEqualTo($n)&&$this->decimal($d->delta_fisico)->isEqualTo($n)
            &&$this->decimal($d->delta_transito)->isZero()&&$this->decimal($d->delta_comprometido)->isZero()
            &&$this->decimal($k->cantidad_actual)->minus($this->decimal($k->cantidad_anterior))->isEqualTo($n)
            &&$this->decimal($k->fisico_actual)->minus($this->decimal($k->fisico_anterior))->isEqualTo($n)
            &&$this->decimal($k->transito_actual)->isEqualTo($this->decimal($k->transito_anterior))
            &&$this->decimal($k->comprometido_actual)->isEqualTo($this->decimal($k->comprometido_anterior)),409,'Movimiento de origen inconsistente.');
        abort_if(DB::table('kardex')->where('movimiento_reversado_id',$kid)->lockForUpdate()->get(['id'])->isNotEmpty(),409,'Movimiento de origen revertido.');
        // Exclusividad conservadora: no usar una referencia con otra operación activa,
        // incluso si es de otro módulo. Las anuladas se conservan como histórico.
        $links=DB::table('inventario_operaciones as x')->leftJoin('estados as e','e.id','=','x.estado_id')->where('x.referencia_kardex_id',$kid)
            ->where(fn($z)=>$z->whereNull('e.codigo')->orWhere('e.codigo','<>','INV_ANULADA'));
        if($operacion!==null)$links->where('x.id','<>',$operacion);
        abort_if($links->lockForUpdate()->get(['x.id'])->isNotEmpty(),409,'Referencia ya utilizada por otra operación.');
        abort_unless($q->isLessThanOrEqualTo($n),409,'Cantidad excede movimiento acreditado.');
        return ['referencia_kardex_id'=>$kid,'referencia_tipo_documento_id'=>(int)$k->tipo_documento_id,
            'referencia_folio'=>$k->folio_documento,'operacion_origen_id'=>(int)$o->id,'cantidad_origen'=>(string)$n,'cantidad_solicitada'=>(string)$q];
    }
}
