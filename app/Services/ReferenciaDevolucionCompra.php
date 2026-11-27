<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/** V109G2B2A: acredita origen de compra disponible, no autoriza devolución ni DTE.
 * Consolidaciones con varias líneas de compra se mantienen bloqueadas hasta
 * contar con asignación documental individual de la cantidad y costo.
 */
final class ReferenciaDevolucionCompra
{
    private function qty(mixed $v): BigDecimal {
        abort_unless((is_string($v)||is_int($v))&&preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),409,'Valor de compra no exacto.');
        return BigDecimal::of($v)->toScale(8);
    }
    private function estado(object $row,string $code): bool {
        return DB::table('estados')->where('id',$row->estado_id)->where('codigo',$code)->exists();
    }
    private function compra(object $r,int $sid,int $bid): array {
        abort_unless((int)$r->sucursal_id===$sid&&(int)$r->bodega_id===$bid&&(int)$r->stock_aplicado===1
            &&is_string($r->token_confirmacion)&&$r->token_confirmacion!=='',409,'Recepción no acreditada.');
        $c=DB::table('compras')->where('id',$r->compra_id)->where('sucursal_id',$sid)->lockForUpdate()->first();
        $o=DB::table('ordenes_compra')->where('id',$r->orden_compra_id)->where('sucursal_id',$sid)->lockForUpdate()->first();
        abort_unless($c&&$o&&$this->estado($c,'RECEPCIONADA')&&$this->estado($o,'RECEPCIONADA')
            &&(int)$c->orden_compra_id===(int)$o->id&&(int)$c->proveedor_id===(int)$r->proveedor_id
            &&(int)$o->proveedor_id===(int)$r->proveedor_id&&(int)$c->documento_id>0&&trim((string)$c->folio)!=='',409,'Compra u orden no acreditada.');
        abort_unless(DB::table('tipo_documentos')->where('id',$c->documento_id)->where('documento_compra',1)->where('activo',1)->exists(),409,'Tipo de documento de compra incompatible.');
        return [$c,$o];
    }
    private function linea(object $d,object $o,int $pid): array {
        $q=$this->qty($d->cantidad_aceptada);$cost=$this->qty($d->costo_unitario_neto);
        abort_unless((int)$d->producto_id===$pid&&$d->presentacion_id===null&&$this->qty($d->unidades_por_presentacion_snapshot)->isEqualTo(1)
            &&$q->isPositive()&&$cost->isPositive()&&$q->isLessThanOrEqualTo($this->qty($d->cantidad_recibida)),409,'Detalle de compra no conciliable.');
        $ol=DB::table('detalles_ordenes_compra')->where('id',$d->detalle_orden_compra_id)->where('orden_compra_id',$o->id)->where('producto_id',$pid)->lockForUpdate()->first();
        abort_unless($ol,409,'Detalle de orden incompatible.');
        return [$q,$cost];
    }
    private function comprobarKardex(object $k,BigDecimal $q,BigDecimal $cost,bool $libera): void {
        $a0=$this->qty($k->cantidad_anterior);$a1=$this->qty($k->cantidad_actual);
        $t0=$this->qty($k->transito_anterior);$t1=$this->qty($k->transito_actual);
        $c0=$this->qty($k->comprometido_anterior);$c1=$this->qty($k->comprometido_actual);
        $f0=$this->qty($k->fisico_anterior);$f1=$this->qty($k->fisico_actual);
        abort_unless($q->isEqualTo($this->qty($k->cantidad_documento))&&$cost->isEqualTo($this->qty($k->costo_unitario_neto))
            &&$a1->minus($a0)->isEqualTo($q)&&$c0->isEqualTo($c1)
            &&$t1->minus($t0)->isEqualTo($libera?$q->negated():'0')&&$f1->minus($f0)->isEqualTo($libera?'0':$q)
            &&$f0->isEqualTo($a0->plus($t0)->plus($c0))&&$f1->isEqualTo($a1->plus($t1)->plus($c1)),409,'Movimiento de ingreso incoherente.');
    }
    private function comprobarTransito(object $d,object $c,object $k,BigDecimal $q,BigDecimal $cost): void {
        $rows=DB::table('kardex')->where('origen_tipo','RECEPCION')->where('origen_detalle_id',$d->id)->where('operacion_codigo','ENTRADA_TRANSITO')->lockForUpdate()->get();
        abort_unless($rows->count()===1,409,'Tránsito original ambiguo.');
        $t=$rows->first();
        abort_unless((int)$t->id<(int)$k->id&&(int)$t->tipo_movimiento===3&&(int)$t->sucursal_id===(int)$k->sucursal_id
            &&(int)$t->producto_id===(int)$k->producto_id&&(int)$t->bodega_id===(int)$k->bodega_id
            &&(int)$t->tipo_documento_id===(int)$c->documento_id&&(string)$t->folio_documento===(string)$c->folio
            &&$this->qty($t->cantidad_documento)->isEqualTo($q)&&$this->qty($t->costo_unitario_neto)->isEqualTo($cost)
            &&$this->qty($t->cantidad_actual)->isEqualTo($this->qty($t->cantidad_anterior))
            &&$this->qty($t->comprometido_actual)->isEqualTo($this->qty($t->comprometido_anterior))
            &&$this->qty($t->transito_actual)->minus($this->qty($t->transito_anterior))->isEqualTo($q)
            &&$this->qty($t->fisico_actual)->minus($this->qty($t->fisico_anterior))->isEqualTo($q),409,'Tránsito de compra no acreditado.');
        abort_if(DB::table('kardex')->where('movimiento_reversado_id',$t->id)->exists(),409,'Tránsito de compra revertido.');
    }
    public function verificar(int $kid,int $sid,int $motivo): array {
        $cfg=config('devoluciones.origenes_verificados',[])[$motivo]??null;
        abort_unless(DB::transactionLevel()>0&&$kid>0&&$sid>0&&config('devoluciones.referencias_verificadas')===true
            &&config('devoluciones.integracion_comercial_verificada')===true&&config('devoluciones.aislamiento_verificado')===true
            &&in_array($motivo,[602,605],true)&&is_array($cfg)&&($cfg['origen']??null)==='COMPRA_DISPONIBLE',409,'Contrato de devolución a proveedor no acreditado.');
        $hint=DB::table('kardex')->where('id',$kid)->where('sucursal_id',$sid)->first();abort_unless($hint,404,'Origen no disponible.');
        $pid=(int)$hint->producto_id;$bid=(int)$hint->bodega_id;
        abort_unless(DB::table('productos')->where('id',$pid)->where('activo',1)->lockForUpdate()->first(),409,'Producto no disponible.');
        abort_unless(DB::table('bodegas as b')->join('inventario_bodegas_sucursales as bs','bs.bodega_id','=','b.id')->where('b.id',$bid)->where('b.activo',1)->where('bs.sucursal_id',$sid)->lockForUpdate()->first(),404,'Bodega no disponible.');
        $k=DB::table('kardex')->where('id',$kid)->lockForUpdate()->first();
        abort_unless($k&&(int)$k->sucursal_id===$sid&&(int)$k->producto_id===$pid&&(int)$k->bodega_id===$bid
            &&(int)$k->tipo_movimiento===1&&(int)$k->origen_detalle_id>0&&$k->token_idempotencia!==null,409,'Entrada de compra no acreditada.');
        $consolidado=$k->origen_tipo==='CONSOLIDACION_RECEPCION'&&$k->operacion_codigo==='CONSOLIDAR_TRANSITO';
        abort_unless($consolidado||($k->origen_tipo==='RECEPCION'&&in_array($k->operacion_codigo,['ENTRADA_DIRECTA','LIBERAR_TRANSITO_DIRECTO'],true)),409,'Movimiento no incorporado al disponible.');
        $matches=DB::table('kardex')->where('origen_tipo',$k->origen_tipo)->where('origen_detalle_id',$k->origen_detalle_id)->where('tipo_movimiento',1)->lockForUpdate()->get();
        abort_unless($matches->count()===1&&(int)$matches->first()->id===$kid,409,'Ingreso disponible duplicado.');
        $con=null;
        if($consolidado){
            $cd=DB::table('recepciones_consolidadas_detalle')->where('id',$k->origen_detalle_id)->lockForUpdate()->first();
            abort_unless($cd&&(int)$cd->producto_id===$pid,409,'Detalle consolidado no disponible.');
            $con=DB::table('recepciones_consolidadas')->where('id',$cd->recepcion_consolidada_id)->where('sucursal_id',$sid)->lockForUpdate()->first();
            abort_unless($con&&(int)$con->bodega_id===$bid&&$this->estado($con,'CONSOLIDADA')&&$con->token_confirmacion!==null&&(int)$con->consolidado_por>0,409,'Consolidación no confirmada.');
            $doc=DB::table('tipo_documentos')->where('id',$k->tipo_documento_id)->where('codigo','CONSOLIDACION_RECEPCION')->where('codigo_sii',0)->where('documento_compra',1)->where('familia','INTERNO')->first();
            abort_unless($doc&&(string)$k->folio_documento===(string)$con->numero_interno,409,'Referencia interna de consolidación incompatible.');
            $rows=DB::table('recepcion_detalle as d')->join('recepciones as r','r.id','=','d.recepcion_id')->where('r.recepcion_consolidada_id',$con->id)
                ->where('d.detalle_orden_compra_id',$cd->detalle_orden_compra_id)->where('d.producto_id',$pid)->select('d.*')->lockForUpdate()->get();
            abort_unless($rows->count()===1,409,'Consolidación requiere asignación a documentos de compra individuales.');
            $d=$rows->first();
        }else{
            $d=DB::table('recepcion_detalle')->where('id',$k->origen_detalle_id)->lockForUpdate()->first();
            abort_unless($d,409,'Detalle de recepción no disponible.');
        }
        $r=DB::table('recepciones')->where('id',$d->recepcion_id)->lockForUpdate()->first();
        abort_unless($r,409,'Recepción inexistente.');[$c,$o]=$this->compra($r,$sid,$bid);[$q,$cost]=$this->linea($d,$o,$pid);
        if($consolidado){
            abort_unless((int)$con->orden_compra_id===(int)$o->id&&(int)$con->proveedor_id===(int)$c->proveedor_id
                &&in_array(DB::table('estados')->where('id',$r->estado_id)->value('codigo'),['RECEPCIONADA','DESCUADRADA','FINALIZADA'],true)
                &&$this->qty($cd->cantidad_aceptada)->isEqualTo($q)&&$this->qty($cd->costo_unitario_neto_ponderado)->isEqualTo($cost),409,'Detalle consolidado no atribuible a una compra.');
            abort_if(DB::table('kardex')->where('origen_tipo','RECEPCION')->where('origen_detalle_id',$d->id)->where('tipo_movimiento',1)->exists(),409,'Recepción también liberada directamente.');
        }else{
            abort_unless($r->recepcion_consolidada_id===null&&$this->estado($r,'RECEPCIONADA')&&(int)$r->recepcion_tipo===1
                &&$this->qty($d->cantidad_rechazada)->isZero()&&$q->isEqualTo($this->qty($d->cantidad_recibida))
                &&(int)$k->tipo_documento_id===(int)$c->documento_id&&(string)$k->folio_documento===(string)$c->folio,409,'Recepción directa incompatible.');
        }
        $libera=$consolidado||$k->operacion_codigo==='LIBERAR_TRANSITO_DIRECTO';
        $this->comprobarKardex($k,$q,$cost,$libera);
        if($libera)$this->comprobarTransito($d,$c,$k,$q,$cost);
        else abort_if(DB::table('kardex')->where('origen_tipo','RECEPCION')->where('origen_detalle_id',$d->id)->where('operacion_codigo','ENTRADA_TRANSITO')->exists(),409,'Entrada directa con tránsito previo.');
        abort_if(DB::table('kardex')->where('movimiento_reversado_id',$kid)->exists(),409,'Entrada original revertida.');
        // No se ignoran operaciones que ya referencian la entrada.
        $used=DB::table('inventario_operaciones as x')->leftJoin('estados as e','e.id','=','x.estado_id')->where('x.referencia_kardex_id',$kid)->select('x.*','e.codigo as estado_codigo')->lockForUpdate()->get();
        foreach($used as $u)abort_unless($u->estado_codigo==='INV_ANULADA'&&$u->confirmado_por===null&&$u->token_confirmacion===null
            &&!DB::table('inventario_operaciones_detalle')->where('operacion_id',$u->id)->whereNotNull('kardex_id')->exists(),409,'Origen utilizado: conciliar historial antes de devolver.');
        return ['referencia_kardex_id'=>$kid,'compra_id'=>(int)$c->id,'recepcion_id'=>(int)$r->id,'recepcion_detalle_id'=>(int)$d->id,
            'consolidacion_id'=>$con?(int)$con->id:null,'orden_compra_id'=>(int)$o->id,'proveedor_id'=>(int)$c->proveedor_id,
            'tipo_documento_id'=>(int)$c->documento_id,'folio'=>(string)$c->folio,'producto_id'=>$pid,'bodega_id'=>$bid,'sucursal_id'=>$sid,
            'cantidad_original'=>(string)$q,'costo_especifico'=>(string)$cost,'origen_fisico_acreditado'=>true,
            'nota_credito_acreditada'=>false,'solo_lectura'=>true];
    }
}
