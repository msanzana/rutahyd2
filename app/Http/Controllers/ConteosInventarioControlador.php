<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\ContextoCompras;
use App\Services\PlanConteoInventario;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** V109D2: registro selectivo, corte, conteo ciego y reconteo. Sin ajustes. */
final class ConteosInventarioControlador extends Controller
{
    private function auditoria(int $id,int $uid,string $action,array $before,array $after): void {
        DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>$action,'tipo_entidad'=>'inventario_conteos','entidad_id'=>$id,'valores_anteriores'=>json_encode($before,JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode($after,JSON_THROW_ON_ERROR)]);
    }
    private function bodega(int $sid,int $bid): void {
        $b=DB::table('bodegas as b')->join('inventario_bodegas_sucursales as bs','bs.bodega_id','=','b.id')->where('b.id',$bid)->where('b.activo',1)->where('bs.sucursal_id',$sid)->select('b.id')->lockForUpdate()->first();
        abort_unless($b,404,'Bodega no autorizada.');
    }
    private function actual(int $pid,int $bid,int $sid): array {
        $p=DB::table('productos')->where('id',$pid)->where('activo',1)->lockForUpdate()->first();abort_unless($p,404,'Producto no disponible.');
        abort_if(DB::table('saldos_inventario as s')->join('presentaciones_productos as pp','pp.id','=','s.presentacion_id')->where('pp.producto_id',$pid)->where('s.cantidad','<>',0)->exists(),409,'Inventario legado pendiente.');
        $stock=DB::table('stock')->where('producto_id',$pid)->where('bodega_id',$bid)->lockForUpdate()->first()??(object)['disponible'=>'0','transito'=>'0','comprometido'=>'0','cantidad_fisica'=>'0'];
        $last=DB::table('kardex')->where('producto_id',$pid)->where('bodega_id',$bid)->orderByDesc('id')->lockForUpdate()->first(['id','sucursal_id']);
        abort_if($last&&(int)$last->sucursal_id!==$sid,409,'Kardex sin atribución válida.');
        $global=DB::table('kardex')->where('producto_id',$pid)->orderByDesc('id')->lockForUpdate()->first(['id']);
        $plan=(new PlanConteoInventario())->calcular($stock,(string)BigDecimal::of((string)$stock->disponible)->plus((string)$stock->comprometido)->toScale(8),(string)$p->costo_promedio_ponderado);
        return ['stock'=>$stock,'cpp'=>(string)$p->costo_promedio_ponderado,'saldo'=>$plan['esperado'],'snapshot'=>$plan['snapshot'],'ultimo'=>$last?(int)$last->id:null,'ultimo_global'=>$global?(int)$global->id:null];
    }
    private function cabecera(int $id,int $sid): object {
        $c=DB::table('inventario_conteos')->where('id',$id)->where('sucursal_id',$sid)->lockForUpdate()->first();abort_unless($c,404,'Conteo no disponible.');
        $this->bodega($sid,(int)$c->bodega_id);return $c;
    }
    private function corte(object $c): array {
        $rows=DB::table('registros_auditoria')->where('tipo_entidad','inventario_conteos')->where('entidad_id',$c->id)->where('accion','CONTEO_CORTE')->lockForUpdate()->get(['valores_nuevos']);
        abort_unless($rows->count()===1,409,'No existe un corte de inventario verificable.');
        $v=json_decode($rows[0]->valores_nuevos,true,512,JSON_THROW_ON_ERROR);
        abort_unless(is_array($v)&&isset($v['productos'])&&is_array($v['productos']),409,'Corte inválido.');return $v['productos'];
    }
    private function vigente(object $c,object $l,array $snap,int $sid): array {
        $cut=$snap[$l->producto_id]??null;
        abort_unless(is_array($cut)&&isset($cut['snapshot'])&&array_key_exists('ultimo_global',$cut)&&array_key_exists('ultimo',$cut),409,'Producto sin corte verificable.');
        $a=$this->actual((int)$l->producto_id,(int)$c->bodega_id,$sid);
        abort_unless(($l->ultimo_kardex_id===null?null:(int)$l->ultimo_kardex_id)===$cut['ultimo']&&$a['ultimo']===$cut['ultimo']&&$a['ultimo_global']===$cut['ultimo_global']&&BigDecimal::of($a['saldo'])->isEqualTo((string)$l->saldo_corte),409,'Hubo movimientos desde el corte. Se requiere un nuevo conteo.');
        (new PlanConteoInventario())->verificarVigencia($cut['snapshot'],$a['stock'],$a['cpp']);
        return $a;
    }
    public function crear(Request $r){
        $v=$r->validate(['bodega_id'=>'required|integer|min:1','productos'=>'required|array|min:1|max:50','productos.*'=>'required|integer|min:1|distinct','conteo_ciego'=>'required|boolean','token_creacion'=>'required|uuid']);
        $number='CNT-'.strtolower($v['token_creacion']);$ids=array_map('intval',$v['productos']);sort($ids,SORT_NUMERIC);
        $res=DB::transaction(function()use($r,$v,$number,$ids){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;$bid=(int)$v['bodega_id'];$this->bodega($sid,$bid);
            $old=DB::table('inventario_conteos')->where('numero_interno',$number)->lockForUpdate()->first();
            if($old){
                abort_unless((int)$old->sucursal_id===$sid&&(int)$old->creado_por===$uid&&(int)$old->bodega_id===$bid&&(bool)$old->conteo_ciego===(bool)$v['conteo_ciego']&&$old->alcance==='SELECTIVO',409,'Solicitud incompatible.');
                $saved=DB::table('inventario_conteos_detalle')->where('conteo_id',$old->id)->orderBy('producto_id')->pluck('producto_id')->map(fn($x)=>(int)$x)->all();
                abort_unless($saved===$ids,409,'Solicitud incompatible.');
                return ['id'=>(int)$old->id,'numero_interno'=>$number,'estado'=>$old->estado,'repetida'=>true];
            }
            abort_if(DB::table('inventario_conteos')->where('sucursal_id',$sid)->where('bodega_id',$bid)->whereIn('estado',['BORRADOR','EN_CONTEO','EN_REVISION'])->exists(),409,'Existe un conteo activo en esta bodega.');
            $snap=[];foreach($ids as $pid){$a=$this->actual($pid,$bid,$sid);$snap[$pid]=['saldo'=>$a['saldo'],'ultimo'=>$a['ultimo'],'ultimo_global'=>$a['ultimo_global'],'snapshot'=>$a['snapshot']];}
            $id=DB::table('inventario_conteos')->insertGetId(['numero_interno'=>$number,'bodega_id'=>$bid,'sucursal_id'=>$sid,'alcance'=>'SELECTIVO','estado'=>'EN_CONTEO','conteo_ciego'=>(int)(bool)$v['conteo_ciego'],'fecha_corte'=>now()->format('Y-m-d H:i:s.u'),'creado_por'=>$uid]);
            foreach($snap as $pid=>$a)DB::table('inventario_conteos_detalle')->insert(['conteo_id'=>$id,'producto_id'=>$pid,'saldo_corte'=>$a['saldo'],'ultimo_kardex_id'=>$a['ultimo']]);
            $this->auditoria($id,$uid,'CONTEO_CORTE',[],['productos'=>$snap]);
            return ['id'=>$id,'numero_interno'=>$number,'estado'=>'EN_CONTEO','repetida'=>false];
        },3);
        return response()->json($res,$res['repetida']?200:201)->header('Cache-Control','no-store');
    }
    public function contar(Request $r,int $id,int $line){return $this->registrar($r,$id,$line,false);}
    public function recontar(Request $r,int $id,int $line){return $this->registrar($r,$id,$line,true);}
    private function registrar(Request $r,int $id,int $line,bool $recount){
        $v=$r->validate(['cantidad'=>['required','string','regex:/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D'],'observaciones'=>'nullable|string|max:1000']);
        $qty=(string)BigDecimal::of($v['cantidad'])->toScale(8);$obs=trim($v['observaciones']??'');
        return response()->json(DB::transaction(function()use($r,$id,$line,$recount,$qty,$obs){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;$c=$this->cabecera($id,$sid);
            abort_unless($c->estado==='EN_CONTEO'&&$c->operacion_ajuste_id===null,409,'Conteo no editable.');
            $l=DB::table('inventario_conteos_detalle')->where('conteo_id',$id)->where('id',$line)->lockForUpdate()->first();abort_unless($l,404);
            $field=$recount?'cantidad_recontada':'cantidad_contada';$who=$recount?'recontado_por':'contado_por';
            if($recount)abort_unless($l->cantidad_contada!==null&&(int)$l->contado_por!==$uid,409,'El reconteo requiere otra persona.');
            if($l->$field!==null){
                $action=$recount?'CONTEO_RECONTEO':'CONTEO_REGISTRO';
                $audit=DB::table('registros_auditoria')->where('tipo_entidad','inventario_conteos')->where('entidad_id',$id)->where('accion',$action)->where('usuario_id',$uid)->get(['valores_nuevos'])->map(fn($a)=>json_decode($a->valores_nuevos,true,512,JSON_THROW_ON_ERROR))->filter(fn($a)=>(int)($a['linea']??0)===$line);
                abort_unless($audit->count()===1&&(int)$l->$who===$uid&&BigDecimal::of((string)$l->$field)->isEqualTo($qty)&&($audit->first()['observaciones']??null)===$obs,409,'La medición registrada no puede reemplazarse.');
                return ['id'=>$id,'linea'=>$line,'repetida'=>true];
            }
            $this->vigente($c,$l,$this->corte($c),$sid);
            DB::table('inventario_conteos_detalle')->where('id',$line)->update([$field=>$qty,$who=>$uid,'observaciones'=>$obs]);
            $this->auditoria($id,$uid,$recount?'CONTEO_RECONTEO':'CONTEO_REGISTRO',['linea'=>$line],['linea'=>$line,'cantidad'=>$qty,'observaciones'=>$obs]);
            return ['id'=>$id,'linea'=>$line,'repetida'=>false];
        },3))->header('Cache-Control','no-store');
    }
    public function revisar(Request $r,int $id){
        return response()->json(DB::transaction(function()use($r,$id){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;$c=$this->cabecera($id,$sid);
            abort_unless($c->estado==='EN_CONTEO'&&$c->operacion_ajuste_id===null,409,'Conteo no disponible para revisión.');
            $snap=$this->corte($c);$lines=DB::table('inventario_conteos_detalle')->where('conteo_id',$id)->orderBy('producto_id')->lockForUpdate()->get();
            abort_unless($lines->count()===count($snap)&&$lines->count()>0&&$lines->pluck('producto_id')->unique()->count()===$lines->count(),409,'Detalle de corte inconsistente.');
            $plans=[];
            foreach($lines as $l){
                abort_unless($l->cantidad_contada!==null&&$l->contado_por!==null&&(int)$l->contado_por!==$uid,409,'Se requiere conteo completo y revisión independiente.');
                $diff=!BigDecimal::of((string)$l->cantidad_contada)->isEqualTo((string)$l->saldo_corte);
                if($diff)abort_unless($l->cantidad_recontada!==null&&$l->recontado_por!==null&&(int)$l->recontado_por!==(int)$l->contado_por,409,'Las diferencias requieren reconteo independiente.');
                if($l->cantidad_recontada!==null)abort_unless((int)$l->recontado_por!==$uid&&(int)$l->recontado_por!==(int)$l->contado_por,409,'Revisor debe ser distinto de los contadores.');
                $a=$this->vigente($c,$l,$snap,$sid);
                $plans[]=['producto_id'=>(int)$l->producto_id]+(new PlanConteoInventario())->calcular($a['stock'],(string)($l->cantidad_recontada??$l->cantidad_contada),$a['cpp']);
            }
            DB::table('inventario_conteos')->where('id',$id)->update(['estado'=>'EN_REVISION']);
            $this->auditoria($id,$uid,'CONTEO_REVISION',['estado'=>'EN_CONTEO'],['estado'=>'EN_REVISION','planes'=>$plans]);
            return ['id'=>$id,'estado'=>'EN_REVISION','planes'=>$plans,'movimiento_aplicado'=>false];
        },3))->header('Cache-Control','no-store');
    }
    private function revisionRegistrada(int $id): object {
        $rows=DB::table('registros_auditoria')->where('tipo_entidad','inventario_conteos')->where('entidad_id',$id)->where('accion','CONTEO_REVISION')->lockForUpdate()->get(['usuario_id','valores_nuevos']);
        abort_unless($rows->count()===1,409,'No hay una revisión única verificable.');
        return $rows->first();
    }
    private function prepararAprobacion(object $c,int $sid,int $uid): array {
        abort_unless($c->estado==='EN_REVISION'&&$c->operacion_ajuste_id===null,409,'Conteo no disponible para autorización.');
        $review=$this->revisionRegistrada((int)$c->id);$saved=json_decode($review->valores_nuevos,true,512,JSON_THROW_ON_ERROR);
        $snap=$this->corte($c);
        $lines=DB::table('inventario_conteos_detalle')->where('conteo_id',$c->id)->orderBy('producto_id')->lockForUpdate()->get();
        abort_unless($lines->count()===count($snap)&&$lines->count()>0&&$lines->pluck('producto_id')->unique()->count()===$lines->count(),409,'Detalle inconsistente.');
        $plans=[];$groups=[101=>[],102=>[]];$blocked=[];
        foreach($lines as $l){
            abort_unless($l->cantidad_contada!==null&&$l->contado_por!==null&&(int)$l->contado_por!==$uid,409,'Autorización independiente de los contadores.');
            if($l->cantidad_recontada!==null)abort_unless($l->recontado_por!==null&&(int)$l->recontado_por!==$uid&&(int)$l->recontado_por!==(int)$l->contado_por,409,'Autorización independiente del reconteo.');
            if(!BigDecimal::of((string)$l->cantidad_contada)->isEqualTo((string)$l->saldo_corte))abort_unless($l->cantidad_recontada!==null,409,'Reconteo pendiente.');
            $a=$this->vigente($c,$l,$snap,$sid);
            $p=(new PlanConteoInventario())->calcular($a['stock'],(string)($l->cantidad_recontada??$l->cantidad_contada),$a['cpp']);
            $plans[]=['producto_id'=>(int)$l->producto_id]+$p;
            if(!$p['requiere_ajuste'])continue;
            if(!$p['ajuste_calculable']){$blocked[]=['producto_id'=>(int)$l->producto_id,'bloqueos'=>$p['bloqueos']];continue;}
            // El motivo está en cabecera de operación, no en el detalle.
            // Agrupar sin crear operaciones ni asumir un vínculo que no existe.
            $groups[$p['motivo_sugerido']][]=['producto_id'=>(int)$l->producto_id,'conteo_detalle_id'=>(int)$l->id,'bodega_id'=>(int)$c->bodega_id,'cantidad'=>(string)BigDecimal::of($p['diferencia'])->abs()->toScale(8),'delta_disponible'=>$p['diferencia'],'cpp'=>$p['snapshot']['cpp']];
        }
        abort_unless(isset($saved['planes'])&&$saved['planes']===$plans,409,'La revisión registrada no coincide con el corte vigente.');
        $data=['conteo_id'=>(int)$c->id,'sucursal_id'=>$sid,'bodega_id'=>(int)$c->bodega_id,'revisor_id'=>(int)$review->usuario_id,'planes'=>$plans,'grupos'=>$groups,'bloqueos'=>$blocked];
        return $data+['huella'=>hash('sha256',json_encode($data,JSON_THROW_ON_ERROR))];
    }
    public function previsualizarAprobacion(Request $r,int $id){
        return response()->json(DB::transaction(function()use($r,$id){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;
            $c=$this->cabecera($id,$sid);$plan=$this->prepararAprobacion($c,$sid,(int)$r->user()->id);
            return $plan+['puede_autorizar'=>$plan['bloqueos']===[],'movimiento_aplicado'=>false];
        },3))->header('Cache-Control','no-store');
    }
    public function autorizarDiferencias(Request $r,int $id){
        $v=$r->validate(['huella'=>'required|string|size:64|regex:/^[a-f0-9]{64}$/D','motivo'=>'required|string|min:10|max:1000']);
        $obs=trim($v['motivo']);abort_if(mb_strlen($obs)<10,422,'Justificación insuficiente.');
        return response()->json(DB::transaction(function()use($r,$id,$v,$obs){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;$c=$this->cabecera($id,$sid);
            $plan=$this->prepararAprobacion($c,$sid,$uid);
            abort_unless(hash_equals($plan['huella'],$v['huella'])&&$plan['bloqueos']===[],409,'Plan modificado o con diferencias no ajustables.');
            $old=DB::table('registros_auditoria')->where('tipo_entidad','inventario_conteos')->where('entidad_id',$id)->where('accion','CONTEO_AUTORIZACION')->lockForUpdate()->get(['usuario_id','valores_nuevos']);
            if($old->isNotEmpty()){
                $first=$old->first();$a=json_decode($first->valores_nuevos,true,512,JSON_THROW_ON_ERROR);
                abort_unless($old->count()===1&&(int)$first->usuario_id===$uid&&($a['huella']??null)===$plan['huella']&&($a['motivo']??null)===$obs,409,'Ya existe autorización registrada.');
                return ['id'=>$id,'estado'=>'EN_REVISION','autorizado'=>true,'repetida'=>true,'movimiento_aplicado'=>false];
            }
            $this->auditoria($id,$uid,'CONTEO_AUTORIZACION',['estado'=>'EN_REVISION'],['estado'=>'EN_REVISION','huella'=>$plan['huella'],'motivo'=>$obs,'grupos'=>$plan['grupos']]);
            // No se altera el estado hasta confirmar ENUM final y relación de ajustes.
            return ['id'=>$id,'estado'=>'EN_REVISION','autorizado'=>true,'repetida'=>false,'movimiento_aplicado'=>false];
        },3))->header('Cache-Control','no-store');
    }
    public function ver(Request $r,int $id){
        return response()->json(DB::transaction(function()use($r,$id){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;$c=$this->cabecera($id,$sid);
            $lines=DB::table('inventario_conteos_detalle as l')->join('productos as p','p.id','=','l.producto_id')->where('l.conteo_id',$id)->orderBy('l.id')->select('l.*','p.sku','p.nombre as producto')->get();
            if((bool)$c->conteo_ciego&&$c->estado==='EN_CONTEO')foreach($lines as $l){
                $l->contado=$l->cantidad_contada!==null;$l->recontado=$l->cantidad_recontada!==null;
                unset($l->saldo_corte,$l->ultimo_kardex_id,$l->observaciones);
                if((int)$l->contado_por!==$uid)unset($l->cantidad_contada);
                if((int)$l->recontado_por!==$uid)unset($l->cantidad_recontada);
            }
            return ['conteo'=>$c,'detalles'=>$lines];
        },3))->header('Cache-Control','no-store');
    }
}
