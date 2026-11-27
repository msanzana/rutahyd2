<?php
declare(strict_types=1);
namespace App\Services;

/** V109E5B2: comprobante documental interno; no DTE ni autorización de movimientos. */
final class ComprobanteReservaPdf
{
    private function latin(string $s): string {
        $s=preg_replace('/[\x00-\x1F\x7F]/u',' ',$s)??'';
        return iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$s)?:'';
    }
    private function text(string $s,int $x,int $y,int $size=10,string $font='F1',string $color='0.17 0.14 0.23'): string {
        $s=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s);
        return "$color rg BT /$font $size Tf $x $y Td ($s) Tj ET\n";
    }
    private function lines(string $s,int $width=78): array {
        return explode("\n",wordwrap($this->latin($s),$width,"\n",true));
    }
    public function generar(array $data): string {
        $h=(object)$data['reserva'];
        $number=$this->latin((string)($h->numero_interno??$h->id));
        $blocks=[];
        $block=function(array $rows)use(&$blocks){$out=[];foreach($rows as $s)foreach($this->lines((string)$s) as $line)$out[]=$line;$blocks[]=$out;};
        $block(['DATOS DE LA RESERVA','Estado: '.$h->estado.(!empty($h->vencimiento_alcanzado)?' | Vencimiento alcanzado':''),'Sucursal ID: '.$h->sucursal_id.' | Nota de venta ID: '.($h->nota_venta_id??'Sin referencia'),'Creación: '.($h->creado_en??'Sin fecha').' | Vencimiento: '.($h->vence_en??'Sin fecha'),'Generado: '.$data['generado_en'],'']);
        $block(['PRODUCTOS Y BODEGAS']);
        foreach($data['detalles'] as $line){
            $l=(object)$line;
            $block([$l->sku.' | '.$l->producto,'Bodega: '.$l->bodega,'Cantidad solicitada: '.$l->cantidad_reservada,'Liberada: '.$l->cantidad_liberada.' | Despachada: '.$l->cantidad_despachada,'Solicitud pendiente: '.$l->cantidad_solicitada_pendiente,'Saldo documental: '.$l->saldo_documental,'']);
        }
        if(count($data['detalles'])===0)$block(['Sin productos.']);
        $block(['OPERACIONES ASOCIADAS']);
        foreach($data['operaciones'] as $operation){
            $o=(object)$operation;
            $block([($o->numero_interno??'Sin número').' | '.($o->motivo_nombre_snapshot??$o->motivo_codigo_snapshot).' | '.$o->estado,'Creación: '.($o->creado_en??'Pendiente').' | Aprobación: '.($o->aprobado_en??'Pendiente'),'Confirmación: '.($o->confirmado_en??'Pendiente'),'']);
        }
        if(count($data['operaciones'])===0)$block(['Sin operaciones visibles.']);
        $block(['El saldo documental no acredita disponibilidad ni autoriza movimientos.']);
        $pages=[];$page=[];
        foreach($blocks as $b){
            if($page&&count($page)+count($b)>37){$pages[]=$page;$page=[];}
            foreach($b as $line){if(count($page)===37){$pages[]=$page;$page=[];}$page[]=$line;}
        }
        if($page)$pages[]=$page;
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];$kids=[];
        foreach($pages as $i=>$page){
            $pid=5+$i*2;$sid=$pid+1;$kids[]=$pid.' 0 R';
            $stream="0.30 0.15 0.48 rg 0 750 595 92 re f\n";
            $stream.=$this->text('RUTA H&D',36,810,12,'F2','1 1 1');
            $stream.=$this->text('COMPROBANTE INTERNO DE RESERVA',36,785,15,'F2','1 1 1');
            $stream.=$this->text($number,36,765,10,'F1','1 1 1');
            $stream.=$this->text('SIN VALIDEZ TRIBUTARIA - NO ES DTE NI GUIA DE DESPACHO',36,728,10,'F2');
            $y=704;
            foreach($page as $line){
                $heading=in_array($line,['DATOS DE LA RESERVA','PRODUCTOS Y BODEGAS','OPERACIONES ASOCIADAS'],true);
                $stream.=$this->text($line,36,$y,10,$heading?'F2':'F1',$heading?'0.30 0.15 0.48':'0.17 0.14 0.23');$y-=16;
            }
            $stream.="0.75 0.70 0.82 RG 36 62 m 559 62 l S\n";
            $stream.=$this->text($this->latin('Consulta documental | Página '.($i+1).' de '.count($pages)),36,44,9);
            $objects[$pid]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $sid 0 R >>";
            $objects[$sid]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream";
        }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($pages).' >>';ksort($objects);
        $pdf="%PDF-1.4\n";$offs=[0];foreach($objects as $i=>$obj){$offs[$i]=strlen($pdf);$pdf.="$i 0 obj\n$obj\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".count($offs)."\n0000000000 65535 f \n";for($i=1;$i<count($offs);$i++)$pdf.=sprintf("%010d 00000 n \n",$offs[$i]);
        return $pdf."trailer\n<< /Size ".count($offs)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
