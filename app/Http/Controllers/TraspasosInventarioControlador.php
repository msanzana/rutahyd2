<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\ContextoCompras;
use App\Services\PlanStockTraspaso;
use App\Services\LectorBaseValorizadaInventario;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** V109C5C1: solicitudes, aprobación, anulación y ejecución inicial de traspasos. */
final class TraspasosInventarioControlador extends Controller
{
    private function estado(string $code): int {
        $id=DB::table('modulos_estados as me')->join('modulos as m','m.id','=','me.modulo_id')->join('estados as e','e.id','=','me.estado_id')->where('m.codigo','INVENTARIO_OPERACION')->where('m.activo',1)->where('me.activo',1)->where('e.activo',1)->where('e.codigo',$code)->value('e.id');
        abort_unless($id,409,'Estado no configurado.');return (int)$id;
    }
    private function etapa(string $modalidad): string {return $modalidad==='INMEDIATO'?'INMEDIATO':'DESPACHO';}
    private function evaluar(int $sid,int $from,int $to,string $modalidad,array $items): array {
        abort_if($from===$to,422,'Seleccione bodegas diferentes.');
        $m=DB::table('inventario_motivos as m')->join('inventario_grupos_motivos as g','g.id','=','m.grupo_id')->join('inventario_tipos_movimiento as t','t.id','=','m.tipo_movimiento_id')->where('m.codigo',709)->where('m.activo',1)->where('g.activo',1)->where('t.activo',1)->select('m.*','g.codigo as grupo_codigo','t.naturaleza','t.codigo as tipo_codigo')->lockForUpdate()->first();
        abort_unless($m&&$m->tipo_codigo==='TRASLADO',409,'Motivo de traspaso no disponible.');
        $bids=[$from,$to];sort($bids);
        $b=DB::table('bodegas as b')->join('inventario_bodegas_sucursales as bs','bs.bodega_id','=','b.id')->where('bs.sucursal_id',$sid)->where('b.activo',1)->whereIn('b.id',$bids)->orderBy('b.id')->select('b.id')->lockForUpdate()->get();
        abort_unless($b->pluck('id')->unique()->count()===2,404,'Bodega no autorizada.');
        usort($items,fn($a,$b)=>(int)$a['producto_id']<=>(int)$b['producto_id']);
        $out=[];$zero=(object)['disponible'=>'0','transito'=>'0','comprometido'=>'0','cantidad_fisica'=>'0'];
        foreach($items as $i){
            $pid=(int)$i['producto_id'];abort_if(isset($out[$pid]),422,'Producto repetido.');
            $p=DB::table('productos')->where('id',$pid)->where('activo',1)->lockForUpdate()->first();abort_unless($p,404,'Producto no disponible.');
            abort_if(DB::table('saldos_inventario as s')->join('presentaciones_productos as pp','pp.id','=','s.presentacion_id')->where('pp.producto_id',$pid)->where('s.cantidad','<>',0)->exists(),409,'Inventario legado pendiente.');
            abort_if(DB::table('inventario_traspasos_detalle')->where('producto_id',$pid)->whereRaw('(cantidad_despachada-cantidad_recibida-cantidad_regularizada)>0')->exists(),409,'Producto con traspasos en camino.');
            $s=DB::table('stock')->where('producto_id',$pid)->whereIn('bodega_id',$bids)->orderBy('bodega_id')->lockForUpdate()->get()->keyBy('bodega_id');
            $line=(object)['cantidad_solicitada'=>$i['cantidad'],'cantidad_despachada'=>'0','cantidad_recibida'=>'0','cantidad_regularizada'=>'0'];
            $out[$pid]=(new PlanStockTraspaso())->calcular($m,$line,$modalidad,$this->etapa($modalidad),$i['cantidad'],$s->get($from)??$zero,$s->get($to)??$zero,(string)$p->costo_promedio_ponderado);
        }
        return [$m,$out];
    }
    private function historial(int $id,?int $previous,int $state,int $uid,string $action,string $obs): void {
        DB::table('inventario_operaciones_historial')->insert(['operacion_id'=>$id,'estado_anterior_id'=>$previous,'estado_nuevo_id'=>$state,'usuario_id'=>$uid,'accion'=>$action,'observaciones'=>$obs]);
        DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>'INVENTARIO_'.$action,'tipo_entidad'=>'inventario_operaciones','entidad_id'=>$id,'valores_anteriores'=>json_encode(['estado_id'=>$previous],JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['estado_id'=>$state,'observaciones'=>$obs],JSON_THROW_ON_ERROR)]);
    }
    public function crear(Request $r){
        $v=$r->validate(['bodega_origen_id'=>'required|integer|min:1','bodega_destino_id'=>'required|integer|min:1|different:bodega_origen_id','modalidad'=>'required|in:INMEDIATO,DESPACHO_RECEPCION','items'=>'required|array|min:1|max:50','items.*.producto_id'=>'required|integer|min:1|distinct','items.*.cantidad'=>['required','string','regex:/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D'],'justificacion'=>'required|string|min:10|max:4000','token_creacion'=>'required|uuid']);
        $just=trim($v['justificacion']);abort_if(mb_strlen($just)<10,422,'Justificación insuficiente.');
        $token=strtolower($v['token_creacion']);$from=(int)$v['bodega_origen_id'];$to=(int)$v['bodega_destino_id'];
        $result=DB::transaction(function()use($r,$v,$just,$token,$from,$to){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $old=DB::table('inventario_operaciones')->where('token_creacion',$token)->lockForUpdate()->first();
            if($old){
                abort_unless($old->clase==='TRASPASO'&&(int)$old->sucursal_id===$sid&&(int)$old->creado_por===$uid&&$old->justificacion===$just,409,'Token de otra solicitud.');
                $t=DB::table('inventario_traspasos as t')->join('inventario_traspasos_operaciones as x','x.traspaso_id','=','t.id')->where('x.operacion_id',$old->id)->select('t.*')->first();
                abort_unless($t&&(int)$t->sucursal_origen_id===$sid&&(int)$t->sucursal_destino_id===$sid&&(int)$t->bodega_origen_id===$from&&(int)$t->bodega_destino_id===$to&&$t->modalidad===$v['modalidad'],409,'Token de otra solicitud.');
                $d=DB::table('inventario_traspasos_detalle')->where('traspaso_id',$t->id)->get()->keyBy('producto_id');
                abort_unless($d->count()===count($v['items']),409,'Token de otra solicitud.');
                foreach($v['items'] as $i){$l=$d->get((int)$i['producto_id']);abort_unless($l&&BigDecimal::of($l->cantidad_solicitada)->isEqualTo($i['cantidad']),409,'Token de otra solicitud.');}
                return ['id'=>(int)$t->id,'operacion_id'=>(int)$old->id,'numero_interno'=>$t->numero_interno,'repetida'=>true];
            }
            [$m,$plans]=$this->evaluar($sid,$from,$to,$v['modalidad'],$v['items']);
            $state=$this->estado('INV_PEND_APROBACION');
            $id=DB::table('inventario_traspasos')->insertGetId(['bodega_origen_id'=>$from,'bodega_destino_id'=>$to,'sucursal_origen_id'=>$sid,'sucursal_destino_id'=>$sid,'modalidad'=>$v['modalidad'],'estado'=>'BORRADOR','creado_por'=>$uid]);
            $num='TRP-'.str_pad((string)$id,8,'0',STR_PAD_LEFT);
            DB::table('inventario_traspasos')->where('id',$id)->update(['numero_interno'=>$num]);
            $op=DB::table('inventario_operaciones')->insertGetId(['clase'=>'TRASPASO','sucursal_id'=>$sid,'motivo_id'=>$m->id,'estado_id'=>$state,'motivo_codigo_snapshot'=>709,'motivo_nombre_snapshot'=>$m->nombre,'regla_snapshot'=>$m->regla,'politica_cpp_snapshot'=>$m->politica_cpp,'justificacion'=>$just,'indicador_traslado_sii'=>5,'revision_tributaria'=>'NO_APLICA','evaluacion_aviso_sii'=>'NO_APLICA','token_creacion'=>$token,'creado_por'=>$uid]);
            DB::table('inventario_operaciones')->where('id',$op)->update(['numero_interno'=>'TRP-OP-'.str_pad((string)$op,8,'0',STR_PAD_LEFT)]);
            DB::table('inventario_traspasos_operaciones')->insert(['traspaso_id'=>$id,'operacion_id'=>$op,'etapa'=>$this->etapa($v['modalidad'])]);
            $n=0;
            foreach($plans as $pid=>$p){
                $q=$p['cantidades']['cantidad_movimiento'];$cpp=$p['costo_cpp_snapshot'];
                DB::table('inventario_traspasos_detalle')->insert(['traspaso_id'=>$id,'producto_id'=>$pid,'cantidad_solicitada'=>$q,'cantidad_despachada'=>0,'cantidad_recibida'=>0,'cantidad_regularizada'=>0,'costo_cpp_snapshot'=>$cpp]);
                foreach([[$from,'origen'],[$to,'destino']] as [$bid,$side]){
                    $s=$p[$side];
                    DB::table('inventario_operaciones_detalle')->insert(['operacion_id'=>$op,'numero_linea'=>++$n,'producto_id'=>$pid,'bodega_id'=>$bid,'cantidad'=>$q,'delta_disponible'=>$s['disponible']['delta'],'delta_transito'=>$s['transito']['delta'],'delta_comprometido'=>$s['comprometido']['delta'],'delta_fisico'=>$s['cantidad_fisica']['delta'],'costo_unitario_snapshot'=>$cpp]);
                }
            }
            $this->historial($op,null,$state,$uid,'CREAR','Solicitud '.$num.' pendiente de aprobación; sin movimiento físico.');
            return ['id'=>(int)$id,'operacion_id'=>(int)$op,'numero_interno'=>$num,'repetida'=>false];
        },3);
        return response()->json($result,$result['repetida']?200:201)->header('Cache-Control','no-store');
    }
    public function aprobar(Request $r,int $id){
        $v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','motivo'=>'required|string|min:3|max:1000']);
        $obs=trim($v['motivo']);abort_if(mb_strlen($obs)<3,422,'Motivo insuficiente.');
        return response()->json(DB::transaction(function()use($r,$id,$v,$obs){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            $t=DB::table('inventario_traspasos')->where('id',$id)->where('sucursal_origen_id',$sid)->where('sucursal_destino_id',$sid)->lockForUpdate()->first();
            abort_unless($t,404);abort_unless($t->estado==='BORRADOR',409,'Traspaso no editable.');
            $links=DB::table('inventario_traspasos_operaciones')->where('traspaso_id',$id)->lockForUpdate()->get();
            $link=$links->first();abort_unless($links->count()===1&&$link->etapa===$this->etapa($t->modalidad),409,'Etapa inválida.');
            $o=DB::table('inventario_operaciones')->where('id',$link->operacion_id)->where('sucursal_id',$sid)->where('clase','TRASPASO')->lockForUpdate()->first();
            $pending=$this->estado('INV_PEND_APROBACION');$approved=$this->estado('INV_APROBADA');
            abort_unless($o&&(int)$o->estado_id===$pending&&(int)$o->version===(int)$v['version']&&(int)$o->creado_por!==$uid&&(int)$o->motivo_codigo_snapshot===709,409,'Estado, versión o aprobador inválido.');
            $this->revisarGuardado($t,$o,$sid);
            DB::table('inventario_operaciones')->where('id',$o->id)->update(['estado_id'=>$approved,'version'=>$o->version+1,'aprobado_por'=>$uid,'aprobado_en'=>now()]);
            $this->historial((int)$o->id,$pending,$approved,$uid,'APROBAR',$obs);
            return ['id'=>$id,'operacion_id'=>(int)$o->id,'estado_operacion'=>'INV_APROBADA','estado_traspaso'=>'BORRADOR','version'=>(int)$o->version+1,'movimiento_aplicado'=>false];
        },3))->header('Cache-Control','no-store');
    }

    private function revisarGuardado(object $t,object $o,int $sid): array {
            $items=DB::table('inventario_traspasos_detalle')->where('traspaso_id',$t->id)->lockForUpdate()->get();
            abort_unless($items->count()>=1&&$items->count()<=50,409,'Detalle inválido.');
            foreach($items as $l)foreach(['cantidad_despachada','cantidad_recibida','cantidad_regularizada'] as $k)abort_unless(BigDecimal::of($l->$k)->isZero(),409,'Traspaso ya iniciado.');
            [$m,$plans]=$this->evaluar($sid,(int)$t->bodega_origen_id,(int)$t->bodega_destino_id,$t->modalidad,$items->map(fn($l)=>['producto_id'=>$l->producto_id,'cantidad'=>(string)$l->cantidad_solicitada])->all());
            abort_unless((int)$m->id===(int)$o->motivo_id&&$m->regla===$o->regla_snapshot&&$m->politica_cpp===$o->politica_cpp_snapshot&&(int)$o->indicador_traslado_sii===5,409,'Regla modificada.');
            $details=DB::table('inventario_operaciones_detalle')->where('operacion_id',$o->id)->lockForUpdate()->get();
            abort_unless($details->count()===count($plans)*2,409,'Detalle de operación inválido.');
            foreach($items as $l){
                $p=$plans[$l->producto_id];abort_unless($l->costo_cpp_snapshot!==null&&BigDecimal::of($l->costo_cpp_snapshot)->isEqualTo($p['costo_cpp_snapshot']),409,'CPP cambió; genere nueva solicitud.');
                foreach([[$t->bodega_origen_id,'origen'],[$t->bodega_destino_id,'destino']] as [$bid,$side]){
                    $matches=$details->filter(fn($d)=>(int)$d->producto_id===(int)$l->producto_id&&(int)$d->bodega_id===(int)$bid);
                    $d=$matches->first();abort_unless($matches->count()===1&&$d->kardex_id===null&&$d->kardex_referencia_id===null&&BigDecimal::of($d->cantidad)->isEqualTo($l->cantidad_solicitada)&&BigDecimal::of($d->costo_unitario_snapshot)->isEqualTo($p['costo_cpp_snapshot']),409,'Detalle alterado.');
                    foreach(['disponible'=>'delta_disponible','transito'=>'delta_transito','comprometido'=>'delta_comprometido','cantidad_fisica'=>'delta_fisico'] as $k=>$f)abort_unless(BigDecimal::of($d->$f)->isEqualTo($p[$side][$k]['delta']),409,'Detalle alterado.');
                }
            }
        return [$items,$details,$plans];
    }
    private function cargar(int $id,int $sid): array {
        $t=DB::table('inventario_traspasos')->where('id',$id)->where('sucursal_origen_id',$sid)->where('sucursal_destino_id',$sid)->lockForUpdate()->first();abort_unless($t,404);
        $links=DB::table('inventario_traspasos_operaciones')->where('traspaso_id',$id)->lockForUpdate()->get();
        $link=$links->first();abort_unless($links->count()===1&&$link->etapa===$this->etapa($t->modalidad),409,'Etapa inválida.');
        $o=DB::table('inventario_operaciones')->where('id',$link->operacion_id)->where('sucursal_id',$sid)->where('clase','TRASPASO')->lockForUpdate()->first();
        abort_unless($o&&(int)$o->motivo_codigo_snapshot===709,409,'Operación de traspaso inválida.');
        return [$t,$o];
    }
    public function anular(Request $r,int $id){
        $v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','motivo'=>'required|string|min:3|max:1000']);
        $obs=trim($v['motivo']);abort_if(mb_strlen($obs)<3,422,'Motivo insuficiente.');
        return response()->json(DB::transaction(function()use($r,$id,$v,$obs){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            [$t,$o]=$this->cargar($id,$sid);
            abort_unless($t->estado==='BORRADOR'&&in_array((int)$o->estado_id,[$this->estado('INV_PEND_APROBACION'),$this->estado('INV_APROBADA')],true)&&(int)$o->version===(int)$v['version'],409,'Solo solicitudes sin ejecución pueden anularse.');
            abort_if($t->guia_despacho_id!==null||DB::table('inventario_operaciones_detalle')->where('operacion_id',$o->id)->whereNotNull('kardex_id')->exists(),409,'Hay documentos o movimientos asociados.');
            $items=DB::table('inventario_traspasos_detalle')->where('traspaso_id',$id)->lockForUpdate()->get();
            foreach($items as $l)foreach(['cantidad_despachada','cantidad_recibida','cantidad_regularizada'] as $f)abort_unless(BigDecimal::of($l->$f)->isZero(),409,'Traspaso iniciado.');
            $state=$this->estado('INV_ANULADA');
            // El enum de cabecera aún no está completo: la operación vinculada es la fuente de estado de anulación.
            DB::table('inventario_operaciones')->where('id',$o->id)->update(['estado_id'=>$state,'version'=>$o->version+1,'anulado_por'=>$uid,'anulado_en'=>now(),'motivo_anulacion'=>$obs]);
            $this->historial((int)$o->id,(int)$o->estado_id,$state,$uid,'ANULAR',$obs);
            return ['id'=>$id,'estado_operacion'=>'INV_ANULADA','version'=>(int)$o->version+1,'movimiento_aplicado'=>false];
        },3))->header('Cache-Control','no-store');
    }
    public function confirmar(Request $r,int $id){return $this->ejecutarInicial($r,$id,false);}
    public function despachar(Request $r,int $id){return $this->ejecutarInicial($r,$id,true);}
    private function ejecutarInicial(Request $r,int $id,bool $despacho){
        $v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','token_confirmacion'=>'required|uuid']);$token=strtolower($v['token_confirmacion']);
        return response()->json(DB::transaction(function()use($r,$id,$v,$token,$despacho){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$uid=(int)$r->user()->id;
            [$t,$o]=$this->cargar($id,$sid);$approved=$this->estado('INV_APROBADA');$done=$this->estado('INV_CONFIRMADA');
            $modalidad=$despacho?'DESPACHO_RECEPCION':'INMEDIATO';$estadoFinal=$despacho?'DESPACHADO':'RECIBIDO';
            abort_unless($t->modalidad===$modalidad,409,'Modalidad incompatible con esta acción.');
            if((int)$o->estado_id===$done&&$t->estado===$estadoFinal&&$o->token_confirmacion===$token&&(int)$o->confirmado_por===$uid)return ['id'=>$id,'estado_operacion'=>'INV_CONFIRMADA','estado_traspaso'=>$estadoFinal,'version'=>(int)$o->version];
            abort_unless($t->estado==='BORRADOR'&&$t->guia_despacho_id===null,409,'Traspaso no ejecutable sin validar su documentación.');
            abort_unless((int)$o->estado_id===$approved&&(int)$o->version===(int)$v['version']&&$o->aprobado_por&&(int)$o->aprobado_por!==(int)$o->creado_por,409,'Se requiere aprobación y versión vigente.');
            abort_if(DB::table('inventario_operaciones')->where('token_confirmacion',$token)->exists(),409,'Token utilizado.');
            [$items,$details,$plans]=$this->revisarGuardado($t,$o,$sid);
            $bases=[];$lector=new LectorBaseValorizadaInventario();
            foreach($items->sortBy('producto_id') as $l){
                $pid=(int)$l->producto_id;$plan=$plans[$pid];$p=DB::table('productos')->where('id',$pid)->lockForUpdate()->first();
                if($despacho)$bases[$pid]=$lector->leer($pid,DB::table('stock')->where('producto_id',$pid)->orderBy('bodega_id')->lockForUpdate()->get())['stock_valorizado'];
                foreach([[$t->bodega_origen_id,'origen'],[$t->bodega_destino_id,'destino']] as [$bid,$side]){
                    $b=(int)$bid;$d=$details->first(fn($d)=>(int)$d->producto_id===$pid&&(int)$d->bodega_id===$b);$s=$plan[$side];
                    $stock=DB::table('stock')->where('producto_id',$pid)->where('bodega_id',$b)->lockForUpdate()->first();
                    $stockId=$stock->id??DB::table('stock')->insertGetId(['producto_id'=>$pid,'bodega_id'=>$b,'disponible'=>0,'transito'=>0,'comprometido'=>0,'cantidad_fisica'=>0]);
                    $k=DB::table('kardex')->insertGetId(['sucursal_id'=>$sid,'producto_id'=>$pid,'bodega_id'=>$b,'tipo_documento_id'=>null,'folio_documento'=>$t->numero_interno,'tipo_movimiento'=>$side==='origen'?2:($despacho?3:1),'cantidad_anterior'=>$s['disponible']['anterior'],'cantidad_documento'=>$plan['cantidades']['cantidad_movimiento'],'cantidad_actual'=>$s['disponible']['posterior'],'transito_anterior'=>$s['transito']['anterior'],'transito_actual'=>$s['transito']['posterior'],'comprometido_anterior'=>$s['comprometido']['anterior'],'comprometido_actual'=>$s['comprometido']['posterior'],'fisico_anterior'=>$s['cantidad_fisica']['anterior'],'fisico_actual'=>$s['cantidad_fisica']['posterior'],'costo_unitario_neto'=>$plan['costo_cpp_snapshot'],'ultimo_costo_anterior'=>$p->costo_neto,'ultimo_costo_actual'=>$p->costo_neto,'cpp_anterior'=>$plan['cpp_anterior'],'cpp_actual'=>$plan['cpp_actual'],'origen_tipo'=>'AJUSTE_INVENTARIO','origen_detalle_id'=>$d->id,'operacion_codigo'=>$despacho?'TRASPASO_DESPACHO':'TRASPASO_INTERNO','token_idempotencia'=>(string)Str::uuid(),'registrado_por'=>$uid,'observaciones'=>$t->numero_interno.' | '.$o->justificacion]);
                    DB::table('stock')->where('id',$stockId)->update(['disponible'=>$s['disponible']['posterior'],'transito'=>$s['transito']['posterior'],'cantidad_fisica'=>$s['cantidad_fisica']['posterior']]);
                    DB::table('inventario_operaciones_detalle')->where('id',$d->id)->update(['kardex_id'=>$k]);
                }
                DB::table('inventario_traspasos_detalle')->where('id',$l->id)->update(['cantidad_despachada'=>$l->cantidad_solicitada,'cantidad_recibida'=>$despacho?'0.00000000':$l->cantidad_solicitada]);
            }
            DB::table('inventario_traspasos')->where('id',$id)->update(['estado'=>$estadoFinal]);
            DB::table('inventario_operaciones')->where('id',$o->id)->update(['estado_id'=>$done,'version'=>$o->version+1,'confirmado_por'=>$uid,'confirmado_en'=>now(),'token_confirmacion'=>$token]);
            if($despacho)foreach($bases as $pid=>$base){
                $actual=$lector->leer((int)$pid,DB::table('stock')->where('producto_id',$pid)->orderBy('bodega_id')->lockForUpdate()->get());
                abort_unless(BigDecimal::of($actual['stock_valorizado'])->isEqualTo($base),409,'El despacho alteró la base valorizada.');
            }
            $this->historial((int)$o->id,$approved,$done,$uid,'CONFIRMAR',$despacho?'Despacho interno confirmado; tránsito valorizado y CPP conservado.':'Traspaso inmediato interno; CPP conservado, sin emisión de DTE.');
            return ['id'=>$id,'estado_operacion'=>'INV_CONFIRMADA','estado_traspaso'=>$estadoFinal,'version'=>(int)$o->version+1];
        },3))->header('Cache-Control','no-store');
    }
}
