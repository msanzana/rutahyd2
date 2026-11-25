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
            if($kind==='recepcion')return $this->recepcionTablas($r,$doc,$rows,$dif);
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

    /** Recepción en A4 apaisado, sin cambiar el origen ni los cálculos. */
    private function numero($v): string {
        $s=$this->val($v);
        if(!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D',$s,$m))return $s;
        $entero=preg_replace('/\B(?=(\d{3})+(?!\d))/','.', $m[2]);
        $dec=rtrim($m[3]??'','0');
        return $m[1].$entero.($dec!==''?','.$dec:'');
    }
    private function recepcionTablas(object $r,?object $doc,iterable $rows,iterable $dif): string {
        $meta=[
            ['Proveedor',$this->val($r->proveedor),'RUT',$this->val($r->rut)],
            ['Orden de compra',$this->val($r->oc),'Bodega',$this->val($r->bodega)],
            ['Estado',$this->val($r->estado),'Fecha recepción',$this->val($r->fecha_recepcion)],
        ];
        if($doc){
            $meta[]=['Documento',$this->val($doc->nombre),'Folio',$this->val($doc->folio)];
            $meta[]=['Fecha emisión',$this->val($doc->fecha_documento),'Total informado (CLP)',$this->numero($doc->total_informado)];
        }
        $products=[];
        foreach($rows as $d)$products[]=[
            $this->val($d->numero_linea),$this->val($d->sku),$this->val($d->nombre),
            $this->numero($d->cantidad_documentada),$this->numero($d->cantidad_recibida),
            $this->numero($d->cantidad_aceptada),$this->numero($d->cantidad_rechazada),
            $this->numero($d->costo_unitario_neto),$this->numero($d->monto_neto),
            $this->numero($d->monto_exento),$this->numero($d->monto_iva),$this->numero($d->total)
        ];
        $difRows=[];
        foreach($dif as $d)$difRows[]=[
            $this->val($d->recepcion_id),$this->val($d->tipo_diferencia),$this->numero($d->cantidad_diferencia),
            $this->val($d->motivo),$this->val($d->estado_resolucion)
        ];
        $sections=[
            ['DATOS DE RECEPCIÓN',['Campo','Información','Campo','Información'],[120,273,120,273],[1,3],$meta,[]],
            ['DETALLE DE PRODUCTOS · CANTIDADES Y MONTOS (CLP)',
              ['N°','SKU','Producto','Cant. doc.','Cant. recib.','Cant. acep.','Cant. rech.','Costo unit. neto','Neto','Exento','IVA','Total'],
              [25,66,168,57,57,57,57,70,56,56,56,61],[0,3,4,5,6,7,8,9,10,11],$products,[]],
            ['DIFERENCIAS REGISTRADAS',['Recepción','Tipo de diferencia','Cantidad','Motivo','Estado'],
              [75,145,85,350,131],[2],$difRows,['Sin diferencias registradas.']],
            ['TOTALES DE DOCUMENTOS · CLP (no equivalen a valoración de existencias)',
              ['Concepto','Importe','Concepto','Importe','Concepto','Importe'],
              [130,132,130,132,130,132],[1,3,5],[
                ['Neto',$this->numero($r->subtotal_neto),'Descuentos',$this->numero($r->total_descuentos),'Exento',$this->numero($r->total_exento)],
                ['IVA',$this->numero($r->total_iva),'Otros impuestos',$this->numero($r->otros_impuestos),'TOTAL',$this->numero($r->total)]
              ],[]],
        ];
        // Los campos de identificación no son numéricos: conservar alineación izquierda.
        $sections[0][3]=[];
        if($r->observaciones)$sections[]=['OBSERVACIONES',['Observación'],[786],[],[[$this->val($r->observaciones)]],[]];
        return $this->pdfTablas($this->val($r->numero_interno),in_array($r->estado,['ACTIVA','PENDIENTE'],true),$sections);
    }
    private function ancho(string $s,float $size,bool $bold=false): float {
        // Métricas de Helvetica, unidades de 1/1000 em. Para glifos no listados
        // se usa una cota conservadora; nunca se corta ni se omite el texto.
        $groups=[278=>" !'.,:;iIl",333=>"frt()[]",556=>"0123456789abdeghnopqu$¢",500=>"cksvxyzJ",611=>"FTZ",667=>"ABEKPSVXY",722=>"CDGHNRU",778=>"OQ",833=>"Mm",944=>"W",889=>"w",222=>"j",469=>"-",584=>"+=<>",1000=>"@%"];
        $map=[];foreach($groups as $w=>$chars)foreach(str_split($chars) as $ch)$map[$ch]=$w;
        $width=0;foreach(preg_split('//u',$s,-1,PREG_SPLIT_NO_EMPTY)?:[] as $ch){
            if(isset($map[$ch])){$width+=$map[$ch];continue;}
            $plain=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$ch);
            $width+=($plain!==false&&strlen($plain)===1&&isset($map[$plain]))?$map[$plain]:1000;
        }
        return $width*$size/1000*($bold?1.10:1.035);
    }
    private function envolver(string $s,float $width,float $size,bool $bold=false): array {
        $s=preg_replace('/[ \t]+/u',' ',trim($s))??'';
        $result=[];
        foreach(preg_split('/\R/u',$s)?:[''] as $paragraph){
            $line='';
            foreach(preg_split('/\s+/u',trim($paragraph),-1,PREG_SPLIT_NO_EMPTY)?:[] as $word){
                $try=$line===''?$word:$line.' '.$word;
                if($this->ancho($try,$size,$bold)<=$width){$line=$try;continue;}
                if($line!==''){$result[]=$line;$line='';}
                foreach(preg_split('//u',$word,-1,PREG_SPLIT_NO_EMPTY)?:[] as $ch){
                    if($line!==''&&$this->ancho($line.$ch,$size,$bold)>$width){$result[]=$line;$line='';}
                    $line.=$ch;
                }
            }
            $result[]=$line;
        }
        return $result?:[''];
    }
    private function tinta(string $s,float $x,float $y,float $size=9,bool $bold=false,string $color='0.17 0.13 0.23'): string {
        $font=$bold?'F2':'F1';
        return sprintf("%s rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",$color,$font,$size,$x,$y,$this->text($s));
    }
    private function caja(float $x,float $y,float $w,float $h,string $fill): string {
        return sprintf("%s rg %.2F %.2F %.2F %.2F re f\n",$fill,$x,$y,$w,$h);
    }
    private function fila(float $y,array $widths,array $cells,array $right,bool $head=false,bool $stripe=false): array {
        $max=max(array_map('count',$cells));$h=max(23,8+$max*11);$x=28;
        $s=$this->caja($x,$y-$h,array_sum($widths),$h,$head?'0.30 0.17 0.46':($stripe?'0.96 0.94 0.98':'1 1 1'));
        $s.="0.84 0.80 0.89 RG 0.45 w\n";
        foreach($widths as $i=>$w){
            $s.=sprintf("%.2F %.2F %.2F %.2F re S\n",$x,$y-$h,$w,$h);
            foreach($cells[$i] as $j=>$line){
                $size=$head?8.5:9;
                $tx=$x+5;
                if(in_array($i,$right,true)&&!$head)$tx=max($tx,$x+$w-5-$this->ancho($line,$size));
                $s.=$this->tinta($line,$tx,$y-13-$j*11,$size,$head,$head?'1 1 1':'0.17 0.13 0.23');
            }
            $x+=$w;
        }
        return [$s,$h];
    }
    private function pdfTablas(string $num,bool $draft,array $sections): string {
        $pages=[];$s='';$y=0;
        $new=function()use(&$pages,&$s,&$y,$num,$draft){
            if($s!=='')$pages[]=$s;
            $s=$this->caja(0,531,842,64,'0.24 0.10 0.43');
            $s.=$this->tinta('H&D  |  RECEPCIÓN DE COMPRA',28,568,18,true,'1 1 1');
            $s.=$this->tinta($num.'  |  Documento interno, no DTE',28,546,11,false,'1 1 1');
            $y=515;
            if($draft){$s.=$this->tinta('BORRADOR - NO CONFIRMADO',28,$y-10,10,true,'0.62 0.29 0.02');$y-=20;}
        };
        $heading=function(string $title,array $headers,array $widths,array $right)use(&$s,&$y){
            $s.=$this->tinta($title,28,$y-11,11,true,'0.28 0.13 0.46');$y-=18;
            $cells=[];foreach($headers as $i=>$v)$cells[]=$this->envolver($v,$widths[$i]-10,8.5,true);
            [$row,$h]=$this->fila($y,$widths,$cells,$right,true);$s.=$row;$y-=$h;
        };
        $new();
        foreach($sections as [$title,$headers,$widths,$right,$rows,$empty]){
            if($y<110)$new();
            $heading($title,$headers,$widths,$right);
            if(!$rows){
                $msg=$empty[0]??'Sin registros.';
                $s.=$this->caja(28,$y-23,786,23,'0.96 0.94 0.98');
                $s.=$this->tinta($msg,34,$y-14,9);$y-=23;
            }
            foreach($rows as $ri=>$values){
                $cells=[];foreach($values as $i=>$v)$cells[]=$this->envolver((string)$v,$widths[$i]-10,9);
                $max=max(array_map('count',$cells));$offset=0;
                while($offset<$max){
                    $remaining=$max-$offset;$fullHeight=max(23,8+$remaining*11);
                    // Si la fila completa cabe en una página nueva, no partirla.
                    if($y-$fullHeight<42&&$fullHeight<=375){$new();$heading($title.' (continuación)',$headers,$widths,$right);}
                    if($y<76){$new();$heading($title.' (continuación)',$headers,$widths,$right);}
                    $take=min($remaining,max(1,(int)floor(($y-42-8)/11)));
                    $chunk=[];foreach($cells as $c)$chunk[]=array_slice($c,$offset,$take)?:[''];
                    [$row,$h]=$this->fila($y,$widths,$chunk,$right,false,$ri%2===1);$s.=$row;$y-=$h;$offset+=$take;
                    if($offset<$max){$new();$heading($title.' (continuación)',$headers,$widths,$right);}
                }
            }
            $y-=13;
        }
        if($s!=='')$pages[]=$s;
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',
            3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $kids=[];
        foreach($pages as $i=>$page){
            $pid=5+2*$i;$sid=$pid+1;$kids[]=$pid.' 0 R';
            $page.="0.84 0.80 0.89 RG 0.6 w 28 31 m 814 31 l S\n";
            $page.=$this->tinta('Ruta H&D  |  Página '.($i+1).' / '.count($pages),28,18,9,false,'0.35 0.27 0.44');
            $objects[$pid]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $sid 0 R >>";
            $objects[$sid]='<< /Length '.strlen($page)." >>\nstream\n".$page."endstream";
        }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';
        ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];
        foreach($objects as $i=>$obj){$offsets[$i]=strlen($pdf);$pdf.="$i 0 obj\n$obj\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".count($offsets)."\n0000000000 65535 f \n";
        for($i=1;$i<count($offsets);$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
        return $pdf."trailer\n<< /Size ".count($offsets)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
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
