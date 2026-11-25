<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Consultas operativas sin escrituras ni duplicación de importes. */
final class ComprasConsultasControlador
{
    public function listar(Request $request, string $modulo)
    {
        $v=$request->validate(['page'=>'sometimes|integer|min:1','buscar'=>'nullable|string|max:120','producto_id'=>'nullable|integer|min:1','bodega_id'=>'nullable|integer|min:1','estado'=>'nullable|in:ACTIVA,PENDIENTE,RECEPCIONADA,DESCUADRADA,FINALIZADA,CONSOLIDADA','proveedor'=>'nullable|string|max:120','orden_compra'=>'nullable|string|max:80','desde'=>'nullable|date_format:Y-m-d','hasta'=>'nullable|date_format:Y-m-d|after_or_equal:desde']);
        if($modulo==='kardex'){
            if(!$request->filled('producto_id')){
                $q=DB::table('productos')->select('id','sku','nombre as producto');
                if(!empty($v['buscar']))$q->where(fn($w)=>$w->where('sku','like','%'.$v['buscar'].'%')->orWhere('nombre','like','%'.$v['buscar'].'%'));
                $data=$q->orderBy('nombre')->orderBy('id')->paginate(25)->toArray();
                $data['bodegas']=DB::table('bodegas')->orderBy('nombre')->get(['id','nombre']);
                return response()->json($data);
            }
            abort_unless(!empty($v['bodega_id']),422,'Seleccione una bodega.');
        }
        $q=match($modulo){
            'facturas'=>DB::table('compras as c')->join('estados as e','e.id','=','c.estado_id')
                ->join('proveedores as p','p.id','=','c.proveedor_id')
                ->join('tipo_documentos as d','d.id','=','c.documento_id')->leftJoin('ordenes_compra as oc','oc.id','=','c.orden_compra_id')
                ->where('e.codigo','<>','NULA')
                ->select('c.id','c.folio','p.nombre as proveedor','d.nombre as documento','c.fecha_documento','oc.numero as orden_compra','c.total','c.signo_contable','e.codigo as estado'),
            'recepciones'=>DB::table('recepciones as r')->join('estados as e','e.id','=','r.estado_id')
                ->join('proveedores as p','p.id','=','r.proveedor_id')->join('bodegas as b','b.id','=','r.bodega_id')->leftJoin('ordenes_compra as oc','oc.id','=','r.orden_compra_id')->where('e.codigo','<>','NULA')->select('r.id','r.numero_interno','p.nombre as proveedor','b.nombre as bodega','oc.numero as orden_compra','r.total','e.codigo as estado'),
            'consolidaciones'=>DB::table('recepciones_consolidadas as r')->join('estados as e','e.id','=','r.estado_id')
                ->join('proveedores as p','p.id','=','r.proveedor_id')->join('bodegas as b','b.id','=','r.bodega_id')->leftJoin('ordenes_compra as oc','oc.id','=','r.orden_compra_id')->where('e.codigo','<>','NULA')->select('r.id','r.numero_interno','p.nombre as proveedor','b.nombre as bodega','oc.numero as orden_compra','r.total','e.codigo as estado'),
            'stock'=>DB::table('stock as s')->join('productos as p','p.id','=','s.producto_id')
                ->join('bodegas as b','b.id','=','s.bodega_id')
                ->select('s.id','p.sku','p.nombre as producto','b.nombre as bodega','s.disponible','s.transito','s.comprometido','s.cantidad_fisica'),
            'kardex'=>DB::table('kardex as k')->join('productos as p','p.id','=','k.producto_id')
                ->join('bodegas as b','b.id','=','k.bodega_id')
                ->select('k.id','p.sku','p.nombre as producto','b.nombre as bodega','k.fecha_movimiento','k.operacion_codigo','k.cantidad_documento','k.fisico_anterior','k.fisico_actual','k.costo_unitario_neto'),
            'libro'=>DB::table('libro_compras as l')->join('compras as c','c.id','=','l.compra_id')
                ->join('estados as e','e.id','=','c.estado_id')->leftJoin('ordenes_compra as oc','oc.id','=','c.orden_compra_id')->where('e.codigo','<>','NULA')
                ->select('l.id','l.periodo_tributario','l.folio','l.fecha_documento','oc.numero as orden_compra','l.rut_emisor','l.razon_social_emisor','l.monto_neto','l.monto_exento','l.monto_iva_recuperable','l.monto_total','l.signo_contable','l.estado_rcv'),
            default=>abort(404)
        };
        if($modulo==='kardex')$q->where('k.producto_id',$v['producto_id'])->where('k.bodega_id',$v['bodega_id']);
        if(in_array($modulo,['facturas','recepciones','consolidaciones'],true)&&!empty($v['estado']))$q->where('e.codigo',$v['estado']);
        $fecha=match($modulo){'facturas'=>'c.fecha_documento','recepciones'=>'r.fecha_recepcion','consolidaciones'=>'r.fecha_consolidacion','libro'=>'l.fecha_documento',default=>null};
        if($fecha!==null){
            if(!empty($v['desde']))$q->whereDate($fecha,'>=',$v['desde']);
            if(!empty($v['hasta']))$q->whereDate($fecha,'<=',$v['hasta']);
        }
        if(in_array($modulo,['facturas','recepciones','consolidaciones','libro'],true)){
            $prov=trim($v['proveedor']??'');
            if($prov!=='')$q->where(function($w)use($modulo,$prov){
                if($modulo==='libro')$w->where('l.razon_social_emisor','like','%'.$prov.'%')->orWhere('l.rut_emisor','like','%'.$prov.'%');
                else $w->where('p.nombre','like','%'.$prov.'%')->orWhere('p.rut','like','%'.$prov.'%');
            });
            $oc=trim($v['orden_compra']??'');
            if($oc!=='')$q->where('oc.numero','like','%'.$oc.'%');
        }
        if(!empty($v['bodega_id'])&&in_array($modulo,['recepciones','consolidaciones','stock'],true))$q->where(($modulo==='stock'?'s':'r').'.bodega_id',$v['bodega_id']);
        if($modulo==='kardex'){
            if(!empty($v['desde']))$q->whereDate('k.fecha_movimiento','>=',$v['desde']);
            if(!empty($v['hasta']))$q->whereDate('k.fecha_movimiento','<=',$v['hasta']);
        }
        $alias=match($modulo){'facturas'=>'c','recepciones','consolidaciones'=>'r','stock'=>'s','kardex'=>'k','libro'=>'l'};
        $buscar=trim($v['buscar']??'');
        if($buscar!==''){
            $q->where(function($w)use($modulo,$alias,$buscar){
                if(ctype_digit($buscar))$w->where($alias.'.id',$buscar);
                else $w->whereRaw('1=0');
                if($modulo==='facturas')$w->orWhere('c.folio','like','%'.$buscar.'%')->orWhere('p.nombre','like','%'.$buscar.'%');
                if(in_array($modulo,['stock','kardex'],true))$w->orWhere('p.nombre','like','%'.$buscar.'%')->orWhere('p.sku','like','%'.$buscar.'%');
                if($modulo==='libro')$w->orWhere('l.folio','like','%'.$buscar.'%')->orWhere('l.razon_social_emisor','like','%'.$buscar.'%');
                if(in_array($modulo,['recepciones','consolidaciones'],true))$w->orWhere('e.codigo','like','%'.$buscar.'%')->orWhere('r.numero_interno','like','%'.$buscar.'%')->orWhere('p.nombre','like','%'.$buscar.'%')->orWhere('oc.numero','like','%'.$buscar.'%');
            });
        }
        $data=$q->orderByDesc($alias.'.id')->paginate(25)->toArray();
        if(in_array($modulo,['recepciones','consolidaciones','stock'],true))$data['bodegas']=DB::table('bodegas')->where('activo',1)->orderBy('nombre')->get(['id','nombre']);
        return response()->json($data);
    }
}
