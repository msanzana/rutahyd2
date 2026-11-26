<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\ContextoCompras;
use App\Services\PlanAjusteGeneral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Brick\Math\BigDecimal;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** V109B3: ajustes generales con respaldo, aprobación y confirmación transaccional. */
final class AjustesGeneralesControlador extends Controller
{
    private function reglas(): array {
        return [
            'motivo_codigo'=>'required|integer|in:101,102,107',
            'producto_id'=>'required|integer|min:1',
            'bodega_id'=>'required|integer|min:1',
            'cantidad'=>['required','string','regex:/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D'],
        ];
    }
    private function evaluar(array $v,int $sid): array {
            abort_unless(DB::transactionLevel()>0,500,'Evaluación sin transacción.');
            $m=DB::table('inventario_motivos as m')
              ->join('inventario_grupos_motivos as g','g.id','=','m.grupo_id')
              ->join('inventario_tipos_movimiento as t','t.id','=','m.tipo_movimiento_id')
              ->where('m.codigo',$v['motivo_codigo'])->where('m.activo',1)->where('g.activo',1)->where('t.activo',1)
              ->select('m.*','g.codigo as grupo_codigo','t.naturaleza','t.codigo as tipo_codigo')->lockForUpdate()->first();
            abort_unless($m&&$m->tipo_codigo===$m->naturaleza,409,'Motivo o tipo de movimiento no disponible.');
            $b=DB::table('bodegas as b')->join('inventario_bodegas_sucursales as bs','bs.bodega_id','=','b.id')
              ->where('b.id',$v['bodega_id'])->where('bs.sucursal_id',$sid)->where('b.activo',1)
              ->select('b.id','b.nombre')->lockForUpdate()->first();
            abort_unless($b,404,'Bodega no disponible en la sucursal.');
            $p=DB::table('productos')->where('id',$v['producto_id'])->where('activo',1)
              ->select('id','sku','nombre','costo_neto','costo_promedio_ponderado')->lockForUpdate()->first();
            abort_unless($p,404,'Producto no disponible.');
            abort_if(DB::table('saldos_inventario as s')->join('presentaciones_productos as pp','pp.id','=','s.presentacion_id')->where('pp.producto_id',$p->id)->where('s.cantidad','<>',0)->exists(),409,'Inventario anterior pendiente de conciliación.');
            abort_if(DB::table('inventario_traspasos_detalle')->where('producto_id',$p->id)->whereRaw('cantidad_despachada>cantidad_recibida+cantidad_regularizada')->exists(),409,'Producto con traspasos en camino.');
            $s=DB::table('stock')->where('producto_id',$p->id)->where('bodega_id',$b->id)->lockForUpdate()->first()
              ??(object)['disponible'=>'0','transito'=>'0','comprometido'=>'0','cantidad_fisica'=>'0'];
            $plan=(new PlanAjusteGeneral())->calcular($m,$v['cantidad'],$s,(string)$p->costo_promedio_ponderado);
            return [$m,$b,$p,$plan,$s];
    }
    public function revisar(Request $r)
    {
        $v=$r->validate($this->reglas());
        return response()->json(DB::transaction(function()use($r,$v){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;
            [$m,$b,$p,$plan]=$this->evaluar($v,$sid);
            return ['sucursal_id'=>$sid,'producto'=>['id'=>$p->id,'sku'=>$p->sku,'nombre'=>$p->nombre],'bodega'=>$b,'motivo'=>['codigo'=>$m->codigo,'nombre'=>$m->nombre],'plan'=>$plan,'solo_previsualizacion'=>true,'puede_confirmar'=>false,'mensaje'=>'Simulación. No guarda ni modifica stock. La confirmación requiere respaldo, aprobación y nueva validación.'];
        },3))->header('Cache-Control','no-store');
    }
    private function estado(string $codigo): int {
        $id=DB::table('modulos_estados as me')->join('modulos as m','m.id','=','me.modulo_id')->join('estados as e','e.id','=','me.estado_id')->where('m.codigo','INVENTARIO_OPERACION')->where('m.activo',1)->where('me.activo',1)->where('e.activo',1)->where('e.codigo',$codigo)->value('e.id');
        abort_unless($id,409,'Estado de inventario no configurado.');return (int)$id;
    }
    private function archivos(Request $r): array {
        $out=[];$total=0;
        foreach($r->file('respaldos',[]) as $file){
            abort_unless($file->isValid(),422,'Archivo no válido.');
            $data=$file->get();$total+=strlen($data);
            abort_if($total>2*1024*1024||strlen($data)===0,422,'Los respaldos no pueden superar 2 MB en conjunto.');
            $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($data);
            $ext=match($mime){'application/pdf'=>'pdf','image/png'=>'png','image/jpeg'=>'jpg',default=>null};
            abort_unless($ext,422,'Solo se permiten PDF, PNG y JPEG.');
            $raw=basename(str_replace('\\','/',$file->getClientOriginalName()));
            $name=preg_replace('/[^\p{L}\p{N} _.-]/u','_',pathinfo($raw,PATHINFO_FILENAME))??'respaldo';
            $name=mb_substr(trim($name,' ._'),0,175);
            $out[]=['nombre'=>($name?:'respaldo').'.'.$ext,'mime'=>$mime,'contenido'=>$data,'sha256'=>hash('sha256',$data)];
        }
        abort_unless($out,422,'Adjunte al menos un respaldo.');
        return $out;
    }
    public function crear(Request $r)
    {
        $v=$r->validate($this->reglas()+['justificacion'=>'required|string|min:10|max:4000','token_creacion'=>'required|uuid','respaldos'=>'required|array|min:1|max:3','respaldos.*'=>'required|file|max:2048']);
        $just=trim($v['justificacion']);abort_if(mb_strlen($just)<10,422,'Justificación insuficiente.');
        $files=$this->archivos($r);$token=strtolower($v['token_creacion']);
        $result=DB::transaction(function()use($r,$v,$just,$files,$token){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $old=DB::table('inventario_operaciones')->where('token_creacion',$token)->lockForUpdate()->first();
            if($old){
                abort_unless((int)$old->sucursal_id===$sid&&$old->clase==='AJUSTE'&&(int)$old->creado_por===$uid&&(int)$old->motivo_codigo_snapshot===(int)$v['motivo_codigo']&&$old->justificacion===$just,409,'Token usado en otra solicitud.');
                $lines=DB::table('inventario_operaciones_detalle')->where('operacion_id',$old->id)->get();
                $l=$lines->first();
                $stored=DB::table('inventario_operaciones_adjuntos')->where('operacion_id',$old->id)->orderBy('id')->get(['nombre','mime','sha256'])->map(fn($x)=>(array)$x)->all();
                $requested=array_map(fn($x)=>['nombre'=>$x['nombre'],'mime'=>$x['mime'],'sha256'=>$x['sha256']],$files);
                abort_unless($lines->count()===1&&(int)$l->producto_id===(int)$v['producto_id']&&(int)$l->bodega_id===(int)$v['bodega_id']&&BigDecimal::of($l->cantidad)->isEqualTo($v['cantidad'])&&$stored===$requested,409,'Token usado en otra solicitud.');
                return ['id'=>(int)$old->id,'numero_interno'=>$old->numero_interno,'repetida'=>true];
            }
            [$m,$b,$p,$plan]=$this->evaluar($v,$sid);
            $state=$this->estado('INV_PEND_APROBACION');
            $id=DB::table('inventario_operaciones')->insertGetId(['sucursal_id'=>$sid,'clase'=>'AJUSTE','motivo_id'=>$m->id,'estado_id'=>$state,'motivo_codigo_snapshot'=>$m->codigo,'motivo_nombre_snapshot'=>$m->nombre,'regla_snapshot'=>$m->regla,'politica_cpp_snapshot'=>$m->politica_cpp,'justificacion'=>$just,'revision_tributaria'=>'PENDIENTE','evaluacion_aviso_sii'=>'NO_APLICA','token_creacion'=>$token,'creado_por'=>$uid]);
            $num='AJG-'.str_pad((string)$id,8,'0',STR_PAD_LEFT);
            DB::table('inventario_operaciones')->where('id',$id)->update(['numero_interno'=>$num]);
            DB::table('inventario_operaciones_detalle')->insert(['operacion_id'=>$id,'numero_linea'=>1,'producto_id'=>$p->id,'bodega_id'=>$b->id,'cantidad'=>$plan['cantidad'],'delta_disponible'=>$plan['delta_disponible'],'delta_fisico'=>$plan['delta_fisico'],'delta_transito'=>$plan['delta_transito'],'delta_comprometido'=>$plan['delta_comprometido'],'costo_unitario_snapshot'=>$plan['costo_unitario_snapshot']]);
            foreach($files as $f)DB::table('inventario_operaciones_adjuntos')->insert($f+['operacion_id'=>$id,'creado_por'=>$uid]);
            DB::table('inventario_operaciones_historial')->insert(['operacion_id'=>$id,'estado_anterior_id'=>null,'estado_nuevo_id'=>$state,'usuario_id'=>$uid,'accion'=>'CREAR','observaciones'=>'Ajuste con respaldo pendiente de aprobación.']);
            DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>'INVENTARIO_CREAR','tipo_entidad'=>'inventario_operaciones','entidad_id'=>$id,'valores_anteriores'=>json_encode(null,JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['estado_id'=>$state,'sucursal_id'=>$sid,'respaldos'=>array_column($files,'sha256')],JSON_THROW_ON_ERROR)]);
            return ['id'=>(int)$id,'numero_interno'=>$num,'repetida'=>false];
        },3);
        return response()->json($result,$result['repetida']?200:201)->header('Cache-Control','no-store');
    }
    private function base(int $sid){
        return DB::table('inventario_operaciones as o')->join('estados as e','e.id','=','o.estado_id')->where('o.clase','AJUSTE')->where('o.sucursal_id',$sid)->where('e.codigo','<>','INV_ANULADA')->select('o.*','e.codigo as estado_codigo','e.nombre as estado_nombre');
    }
    public function listar(Request $r){
        $v=$r->validate(['buscar'=>'nullable|string|max:120','page'=>'nullable|integer|min:1']);
        $q=$this->base((int)app(ContextoCompras::class)->resolver($r)->id);
        if(trim($v['buscar']??'')!==''){$s=trim($v['buscar']);$q->where(fn($x)=>$x->where('o.numero_interno','like','%'.$s.'%')->orWhere('o.motivo_nombre_snapshot','like','%'.$s.'%'));}
        return response()->json($q->orderByDesc('o.id')->paginate(25))->header('Cache-Control','no-store');
    }
    public function ver(Request $r,int $id){
        $sid=(int)app(ContextoCompras::class)->resolver($r)->id;
        $o=$this->base($sid)->where('o.id',$id)->first();abort_unless($o,404);
        $d=DB::table('inventario_operaciones_detalle as d')->join('productos as p','p.id','=','d.producto_id')->join('bodegas as b','b.id','=','d.bodega_id')->where('d.operacion_id',$id)->orderBy('d.numero_linea')->get(['d.*','p.sku','p.nombre as producto','b.nombre as bodega']);
        $a=DB::table('inventario_operaciones_adjuntos')->where('operacion_id',$id)->orderBy('id')->get(['id','nombre','mime','sha256','creado_en']);
        return response()->json(['operacion'=>$o,'detalles'=>$d,'respaldos'=>$a])->header('Cache-Control','no-store');
    }
    public function respaldo(Request $r,int $id,int $adjunto): Response {
        $sid=(int)app(ContextoCompras::class)->resolver($r)->id;
        abort_unless($this->base($sid)->where('o.id',$id)->exists(),404);
        $a=DB::table('inventario_operaciones_adjuntos')->where('id',$adjunto)->where('operacion_id',$id)->first();
        abort_unless($a,404);abort_unless(hash_equals($a->sha256,hash('sha256',$a->contenido)),409,'Respaldo inconsistente.');
        $ext=match($a->mime){'application/pdf'=>'pdf','image/png'=>'png','image/jpeg'=>'jpg',default=>'bin'};
        return response($a->contenido,200,['Content-Type'=>'application/octet-stream','Content-Disposition'=>'attachment; filename="respaldo-'.$adjunto.'.'.$ext.'"','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }

    private function operacionBloqueada(int $id,int $sid): object {
        $o=DB::table('inventario_operaciones')->where('id',$id)->where('sucursal_id',$sid)->where('clase','AJUSTE')->lockForUpdate()->first();
        abort_unless($o,404,'Ajuste no encontrado.');return $o;
    }
    private function revisarGuardado(object $o,int $sid): array {
        $lines=DB::table('inventario_operaciones_detalle')->where('operacion_id',$o->id)->lockForUpdate()->get();
        $l=$lines->first();
        abort_unless($lines->count()===1&&(int)$l->numero_linea===1&&$l->kardex_id===null&&$l->kardex_referencia_id===null&&$o->referencia_kardex_id===null,409,'Detalle de ajuste inválido.');
        [$m,$b,$p,$plan,$stock]=$this->evaluar(['motivo_codigo'=>$o->motivo_codigo_snapshot,'producto_id'=>$l->producto_id,'bodega_id'=>$l->bodega_id,'cantidad'=>(string)$l->cantidad],$sid);
        abort_unless((int)$m->id===(int)$o->motivo_id&&$m->regla===$o->regla_snapshot&&$m->politica_cpp===$o->politica_cpp_snapshot,409,'Regla del motivo modificada.');
        foreach(['cantidad','delta_disponible','delta_fisico','delta_transito','delta_comprometido','costo_unitario_snapshot'] as $field){
            abort_unless($l->$field!==null&&BigDecimal::of($l->$field)->isEqualTo($plan[$field]),409,'Detalle o CPP modificado. Anule y genere un nuevo ajuste.');
        }
        $files=DB::table('inventario_operaciones_adjuntos')->where('operacion_id',$o->id)->orderBy('id')->lockForUpdate()->get();
        abort_unless($files->count()>=1&&$files->count()<=3,409,'Respaldo obligatorio.');
        $total=0;
        foreach($files as $f){
            $total+=strlen($f->contenido);
            abort_unless($total<=2*1024*1024&&strlen($f->contenido)>0&&hash_equals($f->sha256,hash('sha256',$f->contenido))&&in_array($f->mime,['application/pdf','image/jpeg','image/png'],true)&&(new \finfo(FILEINFO_MIME_TYPE))->buffer($f->contenido)===$f->mime,409,'Respaldo inválido o alterado.');
        }
        return [$l,$p,$plan,$stock];
    }
    private function registrarEstado(object $o,int $next,int $uid,string $action,string $obs,array $data): void {
        DB::table('inventario_operaciones')->where('id',$o->id)->update($data+['estado_id'=>$next,'version'=>(int)$o->version+1]);
        DB::table('inventario_operaciones_historial')->insert(['operacion_id'=>$o->id,'estado_anterior_id'=>$o->estado_id,'estado_nuevo_id'=>$next,'usuario_id'=>$uid,'accion'=>$action,'observaciones'=>$obs]);
        DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>'INVENTARIO_'.$action,'tipo_entidad'=>'inventario_operaciones','entidad_id'=>$o->id,'valores_anteriores'=>json_encode(['estado_id'=>$o->estado_id],JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['estado_id'=>$next,'sucursal_id'=>$o->sucursal_id,'observaciones'=>$obs],JSON_THROW_ON_ERROR)]);
    }
    public function aprobar(Request $r,int $id){return $this->transicion($r,$id,true);}
    public function anular(Request $r,int $id){return $this->transicion($r,$id,false);}
    private function transicion(Request $r,int $id,bool $approve){
        $v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','motivo'=>'required|string|min:3|max:1000']);
        $obs=trim($v['motivo']);abort_if(mb_strlen($obs)<3,422,'Motivo insuficiente.');
        return response()->json(DB::transaction(function()use($r,$id,$v,$obs,$approve){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $o=$this->operacionBloqueada($id,$sid);$pending=$this->estado('INV_PEND_APROBACION');$approved=$this->estado('INV_APROBADA');
            abort_unless((int)$o->version===(int)$v['version']&&in_array((int)$o->estado_id,$approve?[$pending]:[$pending,$approved],true),409,'Estado o versión cambió.');
            if($approve){
                abort_if((int)$o->creado_por===$uid,409,'La aprobación requiere otro usuario autorizado.');
                $this->revisarGuardado($o,$sid);
                $this->registrarEstado($o,$approved,$uid,'APROBAR',$obs,['aprobado_por'=>$uid,'aprobado_en'=>now()]);
            }else{
                $this->registrarEstado($o,$this->estado('INV_ANULADA'),$uid,'ANULAR',$obs,['anulado_por'=>$uid,'anulado_en'=>now(),'motivo_anulacion'=>$obs]);
            }
            return ['id'=>$id,'estado'=>$approve?'INV_APROBADA':'INV_ANULADA','version'=>(int)$o->version+1];
        },3))->header('Cache-Control','no-store');
    }
    public function confirmar(Request $r,int $id){
        $v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','token_confirmacion'=>'required|uuid']);$token=strtolower($v['token_confirmacion']);
        return response()->json(DB::transaction(function()use($r,$v,$id,$token){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $o=$this->operacionBloqueada($id,$sid);$approved=$this->estado('INV_APROBADA');$done=$this->estado('INV_CONFIRMADA');
            if((int)$o->estado_id===$done&&$o->token_confirmacion===$token&&(int)$o->confirmado_por===$uid)return ['id'=>$id,'estado'=>'INV_CONFIRMADA','version'=>(int)$o->version];
            abort_unless((int)$o->estado_id===$approved&&(int)$o->version===(int)$v['version']&&$o->aprobado_por&&(int)$o->aprobado_por!==(int)$o->creado_por,409,'Se requiere aprobación y versión vigente.');
            abort_if(DB::table('inventario_operaciones')->where('token_confirmacion',$token)->exists(),409,'Token de confirmación utilizado.');
            [$l,$p,$plan,$s]=$this->revisarGuardado($o,$sid);
            $stockId=$s->id??null;
            if(!$stockId)$stockId=DB::table('stock')->insertGetId(['producto_id'=>$l->producto_id,'bodega_id'=>$l->bodega_id,'disponible'=>0,'transito'=>0,'comprometido'=>0,'cantidad_fisica'=>0]);
            $movement=DB::table('kardex')->insertGetId([
                'sucursal_id'=>$sid,'producto_id'=>$l->producto_id,'bodega_id'=>$l->bodega_id,
                'tipo_documento_id'=>null,'folio_documento'=>$o->numero_interno,'tipo_movimiento'=>(int)$o->motivo_codigo_snapshot===102?2:1,
                'cantidad_anterior'=>$plan['disponible_anterior'],'cantidad_documento'=>$plan['cantidad'],'cantidad_actual'=>$plan['disponible_actual'],
                'transito_anterior'=>$plan['transito'],'transito_actual'=>$plan['transito'],'comprometido_anterior'=>$plan['comprometido'],'comprometido_actual'=>$plan['comprometido'],
                'fisico_anterior'=>$plan['fisico_anterior'],'fisico_actual'=>$plan['fisico_actual'],'costo_unitario_neto'=>$plan['costo_unitario_snapshot'],
                'ultimo_costo_anterior'=>$p->costo_neto,'ultimo_costo_actual'=>$p->costo_neto,'cpp_anterior'=>$plan['cpp_anterior'],'cpp_actual'=>$plan['cpp_actual'],
                'origen_tipo'=>'AJUSTE_INVENTARIO','origen_detalle_id'=>$l->id,'operacion_codigo'=>$o->regla_snapshot,'token_idempotencia'=>(string)Str::uuid(),
                'registrado_por'=>$uid,'observaciones'=>$o->numero_interno.' | '.$o->motivo_nombre_snapshot.' | '.$o->justificacion,
            ]);
            DB::table('stock')->where('id',$stockId)->update(['disponible'=>$plan['disponible_actual'],'cantidad_fisica'=>$plan['fisico_actual']]);
            DB::table('inventario_operaciones_detalle')->where('id',$l->id)->update(['kardex_id'=>$movement]);
            $this->registrarEstado($o,$done,$uid,'CONFIRMAR','Ajuste físico confirmado. CPP vigente conservado; revisión tributaria pendiente.',['confirmado_por'=>$uid,'confirmado_en'=>now(),'token_confirmacion'=>$token]);
            return ['id'=>$id,'estado'=>'INV_CONFIRMADA','version'=>(int)$o->version+1];
        },3))->header('Cache-Control','no-store');
    }
}
