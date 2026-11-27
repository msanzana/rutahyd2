<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/** V109G2B2B: concilia historial persistido de devoluciones simples.
 * El llamador debe acreditar el documento original bajo bloqueo de producto.
 */
final class LectorSaldoDevolucionReferencia
{
    private function dec(mixed $v): BigDecimal {
        abort_unless((is_string($v)||is_int($v))&&preg_match('/^-?(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),409,'Decimal histórico no exacto.');
        return BigDecimal::of($v)->toScale(8);
    }
    private function positivo(mixed $v): BigDecimal {
        $x=$this->dec($v);abort_unless($x->isPositive(),409,'Cantidad o costo no positivo.');return $x;
    }
    public function leer(int $sid,int $pid,int $bid,int $kid,string $cantidad,string $costo,int $doc,string $folio,string $origen,?int $propia=null): array {
        abort_unless(DB::transactionLevel()>0&&config('devoluciones.historial_verificado')===true&&min($sid,$pid,$bid,$kid,$doc)>0
            &&trim($folio)!==''&&in_array($origen,['COMPRA_DISPONIBLE','DESPACHO_RESERVA_712'],true),409,'Historial de devolución no habilitado.');
        $cost=$this->positivo($costo);$this->positivo($cantidad);
        abort_unless(DB::table('productos')->where('id',$pid)->lockForUpdate()->first(),409,'Producto inexistente.');
        $original=DB::table('kardex')->where('id',$kid)->lockForUpdate()->first();
        abort_unless($original&&(int)$original->sucursal_id===$sid&&(int)$original->producto_id===$pid&&(int)$original->bodega_id===$bid
            &&$this->positivo($original->cantidad_documento)->isEqualTo($cantidad)&&$this->positivo($original->costo_unitario_neto)->isEqualTo($cost),409,'Origen cambió.');
        $codes=$origen==='COMPRA_DISPONIBLE'?[602,605]:[601,606];
        $cfg=config('devoluciones.contratos',[]);$origins=config('devoluciones.origenes_verificados',[]);
        $clase=config('devoluciones.clase_operacion');$docInterno=config('devoluciones.tipo_documento_interno');
        abort_unless($clase==='AJUSTE'&&is_int($docInterno)&&$docInterno>0,409,'Contrato de persistencia de devoluciones no acreditado.');
        abort_unless(DB::table('tipo_documentos')->where('id',$docInterno)->where('activo',1)->where('familia','INTERNO')->where('codigo_sii',0)->exists(),409,'Documento interno no acreditado.');
        // Incluir referencias parciales o huérfanas; no ocultar corrupción por JOIN.
        $detailIds=DB::table('inventario_operaciones_detalle')->where('kardex_referencia_id',$kid)->lockForUpdate()->pluck('operacion_id')->all();
        $headers=DB::table('inventario_operaciones as o')->leftJoin('estados as e','e.id','=','o.estado_id')
            ->where(fn($q)=>$q->where('o.referencia_kardex_id',$kid)->orWhereIn('o.id',$detailIds))
            ->select('o.*','e.codigo as estado_codigo')->orderBy('o.id')->lockForUpdate()->get();
        $ids=$headers->pluck('id')->map(fn($id)=>(int)$id)->all();
        foreach($detailIds as $id)abort_unless(in_array((int)$id,$ids,true),409,'Detalle de devolución huérfano.');
        $rows=[];$own=null;
        foreach($headers as $o){
            $code=(int)$o->motivo_codigo_snapshot;$contract=$cfg[$code]??null;
            $policy=match($code){601,606=>'HISTORICO',602=>'ESPECIFICA',605=>'VIGENTE',default=>null};
            $entrada=in_array($code,[601,606],true);
            abort_unless(in_array($code,$codes,true)&&is_array($contract)&&is_string($contract['regla']??null)
                &&($origins[$code]['origen']??null)===$origen&&$o->clase===$clase&&(int)$o->sucursal_id===$sid
                &&$o->regla_snapshot===$contract['regla']&&$o->politica_cpp_snapshot===$policy
                &&(int)$o->referencia_kardex_id===$kid&&(int)$o->referencia_tipo_documento_id===$doc
                &&(string)$o->referencia_folio===$folio&&(int)$o->creado_por>0&&$o->token_creacion!==null,409,'Cabecera de devolución incompatible.');
            $lines=DB::table('inventario_operaciones_detalle')->where('operacion_id',$o->id)->lockForUpdate()->get();
            abort_unless($lines->count()===1,409,'Detalle de devolución ausente o múltiple.');$d=$lines->first();
            abort_unless((int)$d->producto_id===$pid&&(int)$d->bodega_id===$bid&&(int)$d->kardex_referencia_id===$kid,409,'Detalle de referencia incompatible.');
            $n=$this->positivo($d->cantidad);$unit=$this->positivo($d->costo_unitario_snapshot);$delta=$entrada?$n:$n->negated();
            abort_unless($this->dec($d->delta_disponible)->isEqualTo($delta)&&$this->dec($d->delta_fisico)->isEqualTo($delta)
                &&$this->dec($d->delta_transito)->isZero()&&$this->dec($d->delta_comprometido)->isZero()
                &&($policy==='VIGENTE'||$unit->isEqualTo($cost)),409,'Cantidad o costo de devolución no acreditado.');
            $confirmed=$o->estado_codigo==='INV_CONFIRMADA';$movement=null;
            $ks=DB::table('kardex')->where('origen_tipo','AJUSTE_INVENTARIO')->where('origen_detalle_id',$d->id)->lockForUpdate()->get();
            if($confirmed){
                abort_unless($ks->count()===1&&(int)$d->kardex_id===(int)$ks->first()->id&&$o->token_confirmacion!==null
                    &&(int)$o->confirmado_por>0&&$o->anulado_por===null&&(int)$o->aprobado_por>0&&(int)$o->aprobado_por!==(int)$o->creado_por,409,'Confirmación de devolución incompatible.');
                $k=$ks->first();$movement=(int)$k->id;
                abort_unless($movement>$kid&&(int)$k->sucursal_id===$sid&&(int)$k->producto_id===$pid&&(int)$k->bodega_id===$bid
                    &&$k->operacion_codigo===$o->regla_snapshot&&(int)$k->tipo_movimiento===($entrada?1:2)
                    &&(int)$k->tipo_documento_id===$docInterno&&is_string($o->numero_interno)&&trim($o->numero_interno)!==''
                    &&(string)$k->folio_documento===$o->numero_interno&&$k->token_idempotencia!==null
                    &&$this->positivo($k->cantidad_documento)->isEqualTo($n)&&$this->positivo($k->costo_unitario_neto)->isEqualTo($unit)
                    &&($policy!=='VIGENTE'||$this->dec($k->cpp_anterior)->isEqualTo($unit)),409,'Kardex de devolución no acreditado.');
                foreach([['cantidad_anterior','cantidad_actual',$delta],['fisico_anterior','fisico_actual',$delta],
                    ['transito_anterior','transito_actual',BigDecimal::zero()],['comprometido_anterior','comprometido_actual',BigDecimal::zero()]] as [$a,$b,$expected]){
                    $x=$this->dec($k->$a);$y=$this->dec($k->$b);
                    abort_unless(!$x->isNegative()&&!$y->isNegative()&&$y->minus($x)->isEqualTo($expected),409,'Variación de devolución incompatible.');
                }
                foreach(['anterior','actual'] as $s)abort_unless($this->dec($k->{'fisico_'.$s})->isEqualTo($this->dec($k->{'cantidad_'.$s})->plus($this->dec($k->{'transito_'.$s}))->plus($this->dec($k->{'comprometido_'.$s}))),409,'Identidad física incoherente.');
                abort_if(DB::table('kardex')->where('movimiento_reversado_id',$movement)->lockForUpdate()->get(['id'])->isNotEmpty(),409,'Devolución revertida; requiere conciliación especial.');
            }else{
                abort_unless(in_array($o->estado_codigo,['INV_PEND_APROBACION','INV_APROBADA','INV_ANULADA'],true)
                    &&$ks->isEmpty()&&$d->kardex_id===null&&$o->token_confirmacion===null&&$o->confirmado_por===null,409,'Operación no confirmada con movimiento.');
                if($o->estado_codigo==='INV_APROBADA')abort_unless((int)$o->aprobado_por>0&&(int)$o->aprobado_por!==(int)$o->creado_por,409,'Aprobación no acreditada.');
                if($o->estado_codigo==='INV_ANULADA')abort_unless((int)$o->anulado_por>0,409,'Anulación no acreditada.');
                else abort_unless($o->anulado_por===null,409,'Solicitud con anulación inconsistente.');
            }
            $row=['id'=>(int)$o->id,'sucursal_id'=>$sid,'producto_id'=>$pid,'referencia_kardex_id'=>$kid,'motivo_codigo'=>$code,
                'referencia_acreditada'=>true,'estado'=>$o->estado_codigo,'cantidad'=>(string)$n,'kardex_id'=>$movement,
                'creado_por'=>(int)$o->creado_por,'aprobado_por'=>(int)$o->aprobado_por,'movimiento_acreditado'=>$confirmed];
            if($propia!==null&&(int)$o->id===$propia){
                abort_unless(in_array($o->estado_codigo,['INV_PEND_APROBACION','INV_APROBADA'],true),409,'Solicitud propia no vigente.');
                $own=$n;
            }
            $rows[]=$row;
        }
        abort_unless($propia===null||$own!==null,409,'Solicitud propia no encontrada.');
        usort($rows,fn($a,$b)=>($a['kardex_id']??PHP_INT_MAX)<=>($b['kardex_id']??PHP_INT_MAX));
        $result=(new SaldoDevolucionReferencia())->conciliar($sid,$pid,$kid,$cantidad,$rows,$codes);
        return $result+['cantidad_propia'=>$own===null?'0.00000000':(string)$own,
            'disponible_para_propia'=>(string)$this->dec($result['disponible_para_solicitar'])->plus($own??BigDecimal::zero()),'historial_acreditado'=>true];
    }
}
