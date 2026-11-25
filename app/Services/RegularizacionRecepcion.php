<?php
declare(strict_types=1);
namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Solicitudes internas, nunca emisión de DTE. Persistencia dentro del cierre. */
final class RegularizacionRecepcion
{
    private function n($v): BigDecimal {return BigDecimal::of((string)($v??0));}
    private function money(BigDecimal $v): string {return (string)$v->toScale(8,RoundingMode::HALF_UP);}
    public function generar(object $c,array $review,int $user,string $motivo): array
    {
        $receipts=collect($review['recepciones'])->keyBy('id');
        $groups=[];$seen=[];
        foreach($review['diferencias'] as $raw){
            $d=(object)$raw;$r=$receipts->get($d->recepcion_id);abort_unless($r,409,'Diferencia ajena a la consolidación.');
            $line=$d->recepcion_detalle_id?DB::table('recepcion_detalle as r')->join('compras_detalle as cd','cd.id','=','r.compra_detalle_id')->where('r.id',$d->recepcion_detalle_id)->where('r.recepcion_id',$r['id'])->where('cd.compra_id',$r['compra_id'])->select('r.*','cd.es_exento','cd.tasa_iva','cd.descripcion','cd.descuento as descuento_compra','cd.recargo as recargo_compra')->first():null;
            $doc=DB::table('compras as c')->join('tipo_documentos as t','t.id','=','c.documento_id')->where('c.id',$r['compra_id'])->first(['t.codigo_sii']);
            $type='AVISO_COMERCIAL';$net=$ex=$iva=BigDecimal::zero();$qty=$this->n($d->cantidad_diferencia);$cost=$this->n($line?->costo_unitario_neto);
            // Sólo diferencia física frente a factura, no duplicar exceso frente a OC.
            if($line&&$doc&&in_array((int)$doc->codigo_sii,[33,34],true)&&$d->motivo==='Cantidad física distinta del documento.'&&!isset($seen[$line->id])&&$this->n($line->descuento_compra)->isZero()&&$this->n($line->recargo_compra)->isZero()){
                $delta=$this->n($line->cantidad_documentada)->minus((string)$line->cantidad_recibida);
                if(!$delta->isZero()&&$qty->isEqualTo($delta->abs())&&$cost->isPositive()){
                    $type=$delta->isPositive()?'NC':'ND';$seen[$line->id]=true;
                    $amount=$qty->multipliedBy($cost)->toScale(0,RoundingMode::HALF_UP);
                    if($amount->isZero())$type='AVISO_COMERCIAL';
                    elseif((int)$line->es_exento===1)$ex=$amount;
                    elseif($this->n($line->tasa_iva)->isEqualTo('19')){$net=$amount;$iva=$net->multipliedBy('0.19')->toScale(0,RoundingMode::HALF_UP);}
                    else $type='AVISO_COMERCIAL';
                }
            }
            $groups[$r['id'].':'.$type][]=['r'=>$r,'type'=>$type,'diff'=>$d,'descripcion'=>mb_substr((string)($line?->descripcion??'Diferencia documental o comercial'),0,255),'cost'=>$cost,'net'=>$net,'ex'=>$ex,'iva'=>$iva];
        }
        // Saldo OC no es deuda tributaria. No imputar diferencias de orden a una factura.
        // Si no hay diferencias de recepción, el cierre incompleto sigue bloqueado.
        abort_if(!$groups&&$review['saldos_oc'],409,'No existen diferencias de recepción que permitan justificar el cierre incompleto.');
        $prov=DB::table('proveedores')->where('id',$c->proveedor_id)->first();abort_unless($prov,409,'Proveedor inexistente.');
        $email=trim((string)$prov->correo_electronico);$valid=filter_var($email,FILTER_VALIDATE_EMAIL)!==false;
        $ids=[];
        foreach($groups as $items){
            $r=$items[0]['r'];$type=$items[0]['type'];$docid=null;
            if($type!=='AVISO_COMERCIAL'){
                $docid=DB::table('tipo_documentos')->where('codigo_sii',$type==='NC'?61:56)->where('activo',1)->where('documento_compra',1)->value('id');
                abort_unless($docid,409,'Tipo de documento de regularización no configurado.');
            }
            $net=$ex=$iva=BigDecimal::zero();foreach($items as $it){$net=$net->plus($it['net']);$ex=$ex->plus($it['ex']);$iva=$iva->plus($it['iva']);}
            $total=$net->plus($ex)->plus($iva);$token=(string)Str::uuid();
            $id=DB::table('solicitudes_regularizacion')->insertGetId(['numero_interno'=>'TMP-'.$token,'recepcion_consolidada_id'=>$c->id,'recepcion_id'=>$r['id'],'compra_id'=>$r['compra_id'],'proveedor_id'=>$c->proveedor_id,'tipo_solicitud'=>$type,'tipo_documento_solicitado_id'=>$docid,'estado'=>'PENDIENTE','motivo'=>$motivo,'monto_neto'=>$this->money($net),'monto_exento'=>$this->money($ex),'monto_iva'=>$this->money($iva),'otros_impuestos'=>0,'monto_total'=>$this->money($total),'token_idempotencia'=>$token,'creado_por'=>$user]);
            $num='SOL-'.str_pad((string)$id,8,'0',STR_PAD_LEFT);DB::table('solicitudes_regularizacion')->where('id',$id)->update(['numero_interno'=>$num]);
            foreach($items as $i=>$it){$d=$it['diff'];DB::table('solicitudes_regularizacion_detalle')->insert(['solicitud_id'=>$id,'recepcion_diferencia_id'=>$d->id,'recepcion_detalle_id'=>$d->recepcion_detalle_id,'producto_id'=>$d->producto_id,'numero_linea'=>$i+1,'descripcion'=>$it['descripcion'],'tipo_diferencia'=>$d->tipo_diferencia,'cantidad_documentada'=>$d->cantidad_documentada,'cantidad_recibida'=>$d->cantidad_contada,'cantidad_diferencia'=>$d->cantidad_diferencia,'costo_unitario_neto'=>$this->money($it['cost']),'monto_neto'=>$this->money($it['net']),'monto_exento'=>$this->money($it['ex']),'monto_iva'=>$this->money($it['iva']),'otros_impuestos'=>0,'monto_total'=>$this->money($it['net']->plus($it['ex'])->plus($it['iva'])),'observaciones'=>$d->motivo]);}
            $label=match($type){'NC'=>'Solicitud de nota de crédito','ND'=>'Solicitud de revisión para nota de débito',default=>'Aviso comercial'};
            $subject=$label.' '.$num.' | Ruta H&D';
            $esc=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $html='<div style="font-family:Arial,sans-serif;color:#29213a;max-width:650px;margin:auto"><div style="background:#432378;color:white;padding:24px"><h2>H&amp;D | '.$esc($label).'</h2></div><div style="padding:24px"><p>Estimado proveedor '.$esc($prov->nombre).':</p><p>Solicitamos revisar las diferencias de la recepción '.$esc($r['numero_interno']).', folio '.$esc($r['folio']).', consolidación '.$esc($c->numero_interno).'.</p><p>Referencia '.$esc($num).'. Importe propuesto: $'.$esc($this->money($total)).' CLP.</p><p>El PDF adjunto detalla las diferencias. Esta solicitud no constituye emisión de DTE ni aceptación tributaria. Favor confirmar la regularización que corresponda.</p><p>Atentamente, Comercializadora H&amp;D.</p></div></div>';
            $nid=DB::table('cola_notificaciones_correo')->insertGetId(['modulo_codigo'=>'CONSOLIDACION_RECEPCION','origen_tipo'=>'SOLICITUD_REGULARIZACION','origen_id'=>$id,'solicitud_id'=>$id,'proveedor_id'=>$c->proveedor_id,'destinatario'=>$valid?$email:'','destinatario_nombre'=>$prov->nombre,'asunto'=>$subject,'plantilla_codigo'=>'REGULARIZACION_RECEPCION','cuerpo_html'=>$html,'estado'=>$valid?'PENDIENTE':'ERROR','ultimo_error'=>$valid?null:'Proveedor sin correo válido.','token_idempotencia'=>(string)Str::uuid(),'clave_evento'=>'REGULARIZACION:'.$id,'creado_por'=>$user]);
            DB::table('cola_notificaciones_adjuntos')->insert(['notificacion_id'=>$nid,'origen_tipo'=>'SOLICITUD_REGULARIZACION','origen_id'=>$id,'nombre_archivo'=>$num.'.pdf','tipo_mime'=>'application/pdf']);
            $ids[]=$nid;
        }
        return $ids;
    }
    public function pdf(int $id): string
    {
        $s=DB::table('solicitudes_regularizacion as s')->join('proveedores as p','p.id','=','s.proveedor_id')->join('compras as c','c.id','=','s.compra_id')->where('s.id',$id)->where('s.estado','<>','NULA')->first(['s.*','p.nombre as proveedor','c.folio']);
        abort_unless($s,404,'Solicitud no encontrada.');
        $label=match($s->tipo_solicitud){'NC'=>'SOLICITUD NOTA DE CREDITO','ND'=>'REVISION PARA NOTA DE DEBITO',default=>'AVISO COMERCIAL'};
        $lines=['H&D | '.$label,$s->numero_interno,'Proveedor: '.$s->proveedor,'Folio: '.$s->folio,'Motivo: '.$s->motivo,'Solicitud interna, no constituye DTE.',''];
        foreach(DB::table('solicitudes_regularizacion_detalle')->where('solicitud_id',$id)->orderBy('numero_linea')->get() as $d){
            $lines[]=$d->numero_linea.'. '.$d->descripcion.' | '.$d->tipo_diferencia;
            $lines[]='Documentada: '.$d->cantidad_documentada.' / recibida: '.$d->cantidad_recibida.' / diferencia: '.$d->cantidad_diferencia;
            $lines[]='Neto: '.$d->monto_neto.' / exento: '.$d->monto_exento.' / IVA: '.$d->monto_iva;
            $lines[]='Total: '.$d->monto_total.' | '.$d->observaciones;$lines[]='';
        }
        $lines[]='TOTAL PROPUESTO CLP: '.$s->monto_total;
        $wrapped=[];foreach($lines as $line){$line=preg_replace('/\s+/u',' ',(string)$line);while(mb_strlen($line)>85){$wrapped[]=mb_substr($line,0,85);$line=mb_substr($line,85);}$wrapped[]=$line;}
        $pages=array_chunk($wrapped,39);$objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];$kids=[];
        foreach($pages as $i=>$page){$pid=4+$i*2;$sid=$pid+1;$kids[]=$pid.' 0 R';$stream="0.25 0.12 0.44 rg 0 780 595 62 re f\n";$y=753;
            foreach(array_merge(['RUTA H&D | '.$s->numero_interno.' | '.($i+1).'/'.count($pages)],$page) as $line){$latin=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$line);$text=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$latin===false?'':$latin);$stream.="0.15 0.12 0.22 rg BT /F1 10 Tf 36 $y Td ($text) Tj ET\n";$y-=17;}
            $objects[$pid]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $sid 0 R >>";$objects[$sid]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream";
        }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';ksort($objects);$pdf="%PDF-1.4\n";$offs=[0];foreach($objects as $i=>$obj){$offs[$i]=strlen($pdf);$pdf.="$i 0 obj\n$obj\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".count($offs)."\n0000000000 65535 f \n";for($i=1;$i<count($offs);$i++)$pdf.=sprintf("%010d 00000 n \n",$offs[$i]);return $pdf."trailer\n<< /Size ".count($offs)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
    /** SMTP is outside the business transaction. No blind retry after ambiguous transport. */
    public function enviar(int $id,bool $manual=false): string
    {
        $claim=DB::transaction(function()use($id,$manual){
            $n=DB::table('cola_notificaciones_correo')->where('id',$id)->lockForUpdate()->first();
            abort_unless($n&&$n->origen_tipo==='SOLICITUD_REGULARIZACION',404,'Notificación no encontrada.');
            if(!in_array($n->estado,$manual?['PENDIENTE','ERROR']:['PENDIENTE'],true))return null;
            if((int)$n->intentos>=(int)$n->maximo_intentos)return null;
            $s=DB::table('solicitudes_regularizacion')->where('id',$n->solicitud_id)->where('estado','<>','NULA')->first();abort_unless($s,409,'Solicitud anulada.');
            $email=trim((string)DB::table('proveedores')->where('id',$n->proveedor_id)->value('correo_electronico'));
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)){DB::table('cola_notificaciones_correo')->where('id',$id)->update(['estado'=>'ERROR','ultimo_error'=>'Proveedor sin correo válido.']);return null;}
            $token=(string)Str::uuid();DB::table('cola_notificaciones_correo')->where('id',$id)->update(['estado'=>'PROCESANDO','destinatario'=>$email,'token_procesamiento'=>$token,'procesando_en'=>now(),'ultimo_intento_en'=>now(),'intentos'=>(int)$n->intentos+1]);return [$n,$email,$token,$s];
        },3);
        if(!$claim)return (string)DB::table('cola_notificaciones_correo')->where('id',$id)->value('estado');
        [$n,$email,$token,$s]=$claim;
        try{
            $pdf=$this->pdf((int)$s->id);
            Mail::html($n->cuerpo_html,static function($m)use($n,$email,$pdf,$s){$m->to($email)->subject($n->asunto)->attachData($pdf,$s->numero_interno.'.pdf',['mime'=>'application/pdf']);});
        }catch(\Throwable $e){
            // No almacenar credenciales, contenido SMTP ni datos sensibles del error.
            DB::table('cola_notificaciones_correo')->where('id',$id)->where('token_procesamiento',$token)->update(['estado'=>'ERROR','ultimo_error'=>'No se confirmó el envío. Verifique el buzón antes de reintentar.','token_procesamiento'=>null]);
            return 'ERROR';
        }
        DB::transaction(function()use($id,$token,$s){
            $count=DB::table('cola_notificaciones_correo')->where('id',$id)->where('token_procesamiento',$token)->where('estado','PROCESANDO')->update(['estado'=>'ENVIADA','enviada_en'=>now(),'ultimo_error'=>null,'token_procesamiento'=>null]);
            if($count)DB::table('solicitudes_regularizacion')->where('id',$s->id)->where('estado','PENDIENTE')->update(['estado'=>'ENVIADA','enviada_en'=>now()]);
        },3);return 'ENVIADA';
    }
}
