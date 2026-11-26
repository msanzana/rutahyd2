<?php
declare(strict_types=1);
namespace App\Services;

/** Acta interna de conteo; usa solo datos autorizados de revisión o cierre. */
final class ActaConteoPdf
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
        $h=$data['conteo'];$number=(string)$h->numero_interno;
        $rows=[];$add=function(string $s)use(&$rows){foreach($this->wrap($s,78) as $line)$rows[]=$line;};
        $add('Estado: '.$h->estado.' | Bodega: '.$data['bodega']);
        $add('Alcance: '.$h->alcance.' | Conteo ciego: '.((bool)$h->conteo_ciego?'Sí':'No'));
        $add('Fecha corte: '.$h->fecha_corte);
        $add($h->estado==='CONFIRMADO'?'Conteo confirmado. Consultar operaciones registradas.':'Acta de revisión: diferencias aún no aplicadas.');
        $plans=$data['revision']['planes'];$by=[];foreach($data['detalles'] as $l)$by[(int)$l->producto_id]=$l;
        $counts=['CUADRADO'=>0,'SOBRANTE'=>0,'FALTANTE'=>0];
        foreach($plans as $p){if(isset($counts[$p['resultado']]))$counts[$p['resultado']]++;}
        $add('Cuadrados: '.$counts['CUADRADO'].' | Sobrantes: '.$counts['SOBRANTE'].' | Faltantes: '.$counts['FALTANTE']);
        $add('Autorización: '.($data['autorizado']?'Registrada':'Pendiente').' | Ajustes: '.($data['ajustes_preparados']?'Preparados':'Pendientes'));
        $rows[]='';$rows[]='DETALLE DE CONCILIACION';
        foreach($plans as $p){
            $l=$by[(int)$p['producto_id']]??null;abort_unless($l,409,'Detalle de acta incompleto.');
            $rows[]='';$add($l->sku.' | '.$l->producto);
            $add('Corte: '.$p['esperado'].' | Conteo final: '.$p['contado']);
            $add('Diferencia: '.$p['diferencia'].' | '.$p['resultado']);
            if($p['bloqueos'])$add('Regularización pendiente: '.implode(', ',$p['bloqueos']));
            $add('Contado: '.$l->cantidad_contada.' | Reconteo: '.($l->cantidad_recontada??'No registrado'));
        }
        $rows[]='';$rows[]='OPERACIONES RELACIONADAS';
        if(!count($data['operaciones']))$add('Sin operaciones vinculadas.');
        foreach($data['operaciones'] as $o){
            $add($o->numero_interno.' | '.$o->motivo_nombre_snapshot.' | '.$o->estado_nombre);
            foreach($o->detalles as $l)$add($l->sku.' | Variación: '.$l->delta_disponible.' | Kardex: '.($l->kardex_id??'Pendiente'));
            foreach($o->respaldos as $f)$add('Respaldo: '.$f->nombre);
        }
        $rows[]='';$add('Documento de consulta generado desde la revisión registrada.');
        $pages=array_chunk($rows,39);
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];$kids=[];
        foreach($pages as $i=>$page){
            $pid=5+$i*2;$sid=$pid+1;$kids[]=$pid.' 0 R';
            $stream="0.30 0.15 0.48 rg 0 750 595 92 re f\n";
            $stream.=$this->text('RUTA H&D',36,810,12,'F2','1 1 1');
            $stream.=$this->text('ACTA INTERNA DE CONTEO',36,785,15,'F2','1 1 1');
            $stream.=$this->text($number,36,765,11,'F2','1 1 1');
            $stream.=$this->text('DOCUMENTO INTERNO - NO ES DTE',36,728,10,'F2');
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
