<?php
declare(strict_types=1);
namespace App\Services;

/** Comprobante interno, no DTE. Datos exclusivamente de consulta autorizada. */
final class ComprobanteTraspasoPdf
{
    private function literal(string $s): string {
        $s=preg_replace('/[\x00-\x1F\x7F]/u',' ',$s)??'';
        $latin=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$s);
        return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$latin===false?'':$latin);
    }
    private function text(string $s,int $x,int $y,int $size=10,string $font='F1',string $color='0.17 0.14 0.23'): string {
        return "$color rg BT /$font $size Tf $x $y Td (".$this->literal($s).") Tj ET\n";
    }
    private function wrap(string $s,int $width): array {
        $s=trim(preg_replace('/\s+/u',' ',$s)??'');$out=[];
        while(mb_strlen($s)>$width){$part=mb_substr($s,0,$width);$cut=mb_strrpos($part,' ');if($cut===false||$cut<10)$cut=$width;$out[]=mb_substr($s,0,$cut);$s=ltrim(mb_substr($s,$cut));}
        $out[]=$s;return $out;
    }
    public function generar(array $data): string {
        $h=$data['traspaso'];$number=(string)$h->numero_interno;
        $rows=[];
        $add=function(string $s)use(&$rows){foreach($this->wrap($s,78) as $line)$rows[]=$line;};
        $add('Estado del traspaso: '.$h->estado_traspaso.' | '.$h->estado_operacion_nombre);
        $add('Modalidad: '.($h->modalidad==='INMEDIATO'?'Inmediato':'Despacho y recepción'));
        $add('Origen: '.$h->bodega_origen);$add('Destino: '.$h->bodega_destino);
        $add('Fecha de solicitud: '.$h->creado_en);$add('Justificación: '.$h->justificacion);
        $rows[]='';$rows[]='DETALLE DE PRODUCTOS';
        foreach($data['detalles'] as $l){
            $rows[]='';$add($l['sku'].' | '.$l['producto']);
            $add('Solicitada: '.$l['cantidad_solicitada'].' | Despachada: '.$l['cantidad_despachada']);
            $add('Recibida: '.$l['cantidad_recibida'].' | Regularizada: '.$l['cantidad_regularizada']);
            $add('Pendiente despacho: '.$l['pendiente_despacho'].' | Pendiente recepción: '.$l['pendiente_recepcion']);
        }
        $rows[]='';$rows[]='OPERACIONES REGISTRADAS';
        foreach($data['operaciones'] as $o){
            $add($o->numero_interno.' | '.$o->etapa.' | '.$o->estado_nombre);
            if($o->confirmado_en)$add('Confirmado: '.$o->confirmado_en);
        }
        $rows[]='';$add('Comprobante de consulta; refleja el estado al momento de generarse.');
        $pages=array_chunk($rows,39);
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];$kids=[];
        foreach($pages as $i=>$page){
            $pid=5+$i*2;$sid=$pid+1;$kids[]=$pid.' 0 R';
            $stream="0.30 0.15 0.48 rg 0 750 595 92 re f\n";
            $stream.=$this->text('RUTA H&D',36,810,12,'F2','1 1 1');
            $stream.=$this->text('COMPROBANTE INTERNO DE TRASPASO',36,785,15,'F2','1 1 1');
            $stream.=$this->text($number,36,765,11,'F2','1 1 1');
            $stream.=$this->text('SIN VALIDEZ TRIBUTARIA - NO ES GUIA DE DESPACHO',36,728,10,'F2');
            $y=704;foreach($page as $line){$stream.=$this->text($line,36,$y,10);$y-=16;}
            $stream.="0.75 0.70 0.82 RG 36 62 m 559 62 l S\n";
            $stream.=$this->text('Documento interno | Página '.($i+1).' de '.count($pages),36,44,9);
            $objects[$pid]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $sid 0 R >>";
            $objects[$sid]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream";
        }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($pages).' >>';ksort($objects);
        $pdf="%PDF-1.4\n";$offs=[0];foreach($objects as $i=>$obj){$offs[$i]=strlen($pdf);$pdf.="$i 0 obj\n$obj\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".count($offs)."\n0000000000 65535 f \n";for($i=1;$i<count($offs);$i++)$pdf.=sprintf("%010d 00000 n \n",$offs[$i]);
        return $pdf."trailer\n<< /Size ".count($offs)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
