<?php
declare(strict_types=1);
namespace App\Services;
use Illuminate\Support\Facades\DB;

/** Documento operativo multipágina, no DTE; lectura exclusivamente. */
final class PdfRecepcionCompra
{
    private function val($v): string {return $v===null?'—':(string)$v;}
    private function text(string $s): string {
        $s=preg_replace('/[\x00-\x1f\x7f]/u',' ',$s)??'';
        $s=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$s);
        return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s===false?'':$s);
    }
    private function line(array &$out,string $s): void {
        $s=preg_replace('/\s+/u',' ',trim($s))??'';
        while(mb_strlen($s)>105){$out[]=mb_substr($s,0,105);$s=mb_substr($s,105);}$out[]=$s;
    }
    public function generar(string $kind,int $id): string {
        abort_unless(in_array($kind,['recepcion','consolidacion'],true),404);
        $table=$kind==='recepcion'?'recepciones':'recepciones_consolidadas';
        return DB::transaction(function()use($table,$kind,$id){
            $r=DB::table($table.' as r')->join('estados as e','e.id','=','r.estado_id')->join('proveedores as p','p.id','=','r.proveedor_id')
              ->join('bodegas as b','b.id','=','r.bodega_id')->leftJoin('ordenes_compra as oc','oc.id','=','r.orden_compra_id')
              ->where('r.id',$id)->where('e.codigo','<>','NULA')->first(['r.*','e.codigo as estado','p.nombre as proveedor','p.rut','b.nombre as bodega','oc.numero as oc']);
            abort_unless($r,404,'Documento no encontrado.');
            $title=$kind==='recepcion'?'RECEPCIÓN DE COMPRA':'CONSOLIDACIÓN DE RECEPCIONES';
            $lines=[];$this->line($lines,'Proveedor: '.$r->proveedor.' | RUT: '.$this->val($r->rut));$this->line($lines,'Orden de compra: '.$this->val($r->oc).' | Bodega: '.$r->bodega);
            $this->line($lines,'Estado: '.$r->estado.' | Fecha: '.$this->val($kind==='recepcion'?$r->fecha_recepcion:$r->fecha_consolidacion));
            if(in_array($r->estado,['ACTIVA','PENDIENTE'],true))$lines[]='BORRADOR - NO CONFIRMADO';
            $ids=[$id];
            if($kind==='recepcion'){
                $doc=DB::table('compras as c')->join('tipo_documentos as t','t.id','=','c.documento_id')->where('c.id',$r->compra_id)->first(['c.folio','c.fecha_documento','c.total_informado','t.nombre']);
                if($doc)$this->line($lines,'Documento: '.$doc->nombre.' | Folio: '.$doc->folio.' | Emisión: '.$doc->fecha_documento.' | Total informado: '.$this->val($doc->total_informado));
                $rows=DB::table('recepcion_detalle as d')->join('productos as p','p.id','=','d.producto_id')->where('d.recepcion_id',$id)->orderBy('d.numero_linea')->get(['d.*','p.sku','p.nombre']);
            }else{
                $receipts=DB::table('recepciones as r')->join('compras as c','c.id','=','r.compra_id')->join('estados as e','e.id','=','r.estado_id')->where('r.recepcion_consolidada_id',$id)->where('e.codigo','<>','NULA')->orderBy('r.id')->get(['r.id','r.numero_interno','c.folio','e.codigo as estado']);
                $ids=$receipts->pluck('id')->all();$lines[]='RECEPCIONES INCLUIDAS';
                foreach($receipts as $rec)$this->line($lines,$rec->numero_interno.' | Folio '.$rec->folio.' | '.$rec->estado);
                $rows=DB::table('recepciones_consolidadas_detalle as d')->join('productos as p','p.id','=','d.producto_id')->where('d.recepcion_consolidada_id',$id)->orderBy('d.numero_linea')->get(['d.*','p.sku','p.nombre']);
            }
            $lines[]='';$lines[]='PRODUCTOS Y CANTIDADES';
            foreach($rows as $d){
                $this->line($lines,$d->numero_linea.'. '.$d->sku.' | '.$d->nombre);
                $this->line($lines,'Documentada: '.$d->cantidad_documentada.' | Recibida: '.$d->cantidad_recibida.' | Aceptada: '.$d->cantidad_aceptada.' | Rechazada: '.$d->cantidad_rechazada);
                $this->line($lines,'Costo neto: '.$this->val($kind==='recepcion'?$d->costo_unitario_neto:$d->costo_unitario_neto_ponderado).' | Neto: '.$d->monto_neto.' | Exento: '.$d->monto_exento.' | IVA: '.$d->monto_iva.' | Total: '.$d->total);
                $lines[]='';
            }
            $lines[]='DIFERENCIAS REGISTRADAS';
            $dif=DB::table('recepcion_diferencias')->whereIn('recepcion_id',$ids)->orderBy('id')->get();
            if($dif->isEmpty())$lines[]='Sin diferencias registradas.';
            foreach($dif as $d)$this->line($lines,'Recepción '.$d->recepcion_id.' | '.$d->tipo_diferencia.' | Cantidad '.$d->cantidad_diferencia.' | '.$d->motivo.' | '.$d->estado_resolucion);
            if($kind==='consolidacion'){
                $lines[]='SOLICITUDES DE REGULARIZACIÓN';
                foreach(DB::table('solicitudes_regularizacion')->where('recepcion_consolidada_id',$id)->where('estado','<>','NULA')->orderBy('id')->get() as $s)$this->line($lines,$s->numero_interno.' | '.$s->tipo_solicitud.' | '.$s->estado.' | Importe propuesto '.$s->monto_total);
            }
            $lines[]='';$lines[]='TOTALES DE DOCUMENTOS (CLP; no equivalen a valoración de existencias)';
            foreach(['subtotal_neto'=>'Neto','total_descuentos'=>'Descuentos','total_exento'=>'Exento','total_iva'=>'IVA','otros_impuestos'=>'Otros impuestos','total'=>'TOTAL'] as $k=>$label)$this->line($lines,$label.': '.$this->val($r->$k));
            if($r->observaciones)$this->line($lines,'Observaciones: '.$r->observaciones);
            return $this->render($title,$r->numero_interno,$lines);
        });
    }
    private function render(string $title,string $num,array $lines): string {
        $pages=array_chunk($lines,38);$objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];$kids=[];
        foreach($pages as $i=>$page){
            $pid=4+2*$i;$sid=$pid+1;$kids[]=$pid.' 0 R';
            $s="0.24 0.10 0.43 rg 0 766 842 76 re f\n";
            $s.="1 1 1 rg BT /F1 19 Tf 36 812 Td (".$this->text('H&D | '.$title).") Tj ET\n";
            $s.="1 1 1 rg BT /F1 12 Tf 36 787 Td (".$this->text($num.' | Documento interno, no DTE').") Tj ET\n";
            $y=741;foreach($page as $line){$s.="0.15 0.12 0.22 rg BT /F1 10 Tf 36 $y Td (".$this->text($line).") Tj ET\n";$y-=18;}
            $s.="0.24 0.10 0.43 rg BT /F1 9 Tf 36 28 Td (".$this->text('Ruta H&D | Página '.($i+1).' / '.count($pages)).") Tj ET\n";
            $objects[$pid]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $sid 0 R >>";
            $objects[$sid]='<< /Length '.strlen($s)." >>\nstream\n".$s."endstream";
        }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];
        foreach($objects as $i=>$obj){$offsets[$i]=strlen($pdf);$pdf.="$i 0 obj\n$obj\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".count($offsets)."\n0000000000 65535 f \n";
        for($i=1;$i<count($offsets);$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
        return $pdf."trailer\n<< /Size ".count($offsets)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
