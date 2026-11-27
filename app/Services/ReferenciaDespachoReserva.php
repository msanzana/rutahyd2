<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/** V109E4C2B: acredita la relación guía/nota/reserva/operación en lectura.
 * Contrato candidato V110; no autoriza emisión ni crea documentos.
 */
final class ReferenciaDespachoReserva
{
    public static function habilitado(): bool {
        return config('reservas.consumo_lectura_verificado')===true
            &&config('reservas.estado_consumida')==='CONSUMIDA'
            &&is_int(config('reservas.tipo_kardex_despachar'))&&config('reservas.tipo_kardex_despachar')>0
            &&is_int(config('reservas.tipo_documento_guia'))&&config('reservas.tipo_documento_guia')>0
            &&is_string(config('reservas.estado_guia_despachada'))&&trim(config('reservas.estado_guia_despachada'))!=='';
    }
    private function qty(mixed $v): BigDecimal {
        abort_unless(is_string($v)||is_int($v),409,'Cantidad comercial no exacta.');
        abort_unless(preg_match('/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D',(string)$v),409,'Cantidad comercial inválida.');
        return BigDecimal::of($v)->toScale(8);
    }
    public function verificar(object $h,object $o,int $sid): array {
        abort_unless(DB::transactionLevel()>0&&self::habilitado(),409,'Contrato de despacho no verificado.');
        $type=config('reservas.tipo_documento_guia');
        abort_unless((int)$h->sucursal_id===$sid&&(int)$o->sucursal_id===$sid&&(int)$h->nota_venta_id>0&&(int)$o->motivo_codigo_snapshot===712&&(int)$o->referencia_tipo_documento_id===$type&&is_string($o->referencia_folio)&&trim($o->referencia_folio)!=='',409,'Referencia de despacho incompleta.');
        $note=DB::table('notas_venta')->where('id',$h->nota_venta_id)->where('sucursal_id',$sid)->lockForUpdate()->first();
        abort_unless($note,409,'Nota sin atribución comercial.');
        // Folio global único para este contrato; no resolver folios ambiguos.
        $guides=DB::table('guias_despacho')->where('folio_interno',$o->referencia_folio)->lockForUpdate()->get();
        abort_unless($guides->count()===1,409,'Guía inexistente o ambigua.');
        $g=$guides->first();
        abort_unless((int)$g->nota_venta_id===(int)$note->id&&$g->estado===config('reservas.estado_guia_despachada')&&$g->bodega_destino_id===null&&(int)$g->bodega_origen_id>0,409,'Guía incompatible con despacho de venta.');
        $bid=(int)$g->bodega_origen_id;
        abort_unless(DB::table('inventario_bodegas_sucursales')->where('sucursal_id',$sid)->where('bodega_id',$bid)->lockForUpdate()->first(),409,'Bodega de guía no atribuida.');
        $refs=DB::table('inventario_operaciones')->where('motivo_codigo_snapshot',712)->where('referencia_tipo_documento_id',$type)->where('referencia_folio',$o->referencia_folio)->lockForUpdate()->get();
        // Se conserva incluso histórico anulado: no reutilizar folio sin política explícita.
        abort_unless($refs->count()===1&&(int)$refs->first()->id===(int)$o->id,409,'Guía utilizada por otra operación.');
        $guideRows=DB::table('detalles_guias_despacho as d')->join('presentaciones_productos as p','p.id','=','d.presentacion_id')->where('d.guia_despacho_id',$g->id)->select('d.cantidad','p.producto_id','p.unidades_por_envase')->lockForUpdate()->get();
        $expected=[];
        foreach($guideRows as $l){
            $pid=(int)$l->producto_id;$q=$this->qty($l->cantidad);
            abort_unless($pid>0&&$q->isPositive()&&$this->qty($l->unidades_por_envase)->isEqualTo(1),409,'Presentación de guía no unitaria.');
            $expected[$pid]=($expected[$pid]??BigDecimal::zero())->plus($q);
        }
        abort_unless($expected!==[],409,'Guía sin detalle.');
        $actual=[];
        $ops=DB::table('inventario_operaciones_detalle')->where('operacion_id',$o->id)->lockForUpdate()->get();
        foreach($ops as $d){
            $pid=(int)$d->producto_id;$q=$this->qty($d->cantidad);
            abort_unless($pid>0&&(int)$d->bodega_id===$bid&&$q->isPositive()&&!isset($actual[$pid]),409,'Detalle de despacho duplicado o de otra bodega.');
            $actual[$pid]=$q;
        }
        abort_unless(count($actual)===count($expected),409,'Guía y operación tienen productos distintos.');
        foreach($expected as $pid=>$q)abort_unless(isset($actual[$pid])&&$actual[$pid]->isEqualTo($q),409,'Cantidad de guía no coincide con operación.');
        return ['guia_id'=>(int)$g->id,'folio'=>$g->folio_interno,'tipo_documento_id'=>$type,'bodega_id'=>$bid];
    }
}
