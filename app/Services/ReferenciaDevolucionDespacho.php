<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/** V109G2B1: origen comercial de devolución de entrada histórica.
 * Acredita despacho físico 712 vinculado a guía interna y nota de venta.
 * No acredita recepción de devolución, nota de crédito ni aceptación SII.
 */
final class ReferenciaDevolucionDespacho
{
    public static function habilitado(): bool {
        return config('devoluciones.referencias_verificadas')===true
            &&config('devoluciones.integracion_comercial_verificada')===true
            &&config('devoluciones.aislamiento_verificado')===true
            &&ReferenciaDespachoReserva::habilitado();
    }
    private function qty(mixed $v): BigDecimal {
        abort_unless((is_string($v)||is_int($v))&&preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),409,'Valor histórico no exacto.');
        return BigDecimal::of($v)->toScale(8);
    }
    public function verificar(int $kid,int $sid,int $motivo): array {
        abort_unless(self::habilitado()&&DB::transactionLevel()>0&&$kid>0&&$sid>0,409,'Referencia comercial no habilitada.');
        $contract=config('devoluciones.origenes_verificados',[])[$motivo]??null;
        abort_unless(in_array($motivo,[601,606],true)&&is_array($contract)&&($contract['origen']??null)==='DESPACHO_RESERVA_712',409,'Origen no acreditado para este motivo.');
        $initial=DB::table('kardex')->where('id',$kid)->where('sucursal_id',$sid)->first();
        abort_unless($initial,404,'Movimiento no disponible.');
        $pid=(int)$initial->producto_id;$bid=(int)$initial->bodega_id;
        abort_unless($pid>0&&$bid>0&&DB::table('productos')->where('id',$pid)->where('activo',1)->lockForUpdate()->first(),409,'Producto no disponible.');
        abort_unless(DB::table('bodegas as b')->join('inventario_bodegas_sucursales as bs','bs.bodega_id','=','b.id')->where('b.id',$bid)->where('b.activo',1)->where('bs.sucursal_id',$sid)->lockForUpdate()->first(),404,'Bodega no disponible.');
        $k=DB::table('kardex')->where('id',$kid)->lockForUpdate()->first();
        abort_unless($k&&(int)$k->sucursal_id===$sid&&(int)$k->producto_id===$pid&&(int)$k->bodega_id===$bid
            &&$k->origen_tipo==='AJUSTE_INVENTARIO'&&$k->operacion_codigo==='DESPACHAR'
            &&(int)$k->tipo_movimiento===config('reservas.tipo_kardex_despachar')&&(int)$k->origen_detalle_id>0,409,'Salida original incompatible.');
        $d=DB::table('inventario_operaciones_detalle')->where('id',$k->origen_detalle_id)->lockForUpdate()->first();
        abort_unless($d&&(int)$d->kardex_id===$kid&&(int)$d->producto_id===$pid&&(int)$d->bodega_id===$bid,409,'Detalle de despacho no acreditado.');
        $o=DB::table('inventario_operaciones as o')->join('estados as e','e.id','=','o.estado_id')->where('o.id',$d->operacion_id)->select('o.*','e.codigo as estado_codigo')->lockForUpdate()->first();
        abort_unless($o&&(int)$o->sucursal_id===$sid&&$o->clase==='RESERVA'&&(int)$o->motivo_codigo_snapshot===712&&$o->estado_codigo==='INV_CONFIRMADA'
            &&$o->regla_snapshot==='DESPACHAR'&&$o->politica_cpp_snapshot==='VIGENTE'&&(int)$o->creado_por>0&&(int)$o->aprobado_por>0
            &&(int)$o->creado_por!==(int)$o->aprobado_por&&(int)$o->confirmado_por>0&&$o->token_confirmacion!==null&&$o->anulado_por===null,409,'Despacho no confirmado.');
        $links=DB::table('inventario_reservas_operaciones')->where('operacion_id',$o->id)->lockForUpdate()->get();
        abort_unless($links->count()===1,409,'Reserva de despacho ambigua.');
        $h=DB::table('inventario_reservas')->where('id',$links->first()->reserva_id)->where('sucursal_id',$sid)->lockForUpdate()->first();
        abort_unless($h,409,'Reserva de origen no disponible.');
        $ref=(new ReferenciaDespachoReserva())->verificar($h,$o,$sid);
        $ledger=(new LectorSaldoReservaInventario())->leer((int)$h->id,$pid,$bid,$sid);
        $q=$this->qty($k->cantidad_documento);$cost=$this->qty($k->costo_unitario_neto);
        abort_unless($q->isPositive()&&$cost->isPositive()&&$q->isEqualTo($this->qty($d->cantidad))
            &&$q->isLessThanOrEqualTo($this->qty($ledger['despachada']))
            &&$cost->isEqualTo($this->qty($d->costo_unitario_snapshot))&&$cost->isEqualTo($this->qty($k->cpp_anterior))&&$cost->isEqualTo($this->qty($k->cpp_actual))
            &&(int)$k->tipo_documento_id===$ref['tipo_documento_id']&&$k->folio_documento===$ref['folio'],409,'Costo o cantidad de despacho no acreditado.');
        abort_if(DB::table('kardex')->where('movimiento_reversado_id',$kid)->lockForUpdate()->get(['id'])->isNotEmpty(),409,'Despacho revertido.');
        // No admitir historial comercial ambiguo: todos los usos del origen deben
        // conciliarse, incluso los realizados por un módulo distinto.
        $usos=DB::table('inventario_operaciones as x')->leftJoin('estados as e','e.id','=','x.estado_id')->where('x.referencia_kardex_id',$kid)
            ->select('x.*','e.codigo as estado_codigo')->lockForUpdate()->get();
        foreach($usos as $u){
            abort_unless($u->estado_codigo==='INV_ANULADA'&&$u->token_confirmacion===null&&$u->confirmado_por===null
                &&!DB::table('inventario_operaciones_detalle')->where('operacion_id',$u->id)->whereNotNull('kardex_id')->exists(),409,'Origen utilizado; requiere conciliación de devoluciones previas.');
        }
        return ['referencia_kardex_id'=>$kid,'operacion_origen_id'=>(int)$o->id,'reserva_id'=>(int)$h->id,'nota_venta_id'=>(int)$h->nota_venta_id,
            'guia_id'=>$ref['guia_id'],'referencia_tipo_documento_id'=>$ref['tipo_documento_id'],'referencia_folio'=>$ref['folio'],
            'producto_id'=>$pid,'bodega_id'=>$bid,'sucursal_id'=>$sid,'cantidad_original'=>(string)$q,'costo_historico'=>(string)$cost,
            'saldo_solicitable'=>(string)$q,'origen_fisico_acreditado'=>true,'recepcion_devolucion_acreditada'=>false,
            'documento_tributario_acreditado'=>false,'solo_lectura'=>true];
    }
}
