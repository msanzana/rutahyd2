<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\ContextoCompras;
use App\Services\PlanCantidadesTraspaso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** V109C6A: consultas de traspasos, sin escrituras ni exposición de tokens. */
final class TraspasosConsultasControlador extends Controller
{
    private function base(int $sid){
        return DB::table('inventario_traspasos as t')
          ->join('inventario_traspasos_operaciones as x','x.traspaso_id','=','t.id')
          ->join('inventario_operaciones as o','o.id','=','x.operacion_id')
          ->join('estados as e','e.id','=','o.estado_id')
          ->join('bodegas as bo','bo.id','=','t.bodega_origen_id')
          ->join('bodegas as bd','bd.id','=','t.bodega_destino_id')
          ->where('t.sucursal_origen_id',$sid)->where('t.sucursal_destino_id',$sid)->where('o.sucursal_id',$sid)
          ->where('o.clase','TRASPASO')->where('o.motivo_codigo_snapshot',709)
          ->where(fn($q)=>$q->where(fn($m)=>$m->where('t.modalidad','INMEDIATO')->where('x.etapa','INMEDIATO'))->orWhere(fn($m)=>$m->where('t.modalidad','DESPACHO_RECEPCION')->where('x.etapa','DESPACHO')))
          ->where('e.codigo','<>','INV_ANULADA')
          ->whereExists(fn($q)=>$q->selectRaw('1')->from('inventario_bodegas_sucursales as bs')->whereColumn('bs.bodega_id','t.bodega_origen_id')->where('bs.sucursal_id',$sid))
          ->whereExists(fn($q)=>$q->selectRaw('1')->from('inventario_bodegas_sucursales as bs')->whereColumn('bs.bodega_id','t.bodega_destino_id')->where('bs.sucursal_id',$sid))
          ->select('t.id','t.numero_interno','t.modalidad','t.estado as estado_traspaso','t.bodega_origen_id','t.bodega_destino_id','bo.nombre as bodega_origen','bd.nombre as bodega_destino','t.creado_en','t.creado_por','t.guia_despacho_id','o.id as operacion_id','o.version','o.justificacion','e.codigo as estado_operacion','e.nombre as estado_operacion_nombre');
    }
    public function listar(Request $r){
        $v=$r->validate(['buscar'=>'nullable|string|max:120','page'=>'nullable|integer|min:1','modalidad'=>'nullable|in:INMEDIATO,DESPACHO_RECEPCION','estado'=>'nullable|in:BORRADOR,DESPACHADO,PARCIAL,RECIBIDO','bodega_origen_id'=>'nullable|integer|min:1','bodega_destino_id'=>'nullable|integer|min:1']);
        $sid=(int)app(ContextoCompras::class)->resolver($r)->id;$q=$this->base($sid);
        $s=trim($v['buscar']??'');
        if($s!=='')$q->where(fn($x)=>$x->where('t.numero_interno','like','%'.$s.'%')->orWhere('bo.nombre','like','%'.$s.'%')->orWhere('bd.nombre','like','%'.$s.'%')->orWhereExists(fn($z)=>$z->selectRaw('1')->from('inventario_traspasos_detalle as d')->join('productos as p','p.id','=','d.producto_id')->whereColumn('d.traspaso_id','t.id')->where(fn($p)=>$p->where('p.sku','like','%'.$s.'%')->orWhere('p.nombre','like','%'.$s.'%'))));
        foreach(['modalidad','estado','bodega_origen_id','bodega_destino_id'] as $key)if(isset($v[$key]))$q->where('t.'.$key,$v[$key]);
        return response()->json($q->orderByDesc('t.id')->paginate(25,['*'],'page',(int)($v['page']??1)))->header('Cache-Control','no-store');
    }
    public function bodegas(Request $r){
        $sid=(int)app(ContextoCompras::class)->resolver($r)->id;
        $b=DB::table('bodegas as b')->join('inventario_bodegas_sucursales as bs','bs.bodega_id','=','b.id')->where('bs.sucursal_id',$sid)->where('b.activo',1)->select('b.id','b.nombre')->distinct()->orderBy('b.nombre')->get();
        return response()->json(['bodegas'=>$b])->header('Cache-Control','no-store');
    }
    public function ver(Request $r,int $id){
        $data=DB::transaction(function()use($r,$id){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;
            $rows=$this->base($sid)->where('t.id',$id)->get();abort_unless($rows->count()===1,404,'Traspaso no disponible.');
            $t=$rows->first();
            $details=DB::table('inventario_traspasos_detalle as d')->join('productos as p','p.id','=','d.producto_id')->where('d.traspaso_id',$id)->orderBy('d.id')->select('d.*','p.sku','p.nombre as producto')->get();
            $plan=new PlanCantidadesTraspaso();
            $lines=$details->map(fn($d)=>['id'=>$d->id,'producto_id'=>$d->producto_id,'sku'=>$d->sku,'producto'=>$d->producto,'costo_cpp_snapshot'=>$d->costo_cpp_snapshot]+$plan->resumen($d));
            $ops=DB::table('inventario_traspasos_operaciones as x')->join('inventario_operaciones as o','o.id','=','x.operacion_id')->join('estados as e','e.id','=','o.estado_id')->where('x.traspaso_id',$id)->where('o.sucursal_id',$sid)->where('o.clase','TRASPASO')->where('o.motivo_codigo_snapshot',709)->where('e.codigo','<>','INV_ANULADA')->select('o.id','o.numero_interno','x.etapa','e.codigo as estado','e.nombre as estado_nombre','o.version','o.justificacion','o.creado_por','o.aprobado_por','o.confirmado_por','o.creado_en','o.aprobado_en','o.confirmado_en')->orderBy('o.id')->get();
            foreach($ops as $o){
                $o->detalles=DB::table('inventario_operaciones_detalle as d')->join('productos as p','p.id','=','d.producto_id')->where('d.operacion_id',$o->id)->whereIn('d.bodega_id',[$t->bodega_origen_id,$t->bodega_destino_id])->select('d.id','d.producto_id','p.sku','p.nombre as producto','d.bodega_id','d.cantidad','d.delta_disponible','d.delta_transito','d.delta_comprometido','d.delta_fisico','d.costo_unitario_snapshot','d.kardex_id')->orderBy('d.numero_linea')->get();
            }
            $active=$ops->first(fn($o)=>$o->etapa==='RECEPCION'&&in_array($o->estado,['INV_PEND_APROBACION','INV_APROBADA'],true));
            return ['traspaso'=>$t,'detalles'=>$lines,'operaciones'=>$ops,'recepcion_activa'=>$active?->id,'usuario_actual_id'=>(int)$r->user()->id];
        },3);
        return response()->json($data)->header('Cache-Control','no-store');
    }
}
