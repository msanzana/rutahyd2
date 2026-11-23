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

    /**
     * El color de relleno es estado del documento PDF, no de cada recuadro.
     * Es obligatorio fijarlo ANTES de todo texto: de otro modo la letra
     * hereda el violeta claro que usó el rectángulo y queda invisible.
     */
    private static function cmdText(float $x,float $y,string $valor,int $size=10,
        bool $bold=false,string $color='0.15 0.12 0.22'): string
    {
        $font=$bold?'F2':'F1';
        return $color.' rg '.
            sprintf('BT /%s %d Tf %.2F %.2F Td (%s) Tj ET'."\n",
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

    private static function dato(?string $valor): string
    {
        $s=trim((string)$valor);
        return $s!==''?$s:'No configurado';
    }

    private static function fecha(?string $valor): string
    {
        $s=trim((string)$valor);
        if($s==='')return 'No registrada';
        // Desde MariaDB llega YYYY-MM-DD o YYYY-MM-DD HH:MM:SS.
        if(preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})/', $s,$m)===1)
            return $m[3].'-'.$m[2].'-'.$m[1];
        return self::truncar($s,20);
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
        $emisor=$documento['emisor']??[];
        if(!is_array($emisor))$emisor=[];
        // Se usa la ficha privada del emisor. Los valores no configurados
        // quedan explícitos: nunca se inventa un RUT, domicilio o giro.
        $emisorNombre=self::dato((string)($emisor['nombre']??'Comercializadora H&D'));
        $emisorRut=self::dato((string)($emisor['rut']??''));
        $emisorGiro=self::dato((string)($emisor['giro']??''));
        $emisorDireccion=self::dato((string)($emisor['direccion']??''));
        $emisorCorreo=self::dato((string)($emisor['correo']??''));
        $fechaSolicitud=self::fecha($fecha);
        $fechaEmision=self::fecha((string)($documento['creado_en']??''));
        $chunks=array_chunk($rows,18);
        if(!$chunks)$chunks=[[]];
        $totalPaginas=count($chunks);
        $streams=[];
        foreach($chunks as $index=>$detalle){
            $pageNo=$index+1;
            $s="% Ruta H&D2 / orden de compra\n";
            // Banda corporativa. Cada línea de texto define su propio color
            // para que los fondos no hagan desaparecer la información.
            $s.=self::rect(0,739,595,103,'0.18 0.06 0.40');
            $s.=self::cmdText(42,798,'H&D  /  RUTA H&D2',18,true,'1 1 1');
            $s.=self::cmdText(42,770,'ORDEN DE COMPRA',13,true,'1 1 1');
            $s.=self::cmdText(379,797,self::truncar($numero,24),11,true,'1 1 1');
            $s.=self::cmdText(379,774,'Estado: '.self::truncar($estado,27),9,false,'1 1 1');

            // Dos tarjetas de identificación simétricas.
            // Los datos del proveedor provienen de la ficha asociada a la OC.
            $s.=self::rect(42,618,248,108,'0.96 0.95 0.98');
            $s.=self::rect(302,618,251,108,'0.96 0.95 0.98');
            $s.=self::cmdText(55,705,'EMISOR',9,true);
            $s.=self::cmdText(55,687,self::truncar($emisorNombre,40),11,true);
            $s.=self::cmdText(55,672,'RUT: '.self::truncar($emisorRut,36),8);
            $s.=self::cmdText(55,657,'Giro: '.self::truncar($emisorGiro,35),8);
            $s.=self::cmdText(55,642,'Dirección: '.self::truncar($emisorDireccion,35),8);
            $s.=self::cmdText(55,627,'Correo: '.self::truncar($emisorCorreo,36),8);

            $s.=self::cmdText(315,705,'PROVEEDOR',9,true);
            $s.=self::cmdText(315,687,self::truncar(self::dato($proveedor),40),11,true);
            $s.=self::cmdText(315,672,'RUT: '.self::truncar(self::dato($rut),36),8);
            $s.=self::cmdText(315,657,'Correo: '.self::truncar(self::dato($correo),35),8);
            $s.=self::cmdText(315,642,'Dirección: '.self::truncar(self::dato($direccion),35),8);
            $s.=self::cmdText(315,627,'Proveedor ID: '.(string)($documento['proveedor_id']??'—'),8);

            $s.=self::rect(42,578,511,28,'0.93 0.90 0.97');
            $s.=self::cmdText(52,588,'Emisión: '.$fechaEmision,9,true);
            $s.=self::cmdText(302,588,'Fecha solicitada: '.$fechaSolicitud,9,true);

            $s.=self::rect(42,540,511,28,'0.86 0.81 0.92');
            $s.=self::cmdText(49,551,'PRODUCTO',9,true);
            $s.=self::cmdText(330,551,'CANT.',9,true);
            $s.=self::cmdText(398,551,'COSTO NETO',9,true);
            $s.=self::cmdText(488,551,'SUBTOTAL',9,true);
            $y=521;
            foreach($detalle as $row){
                $sku=(string)($row['sku']??'');
                $nombre=(string)($row['nombre']??'');
                $cantidad=(string)($row['cantidad']??'0');
                $costo=(string)($row['costo_unitario_neto']??'0');
                $subtotal=(string)($row['subtotal_neto']??'0');
                $s.=self::cmdText(49,$y,self::truncar($sku.'  '.$nombre,42),8);
                $s.=self::cmdText(331,$y,$cantidad,8);
                $s.=self::cmdText(398,$y,'$ '.number_format((float)$costo,4,',','.'),8);
                $s.=self::cmdText(484,$y,'$ '.number_format((float)$subtotal,2,',','.'),8);
                $s.=self::sep(45,$y-7,551);
                $y-=20;
            }
            if($index===$totalPaginas-1){
                $bottom=min(155,max(132,$y-29));
                $s.=self::lineaResumen('Subtotal neto',(string)$documento['subtotal_neto'],$bottom);
                $s.=self::lineaResumen('Impuestos',(string)$documento['total_impuestos'],$bottom-22);
                $s.=self::lineaResumen('Total bruto',(string)$documento['total_bruto'],$bottom-50,true);
                if($observaciones!==''){
                    $s.=self::cmdText(45,58,'Observaciones: '.self::truncar($observaciones,90),8);
                }
            }
            $s.=self::cmdText(42,29,'Ruta H&D2  /  Documento de compra',8);
            $s.=self::cmdText(496,29,'Página '.$pageNo.' / '.$totalPaginas,8);
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
