<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class InventarioOperacionesControlador extends Controller
{
    private const RAPIDOS=[201,203,207];
    private const REGLAS=[201=>['CORREGIR_DESPACHO_SKU','HISTORICO'],203=>['CORREGIR_DESPACHO_BODEGA','CONSERVAR'],207=>['DESPACHO_NO_REALIZADO','HISTORICO']];
    private function decimal($x): BigDecimal { return BigDecimal::of((string)$x); }
    private function saldo(BigDecimal $x): string {
        abort_if($x->isNegative()||$x->isGreaterThan('99999999999999999999.99999999'),409,'Saldo fuera de rango.');
        return (string)$x->toScale(8);
    }
    private function estado(string $codigo): int {
        $id=DB::table('modulos_estados as me')->join('modulos as m','m.id','=','me.modulo_id')->join('estados as e','e.id','=','me.estado_id')
          ->where('m.codigo','INVENTARIO_OPERACION')->where('m.activo',1)->where('me.activo',1)->where('e.activo',1)->where('e.codigo',$codigo)->value('e.id');
        abort_unless($id,409,'Falta configurar los estados de inventario.');return (int)$id;
    }
    private function usuario(Request $r): int { $id=(int)$r->user()->id;abort_unless($id>0,401);return $id; }
    private function historial(int $id,?int $prev,int $next,int $user,string $action,?string $obs=null): void {
        DB::table('inventario_operaciones_historial')->insert(['operacion_id'=>$id,'estado_anterior_id'=>$prev,'estado_nuevo_id'=>$next,'usuario_id'=>$user,'accion'=>$action,'observaciones'=>$obs]);
        DB::table('registros_auditoria')->insert(['usuario_id'=>$user,'accion'=>'INVENTARIO_'.$action,'tipo_entidad'=>'inventario_operaciones','entidad_id'=>$id,'valores_anteriores'=>json_encode(['estado_id'=>$prev],JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['estado_id'=>$next,'observaciones'=>$obs],JSON_THROW_ON_ERROR)]);
    }
    public function catalogos(Request $r) {
        $v=$r->validate(['buscar'=>'nullable|string|max:120','grupo'=>'nullable|integer','page'=>'nullable|integer|min:1','rapido'=>'nullable|boolean']);
        $q=DB::table('inventario_motivos as m')->join('inventario_grupos_motivos as g','g.id','=','m.grupo_id')->join('inventario_tipos_movimiento as t','t.id','=','m.tipo_movimiento_id')
          ->select('m.*','g.nombre as grupo','g.codigo as grupo_codigo','t.naturaleza');
        if(!empty($v['grupo']))$q->where('g.codigo',$v['grupo']);
        if(!empty($v['rapido']))$q->where('m.activo',1)->where('g.activo',1)->where('t.activo',1)->where('m.ajuste_rapido',1)->whereIn('m.codigo',self::RAPIDOS);
        if(isset($v['buscar'])&&trim($v['buscar'])!==''){$s=trim($v['buscar']);$q->where(fn($x)=>$x->where('m.nombre','like','%'.$s.'%')->orWhere('m.codigo',$s));}
        $result=$q->orderBy('m.codigo')->paginate(25)->toArray();
        $result['data']=array_map(function($x){$x->habilitado_motor=in_array((int)$x->codigo,self::RAPIDOS,true);return $x;},$result['data']);
        $result['grupos']=DB::table('inventario_grupos_motivos')->orderBy('codigo')->get();
        $result['tipos']=DB::table('inventario_tipos_movimiento')->orderBy('id')->get();
        $result['indicadores_sii']=DB::table('inventario_indicadores_traslado_sii')->orderBy('codigo')->get();
        $result['referencias_sii']=DB::table('inventario_referencias_sii')->orderBy('codigo')->get();
        return response()->json($result);
    }
    public function actualizarMotivo(Request $r,int $id) {
        // La regla de negocio, las exigencias y los códigos oficiales no son datos editables por el CRUD.
        $v=$r->validate(['nombre'=>'required|string|max:160','activo'=>'required|boolean']);$user=$this->usuario($r);
        return response()->json(DB::transaction(function()use($v,$id,$user){
            $old=DB::table('inventario_motivos')->where('id',$id)->lockForUpdate()->first();abort_unless($old,404);
            $name=trim($v['nombre']);abort_if($name==='',422,'Ingrese nombre.');
            DB::table('inventario_motivos')->where('id',$id)->update(['nombre'=>$name,'activo'=>(int)$v['activo']]);
            DB::table('registros_auditoria')->insert(['usuario_id'=>$user,'accion'=>'INVENTARIO_EDITAR_MOTIVO','tipo_entidad'=>'inventario_motivos','entidad_id'=>$id,'valores_anteriores'=>json_encode(['nombre'=>$old->nombre,'activo'=>$old->activo],JSON_THROW_ON_ERROR),'valores_nuevos'=>json_encode(['nombre'=>$name,'activo'=>(int)$v['activo']],JSON_THROW_ON_ERROR)]);
            return ['id'=>$id,'nombre'=>$name,'activo'=>(int)$v['activo']];
        },3));
    }
    private function original(int $id,bool $lock=false) {
        $q=DB::table('kardex')->where('id',$id);if($lock)$q->lockForUpdate();$k=$q->first();abort_unless($k,404,'Movimiento no encontrado.');
        $n=$this->decimal($k->cantidad_documento);
        abort_unless((int)$k->tipo_movimiento===2&&in_array($k->origen_tipo,['VENTA','NOTA_VENTA','DESPACHO'],true)&&$n->isPositive()
          &&$this->decimal($k->cantidad_anterior)->minus($k->cantidad_actual)->isEqualTo($n)
          &&$this->decimal($k->fisico_anterior)->minus($k->fisico_actual)->isEqualTo($n)
          &&$this->decimal($k->transito_anterior)->isEqualTo($k->transito_actual)
          &&$this->decimal($k->comprometido_anterior)->isEqualTo($k->comprometido_actual)
          &&!$k->movimiento_reversado_id,409,'Solo salida comercial completa desde disponible, sin consumo de reserva. Otros casos requieren regularización especializada.');
        abort_if(DB::table('kardex')->where('movimiento_reversado_id',$id)->exists(),409,'El movimiento ya fue reversado.');
        abort_unless(($k->tipo_documento_id!==null&&trim((string)$k->folio_documento)!=='')||($k->tipo_documento_id===null),409,'Referencia documental incompleta.');
        return $k;
    }
    public function candidatos(Request $r) {
        $v=$r->validate(['buscar'=>'nullable|string|max:120','page'=>'nullable|integer|min:1']);
        $q=DB::table('kardex as k')->join('productos as p','p.id','=','k.producto_id')->join('bodegas as b','b.id','=','k.bodega_id')
          ->where('k.tipo_movimiento',2)->whereIn('k.origen_tipo',['VENTA','NOTA_VENTA','DESPACHO'])
          ->whereNull('k.movimiento_reversado_id')->where('k.cantidad_documento','>',0)
          ->whereRaw('k.cantidad_anterior-k.cantidad_actual=k.cantidad_documento AND k.fisico_anterior-k.fisico_actual=k.cantidad_documento AND k.transito_anterior=k.transito_actual AND k.comprometido_anterior=k.comprometido_actual')
          ->whereNotExists(fn($x)=>$x->selectRaw('1')->from('kardex as r')->whereColumn('r.movimiento_reversado_id','k.id'))
          ->whereNotExists(fn($x)=>$x->selectRaw('1')->from('inventario_operaciones as o')->join('estados as e','e.id','=','o.estado_id')->whereColumn('o.referencia_kardex_id','k.id')->where('e.codigo','<>','INV_ANULADA'))
          ->select('k.id','k.producto_id','p.nombre as producto','p.sku','k.bodega_id','b.nombre as bodega','k.cantidad_documento as cantidad','k.folio_documento as folio','k.fecha_movimiento');
        if(!empty($v['buscar'])){$s=trim($v['buscar']);$q->where(fn($x)=>$x->where('p.nombre','like','%'.$s.'%')->orWhere('p.sku','like','%'.$s.'%')->orWhere('k.folio_documento','like','%'.$s.'%'));}
        return response()->json($q->orderByDesc('k.id')->paginate(25));
    }
    public function bodegas(Request $r) {
        return response()->json(DB::table('bodegas')->where('activo',1)->orderBy('nombre')->get(['id','nombre']));
    }
    public function origen(Request $r,int $id) {
        $k=$this->original($id);
        return response()->json(['id'=>$k->id,'producto_id'=>$k->producto_id,'bodega_id'=>$k->bodega_id,'cantidad'=>$k->cantidad_documento,'tipo_documento_id'=>$k->tipo_documento_id,'folio'=>$k->folio_documento,'producto'=>DB::table('productos')->where('id',$k->producto_id)->value('nombre'),'bodega'=>DB::table('bodegas')->where('id',$k->bodega_id)->value('nombre')]);
    }
    private function motivo(int $codigo) {
        $m=DB::table('inventario_motivos as m')->join('inventario_grupos_motivos as g','g.id','=','m.grupo_id')->join('inventario_tipos_movimiento as t','t.id','=','m.tipo_movimiento_id')->where('m.codigo',$codigo)->where('m.activo',1)->where('g.activo',1)->where('t.activo',1)->where('m.ajuste_rapido',1)->select('m.*')->first();
        abort_unless($m&&isset(self::REGLAS[$codigo])&&[$m->regla,$m->politica_cpp]===self::REGLAS[$codigo]&&!$m->requiere_respaldo&&$m->requiere_referencia&&$m->requiere_aprobacion,409,'Motivo no habilitado en el motor rápido.');
        return $m;
    }
    private function plan($k,int $code,?int $product,?int $warehouse): array {
        $n=$this->decimal($k->cantidad_documento);
        $p=(int)$k->producto_id;$b=(int)$k->bodega_id;
        $lines=[['producto_id'=>$p,'bodega_id'=>$b,'cantidad'=>(string)$n,'delta_disponible'=>(string)$n,'delta_fisico'=>(string)$n,'kardex_referencia_id'=>$k->id]];
        if($code===201){abort_unless($product&&$product!==$p&&(!$warehouse||$warehouse===$b),422,'Seleccione el SKU efectivamente entregado en la misma bodega.');$lines[]=['producto_id'=>$product,'bodega_id'=>$b,'cantidad'=>(string)$n,'delta_disponible'=>(string)$n->negated(),'delta_fisico'=>(string)$n->negated(),'kardex_referencia_id'=>$k->id];}
        elseif($code===203){abort_unless($warehouse&&$warehouse!==$b&&(!$product||$product===$p),422,'Seleccione la bodega real de salida del mismo producto.');$lines[]=['producto_id'=>$p,'bodega_id'=>$warehouse,'cantidad'=>(string)$n,'delta_disponible'=>(string)$n->negated(),'delta_fisico'=>(string)$n->negated(),'kardex_referencia_id'=>$k->id];}
        elseif($code===207){abort_if($product||$warehouse,422,'El despacho no realizado solo se reversa íntegramente.');}
        else abort(422,'Motivo no habilitado.');
        foreach($lines as $l){
            abort_unless(DB::table('productos')->where('id',$l['producto_id'])->where('activo',1)->exists()&&DB::table('bodegas')->where('id',$l['bodega_id'])->where('activo',1)->exists(),422,'Producto o bodega inactivos.');
        }return $lines;
    }
    private function validarPlan(Request $r): array {
        return $r->validate(['motivo_codigo'=>'required|integer|in:201,203,207','kardex_id'=>'required|integer|min:1','producto_real_id'=>'nullable|integer|min:1','bodega_real_id'=>'nullable|integer|min:1','justificacion'=>'required|string|min:10|max:4000','token_creacion'=>'required|uuid']);
    }
    public function crear(Request $r) {
        $v=$this->validarPlan($r);$user=$this->usuario($r);abort_if(mb_strlen(trim($v['justificacion']))<10,422,'Justificación insuficiente.');
        return response()->json(DB::transaction(function()use($v,$user){
            $old=DB::table('inventario_operaciones')->where('token_creacion',$v['token_creacion'])->lockForUpdate()->first();
            if($old){$target=DB::table('inventario_operaciones_detalle')->where('operacion_id',$old->id)->where('numero_linea',2)->first();$code=(int)$v['motivo_codigo'];abort_unless((int)$old->creado_por===$user&&(int)$old->motivo_codigo_snapshot===$code&&(int)$old->referencia_kardex_id===(int)$v['kardex_id']&&$old->justificacion===trim($v['justificacion'])&&($code!==201||(int)($target->producto_id??0)===(int)($v['producto_real_id']??0))&&($code!==203||(int)($target->bodega_id??0)===(int)($v['bodega_real_id']??0)),409,'Token ya utilizado para otra solicitud.');return ['id'=>$old->id,'numero_interno'=>$old->numero_interno];}
            $k=$this->original((int)$v['kardex_id'],true);$m=$this->motivo((int)$v['motivo_codigo']);
            $lines=$this->plan($k,(int)$m->codigo,isset($v['producto_real_id'])?(int)$v['producto_real_id']:null,isset($v['bodega_real_id'])?(int)$v['bodega_real_id']:null);
            abort_if(DB::table('inventario_operaciones')->where('referencia_kardex_id',$k->id)->whereNotIn('estado_id',[$this->estado('INV_ANULADA')])->exists(),409,'Ya existe una regularización de este movimiento.');
            $state=$this->estado('INV_PEND_APROBACION');
            $id=DB::table('inventario_operaciones')->insertGetId(['clase'=>'AJUSTE_RAPIDO','motivo_id'=>$m->id,'estado_id'=>$state,'motivo_codigo_snapshot'=>$m->codigo,'motivo_nombre_snapshot'=>$m->nombre,'regla_snapshot'=>$m->regla,'politica_cpp_snapshot'=>$m->politica_cpp,'justificacion'=>trim($v['justificacion']),'referencia_tipo_documento_id'=>$k->tipo_documento_id,'referencia_folio'=>$k->tipo_documento_id?$k->folio_documento:null,'referencia_kardex_id'=>$k->id,'revision_tributaria'=>'PENDIENTE','token_creacion'=>$v['token_creacion'],'creado_por'=>$user]);
            $number='AJR-'.str_pad((string)$id,8,'0',STR_PAD_LEFT);
            DB::table('inventario_operaciones')->where('id',$id)->update(['numero_interno'=>$number]);
            foreach($lines as $i=>$line)DB::table('inventario_operaciones_detalle')->insert($line+['operacion_id'=>$id,'numero_linea'=>$i+1]);
            $this->historial((int)$id,null,$state,$user,'CREAR');
            return ['id'=>$id,'numero_interno'=>$number];
        },3),201);
    }
    private function base() {
        return DB::table('inventario_operaciones as o')->join('estados as e','e.id','=','o.estado_id')->select('o.*','e.codigo as estado_codigo','e.nombre as estado_nombre')->where('o.clase','AJUSTE_RAPIDO');
    }
    public function listar(Request $r) {
        $v=$r->validate(['buscar'=>'nullable|string|max:120','page'=>'nullable|integer|min:1']);$q=$this->base();
        $q->where('e.codigo','<>','INV_ANULADA');
        if(!empty($v['buscar'])){$s=trim($v['buscar']);$q->where(fn($a)=>$a->where('o.numero_interno','like','%'.$s.'%')->orWhere('o.referencia_folio','like','%'.$s.'%')->orWhere('o.motivo_nombre_snapshot','like','%'.$s.'%'));}
        return response()->json($q->orderByDesc('o.id')->paginate(25));
    }
    public function ver(int $id) {
        $o=$this->base()->where('o.id',$id)->first();abort_unless($o,404);
        $lines=DB::table('inventario_operaciones_detalle as d')->join('productos as p','p.id','=','d.producto_id')->join('bodegas as b','b.id','=','d.bodega_id')->leftJoin('stock as s',fn($j)=>$j->on('s.producto_id','=','d.producto_id')->on('s.bodega_id','=','d.bodega_id'))->where('d.operacion_id',$id)->orderBy('d.numero_linea')->select('d.*','p.nombre as producto','p.sku','b.nombre as bodega','s.disponible','s.cantidad_fisica')->get();
        return response()->json(['operacion'=>$o,'detalles'=>$lines]);
    }
    public function aprobar(Request $r,int $id) {return $this->transicion($r,$id,'INV_PEND_APROBACION','INV_APROBADA','APROBAR');}
    public function anular(Request $r,int $id) {return $this->transicion($r,$id,'INV_PEND_APROBACION','INV_ANULADA','ANULAR');}
    private function transicion(Request $r,int $id,string $from,string $to,string $action) {
        $v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','motivo'=>'required|string|min:3|max:1000']);$user=$this->usuario($r);abort_if(mb_strlen(trim($v['motivo']))<3,422,'Motivo insuficiente.');
        return response()->json(DB::transaction(function()use($v,$user,$id,$from,$to,$action){
            $o=DB::table('inventario_operaciones')->where('id',$id)->where('clase','AJUSTE_RAPIDO')->lockForUpdate()->first();abort_unless($o,404);
            $a=(int)$o->estado_id;$b=$this->estado($to);$permitted=$action==='ANULAR'?[$this->estado('INV_PEND_APROBACION'),$this->estado('INV_APROBADA')]:[$this->estado($from)];abort_unless(in_array($a,$permitted,true)&&(int)$o->version===(int)$v['version'],409,'Estado o versión cambió.');
            $data=['estado_id'=>$b,'version'=>$o->version+1];
            if($action==='APROBAR'){$this->motivo((int)$o->motivo_codigo_snapshot);$this->original((int)$o->referencia_kardex_id);$data+=['aprobado_por'=>$user,'aprobado_en'=>now()];}
            else $data+=['anulado_por'=>$user,'anulado_en'=>now(),'motivo_anulacion'=>trim($v['motivo'])];
            DB::table('inventario_operaciones')->where('id',$id)->update($data);$this->historial($id,$a,$b,$user,$action,trim($v['motivo']));return ['id'=>$id,'estado'=>$to];
        },3));
    }
    public function confirmar(Request $r,int $id) {
        $v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','token_confirmacion'=>'required|uuid']);$user=$this->usuario($r);
        return response()->json(DB::transaction(function()use($v,$user,$id){
            $o=DB::table('inventario_operaciones')->where('id',$id)->where('clase','AJUSTE_RAPIDO')->lockForUpdate()->first();abort_unless($o,404);
            $approved=$this->estado('INV_APROBADA');$done=$this->estado('INV_CONFIRMADA');
            if((int)$o->estado_id===$done&&$o->token_confirmacion===$v['token_confirmacion'])return ['id'=>$id,'estado'=>'INV_CONFIRMADA'];
            abort_unless((int)$o->estado_id===$approved&&(int)$o->version===(int)$v['version']&&$o->aprobado_por,409,'Se requiere aprobación y versión vigente.');
            $code=(int)$o->motivo_codigo_snapshot;$m=$this->motivo($code);abort_unless($o->regla_snapshot===$m->regla&&$o->politica_cpp_snapshot===$m->politica_cpp,409,'Regla de motivo modificada.');
            $k=$this->original((int)$o->referencia_kardex_id,true);
            $lines=DB::table('inventario_operaciones_detalle')->where('operacion_id',$id)->orderBy('numero_linea')->lockForUpdate()->get();
            $next=$lines->get(1);$expected=$this->plan($k,$code,$code===201?(int)($next->producto_id??0):null,$code===203?(int)($next->bodega_id??0):null);
            abort_unless(count($expected)===$lines->count(),409,'Detalle de operación inválido.');
            $pids=$lines->pluck('producto_id')->unique()->sort()->values();$products=DB::table('productos')->whereIn('id',$pids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $bids=$lines->pluck('bodega_id')->unique()->sort()->values();$warehouses=DB::table('bodegas')->whereIn('id',$bids)->where('activo',1)->orderBy('id')->lockForUpdate()->get();
            abort_unless($warehouses->count()===$bids->count(),409,'Bodega inactiva.');
            $balances=[];$valued=[];
            foreach($pids as $pid){
                $p=$products->get($pid);abort_unless($p&&$p->activo,409,'Producto inactivo.');
                abort_if(DB::table('saldos_inventario as s')->join('presentaciones_productos as pp','pp.id','=','s.presentacion_id')->where('pp.producto_id',$pid)->where('s.cantidad','<>',0)->exists(),409,'Existe inventario legado sin conciliar.');
                abort_if(DB::table('inventario_traspasos_detalle')->where('producto_id',$pid)->whereRaw('cantidad_despachada>cantidad_recibida+cantidad_regularizada')->exists(),409,'Hay traspasos en camino. Requiere motor de valorización de traslados.');
                $valued[$pid]=BigDecimal::zero();
                foreach(DB::table('stock')->where('producto_id',$pid)->orderBy('bodega_id')->lockForUpdate()->get() as $s){
                    $a=$this->decimal($s->disponible);$t=$this->decimal($s->transito);$c=$this->decimal($s->comprometido);$f=$this->decimal($s->cantidad_fisica);
                    abort_unless(!$a->isNegative()&&!$t->isNegative()&&!$c->isNegative()&&$f->isEqualTo($a->plus($t)->plus($c)),409,'Stock previo inconsistente.');
                    $balances[$pid.':'.$s->bodega_id]=$s;$valued[$pid]=$valued[$pid]->plus($a)->plus($c);
                }
            }
            $historical=$this->decimal($k->costo_unitario_neto??0);abort_unless($historical->isPositive()&&$historical->isEqualTo($this->decimal($k->cpp_anterior??0)),409,'No existe costo histórico de salida comprobable.');
            foreach($lines as $i=>$l){
                $x=$expected[$i];$delta=$this->decimal($l->delta_disponible);$n=$this->decimal($l->cantidad);
                abort_unless((int)$l->producto_id===$x['producto_id']&&(int)$l->bodega_id===$x['bodega_id']&&(int)$l->kardex_referencia_id===(int)$k->id&&$delta->isEqualTo($x['delta_disponible'])&&$n->isEqualTo($x['cantidad'])&&$this->decimal($l->delta_fisico)->isEqualTo($delta)&&$this->decimal($l->delta_transito)->isZero()&&$this->decimal($l->delta_comprometido)->isZero()&&!$l->kardex_id,409,'Detalle alterado.');
                $p=$products->get($l->producto_id);$cpp=$this->decimal($p->costo_promedio_ponderado);$newCpp=$cpp;$cost=$delta->isPositive()?$historical:$cpp;
                if($code===203){abort_unless($cpp->isPositive(),409,'CPP vigente no valorizado.');$cost=$cpp;}
                elseif($delta->isPositive()){
                    if($valued[$l->producto_id]->isPositive())abort_unless($cpp->isPositive(),409,'CPP cero con inventario valorizable: regularice antes.');
                    $oldQ=$valued[$l->producto_id];$rm=defined(RoundingMode::class.'::HalfUp')?constant(RoundingMode::class.'::HalfUp'):constant(RoundingMode::class.'::HALF_UP');
                    $newCpp=$oldQ->multipliedBy($cpp)->plus($n->multipliedBy($historical))->dividedBy($oldQ->plus($n),8,$rm);
                }else abort_unless($cpp->isPositive(),409,'Salida sin CPP valorizado.');
                $key=$l->producto_id.':'.$l->bodega_id;$s=$balances[$key]??null;
                if(!$s){abort_if($delta->isNegative(),409,'Sin saldo en bodega real.');$sid=DB::table('stock')->insertGetId(['producto_id'=>$l->producto_id,'bodega_id'=>$l->bodega_id,'disponible'=>0,'transito'=>0,'comprometido'=>0,'cantidad_fisica'=>0]);$s=DB::table('stock')->where('id',$sid)->lockForUpdate()->first();}
                $a=$this->decimal($s->disponible);$f=$this->decimal($s->cantidad_fisica);$na=$a->plus($delta);$nf=$f->plus($delta);
                abort_if($na->isNegative()||$nf->isNegative(),409,'Stock disponible insuficiente. No se toca tránsito ni comprometido.');
                $movement=DB::table('kardex')->insertGetId(['producto_id'=>$l->producto_id,'bodega_id'=>$l->bodega_id,'tipo_documento_id'=>$k->tipo_documento_id,'folio_documento'=>$k->tipo_documento_id?$k->folio_documento:$o->numero_interno,'tipo_movimiento'=>$delta->isPositive()?1:2,'cantidad_anterior'=>$this->saldo($a),'cantidad_documento'=>$this->saldo($n),'cantidad_actual'=>$this->saldo($na),'transito_anterior'=>$s->transito,'transito_actual'=>$s->transito,'comprometido_anterior'=>$s->comprometido,'comprometido_actual'=>$s->comprometido,'fisico_anterior'=>$this->saldo($f),'fisico_actual'=>$this->saldo($nf),'costo_unitario_neto'=>$this->saldo($cost),'ultimo_costo_anterior'=>$p->costo_neto,'ultimo_costo_actual'=>$p->costo_neto,'cpp_anterior'=>$this->saldo($cpp),'cpp_actual'=>$this->saldo($newCpp),'origen_tipo'=>'AJUSTE_INVENTARIO','origen_detalle_id'=>$l->id,'operacion_codigo'=>$delta->isPositive()?'REVERSA_DESPACHO':'CORREGIR_DESPACHO','token_idempotencia'=>(string)Str::uuid(),'movimiento_reversado_id'=>$delta->isPositive()?$k->id:null,'registrado_por'=>$user,'observaciones'=>$o->numero_interno.' | '.$o->motivo_nombre_snapshot.' | '.$o->justificacion]);
                DB::table('stock')->where('id',$s->id)->update(['disponible'=>$this->saldo($na),'cantidad_fisica'=>$this->saldo($nf)]);
                if(!$newCpp->isEqualTo($cpp))DB::table('productos')->where('id',$p->id)->update(['costo_promedio_ponderado'=>$this->saldo($newCpp)]);
                DB::table('inventario_operaciones_detalle')->where('id',$l->id)->update(['kardex_id'=>$movement,'costo_unitario_snapshot'=>$this->saldo($cost)]);
            }
            DB::table('inventario_operaciones')->where('id',$id)->update(['estado_id'=>$done,'version'=>$o->version+1,'token_confirmacion'=>$v['token_confirmacion'],'confirmado_por'=>$user,'confirmado_en'=>now()]);
            $this->historial($id,$approved,$done,$user,'CONFIRMAR','Regularización física completa; DTE original intacto.');
            return ['id'=>$id,'estado'=>'INV_CONFIRMADA'];
        },3));
    }
}
