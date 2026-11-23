<?php
declare(strict_types=1);

namespace App\Services;

/**
 * PDF corporativo de una orden, generado sin dependencias externas.
 * Fuente PDF estándar Helvetica + CP1252, compatible con InfinityFree.
 * La misma salida binaria se adjunta al correo y se sirve al botón "Ver PDF".
 */
final class PdfOrdenCompra
{
    private static function texto(string $valor): string
    {
        $latin=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$valor);
        $latin=$latin===false?'':$latin;
        return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$latin);
    }

    private static function cmdText(float $x,float $y,string $valor,int $size=10,bool $bold=false): string
    {
        $font=$bold?'F2':'F1';
        return sprintf('BT /%s %d Tf %.2F %.2F Td (%s) Tj ET'."\n",
            $font,$size,$x,$y,self::texto($valor));
    }

    private static function rect(float $x,float $y,float $w,float $h,string $color): string
    {
        return $color.' rg '.sprintf('%.2F %.2F %.2F %.2F re f',$x,$y,$w,$h)."\n";
    }

    private static function sep(float $x1,float $y,float $x2): string
    {
        return '0.87 0.86 0.90 RG 0.7 w '.
            sprintf('%.2F %.2F m %.2F %.2F l S',$x1,$y,$x2,$y)."\n";
    }

    private static function lineaResumen(string $etiqueta,string $importe,float $y,bool $destacar=false): string
    {
        return ($destacar?self::rect(332,$y-9,218,29,'0.94 0.91 0.98'):'').
            self::cmdText(343,$y,$etiqueta,10,$destacar).
            self::cmdText(453,$y,'$ '.number_format((float)$importe,2,',','.'),10,true);
    }

    private static function truncar(string $s,int $max=46): string
    {
        return mb_strlen($s,'UTF-8')<=$max?$s:mb_substr($s,0,$max-1,'UTF-8').'…';
    }

    public function generar(array $documento): string
    {
        $numero=(string)($documento['numero']??'OC');
        $estado=(string)($documento['estado_nombre']??'Activa');
        $proveedor=(string)($documento['proveedor_nombre']??'');
        $rut=(string)($documento['proveedor_rut']??'');
        $correo=(string)($documento['proveedor_correo_electronico']??'');
        $direccion=(string)($documento['proveedor_direccion']??'');
        $fecha=(string)($documento['solicitada_en']??'');
        $observaciones=(string)($documento['observaciones']??'');
        $rows=$documento['detalles']??[];
        if(!is_array($rows))$rows=[];
        $chunks=array_chunk($rows,19);
        if(!$chunks)$chunks=[[]];
        $totalPaginas=count($chunks);
        $streams=[];
        foreach($chunks as $index=>$detalle){
            $pageNo=$index+1;
            $s="% Ruta H&D2 / orden de compra\n";
            // Cabecera violeta H&D
            $s.=self::rect(0,742,595,100,'0.18 0.06 0.40');
            $s.='1 1 1 rg'."\n";
            $s.=self::cmdText(44,796,'H&D  /  RUTA H&D2',19,true);
            $s.=self::cmdText(44,769,'ORDEN DE COMPRA',12,true);
            $s.=self::cmdText(403,796,self::truncar($numero,22),12,true);
            $s.=self::cmdText(403,774,'Estado: '.$estado,9);
            $s.='0.13 0.12 0.18 rg'."\n";
            $s.=self::rect(42,651,511,76,'0.96 0.95 0.98');
            $s.=self::cmdText(55,708,'PROVEEDOR',9,true);
            $s.=self::cmdText(55,691,self::truncar($proveedor,54),11,true);
            $s.=self::cmdText(55,673,'RUT: '.$rut.'    Correo: '.self::truncar($correo,42),9);
            $s.=self::cmdText(55,658,'Dirección: '.self::truncar($direccion,70),8);
            $s.=self::cmdText(43,631,'Fecha solicitada: '.$fecha,10);
            $s.=self::rect(42,592,511,27,'0.87 0.83 0.93');
            $s.='0.15 0.10 0.26 rg'."\n";
            $s.=self::cmdText(50,603,'PRODUCTO',9,true);
            $s.=self::cmdText(333,603,'CANT.',9,true);
            $s.=self::cmdText(402,603,'COSTO NETO',9,true);
            $s.=self::cmdText(497,603,'SUBTOTAL',9,true);
            $y=573;
            foreach($detalle as $row){
                $sku=(string)($row['sku']??'');
                $nombre=(string)($row['nombre']??'');
                $cantidad=(string)($row['cantidad']??'0');
                $costo=(string)($row['costo_unitario_neto']??'0');
                $subtotal=(string)($row['subtotal_neto']??'0');
                $s.=self::cmdText(50,$y,self::truncar($sku.'  '.$nombre,43),8);
                $s.=self::cmdText(334,$y,$cantidad,8);
                $s.=self::cmdText(400,$y,'$ '.number_format((float)$costo,4,',','.'),8);
                $s.=self::cmdText(489,$y,'$ '.number_format((float)$subtotal,2,',','.'),8);
                $s.=self::sep(45,$y-10,551);
                $y-=23;
            }
            if($index===$totalPaginas-1){
                $bottom=max(75,$y-34);
                $s.=self::lineaResumen('Subtotal neto',(string)$documento['subtotal_neto'],$bottom);
                $s.=self::lineaResumen('Impuestos',(string)$documento['total_impuestos'],$bottom-19);
                $s.=self::lineaResumen('Total bruto',(string)$documento['total_bruto'],$bottom-47,true);
                if($observaciones!==''){
                    $s.=self::cmdText(45,80,'Observaciones: '.self::truncar($observaciones,85),8);
                }
            }
            $s.='0.35 0.32 0.41 rg'."\n";
            $s.=self::cmdText(44,39,'Documento emitido por Ruta H&D2 · Comercializadora H&D',8);
            $s.=self::cmdText(499,39,'Página '.$pageNo.' / '.$totalPaginas,8);
            $streams[]=$s;
        }
        // Esqueleto ISO PDF 1.4 con una página por stream; xref en bytes exactos.
        $n=count($streams);
        $regular=3+$n*2;
        $bold=$regular+1;
        $objects=[];
        $kids=[];
        foreach($streams as $i=>$stream){
            $page=3+$i*2;
            $content=$page+1;
            $kids[]=$page.' 0 R';
            $objects[$page]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '.
                '/Resources << /Font << /F1 '.$regular.' 0 R /F2 '.$bold.' 0 R >> >> '.
                '/Contents '.$content.' 0 R >>';
            $objects[$content]='<< /Length '.strlen($stream).' >>'."\nstream\n".$stream.'endstream';
        }
        $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.$n.' >>';
        $objects[$regular]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$bold]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($objects);
        $pdf="%PDF-1.4\n";
        $offsets=[0];
        foreach($objects as $id=>$object){
            $offsets[$id]=strlen($pdf);
            $pdf.=$id." 0 obj\n".$object."\nendobj\n";
        }
        $start=strlen($pdf);
        $pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=count($objects);$i++)
            $pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
        $pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\n";
        $pdf.="startxref\n".$start."\n%%EOF";
        return $pdf;
    }
}
