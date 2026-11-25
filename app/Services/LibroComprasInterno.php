<?php
declare(strict_types=1);
namespace App\Services;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/** Libro interno: una fila por documento real, montos positivos y signo separado.
 * No equivale a un RCV conciliado ni determina crédito fiscal. */
final class LibroComprasInterno
{
    public function registrar(int $compraId,string $numero): bool
    {
        abort_unless(DB::transactionLevel()>0,500,'El libro requiere una transacción.');
        $c=DB::table('compras')->where('id',$compraId)->lockForUpdate()->first();
        abort_unless($c,409,'Documento de compra inexistente.');
        $d=DB::table('tipo_documentos')->where('id',$c->documento_id)->first();
        abort_unless($d&&(int)$d->documento_compra===1,409,'Tipo de compra no habilitado.');
        // Guías y documentos internos no constituyen registro monetario de compra.
        if(in_array($d->familia,['INTERNO','GUIA'],true))return false;
        abort_unless(in_array((int)$d->codigo_sii,[33,34,39,41,46,56,61],true),409,'Documento sin clasificación de libro.');
        $p=DB::table('proveedores')->where('id',$c->proveedor_id)->first();
        $rut=trim((string)($p->rut??''));$name=trim((string)($p->razon_social??''));if($name==='')$name=trim((string)($p->nombre??''));
        abort_unless($p&&$rut!==''&&mb_strlen($rut)<=20&&$name!=='',422,'Complete RUT y razón social del proveedor antes de confirmar.');
        $sign=(int)$d->codigo_sii===61?-1:1;abort_unless((int)$c->signo_contable===$sign,409,'Signo contable incompatible con el documento.');
        $fields=['monto_neto'=>'subtotal_neto','monto_exento'=>'total_exento','monto_iva_recuperable'=>'iva_recuperable','monto_iva_no_recuperable'=>'iva_no_recuperable','monto_iva_uso_comun'=>'iva_uso_comun','monto_impuestos_adicionales'=>'otros_impuestos','monto_retenciones'=>'impuestos_retenidos','monto_total'=>'total'];
        $row=['compra_id'=>$c->id,'documento_id'=>$c->documento_id,'periodo_tributario'=>substr((string)$c->fecha_documento,0,7),'folio'=>$c->folio,
          'rut_emisor'=>$rut,'razon_social_emisor'=>$name,'fecha_documento'=>$c->fecha_documento,'fecha_ingreso'=>$c->fecha_ingreso,
          'fecha_vencimiento'=>$c->fecha_vencimiento,'numero_interno'=>$numero,'signo_contable'=>$sign,'estado_rcv'=>'INTERNO_NO_CONCILIADO'];
        foreach($fields as $to=>$from){$n=BigDecimal::of((string)$c->$from);abort_if($n->isNegative(),409,'Importe negativo en documento de compra.');$row[$to]=(string)$n;}
        // Preservar el snapshot ya registrado, no sobrescribir datos conciliados.
        $old=DB::table('libro_compras')->where('compra_id',$compraId)->lockForUpdate()->first();
        if($old){
            abort_unless((int)$old->documento_id===(int)$c->documento_id&&(string)$old->folio===(string)$c->folio&&(int)$old->signo_contable===$sign,409,'Registro previo del libro incompatible.');
            foreach($fields as $to=>$from)abort_unless(BigDecimal::of((string)$old->$to)->isEqualTo($row[$to]),409,'Importes del libro previamente registrados no coinciden.');
            return false;
        }
        DB::table('libro_compras')->insert($row);return true;
    }
}
