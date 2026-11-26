<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Consultas operativas sin escrituras ni duplicación de importes. */
final class ComprasConsultasControlador
{
    private function sucursal(Request $request): int
    {
        return (int)app(\App\Services\ContextoCompras::class)->resolver($request)->id;
    }
    private function bodegas(int $sid)
    {
        return DB::table('bodegas as b')->join('inventario_bodegas_sucursales as bs','bs.bodega_id','=','b.id')
            ->where('bs.sucursal_id',$sid)->where('b.activo',1)->orderBy('b.nombre')->get(['b.id','b.nombre']);
    }
    private function limitarBodega($query,string $column,int $sid): void
    {
        $query->whereExists(fn($x)=>$x->selectRaw('1')->from('inventario_bodegas_sucursales as bs')
            ->whereColumn('bs.bodega_id',$column)->where('bs.sucursal_id',$sid));
    }
    public function pdf(Request $request,string $tipo,int $id)
    {
        abort_unless(in_array($tipo,['recepcion','consolidacion'],true),404);
        $pdf=DB::transaction(function()use($request,$tipo,$id){
            $sid=(int)app(\App\Services\ContextoCompras::class)->resolver($request,true)->id;
            $table=$tipo==='recepcion'?'recepciones':'recepciones_consolidadas';
            $doc=DB::table($table)->where('id',$id)->where('sucursal_id',$sid)->lockForUpdate()->first();
            abort_unless($doc,404,'Documento no encontrado.');
            abort_unless(DB::table('inventario_bodegas_sucursales')->where('bodega_id',$doc->bodega_id)->where('sucursal_id',$sid)->lockForUpdate()->first(),404,'Documento no encontrado.');
            if($doc->orden_compra_id!==null){
                abort_unless(DB::table('ordenes_compra')->where('id',$doc->orden_compra_id)->where('sucursal_id',$sid)->lockForUpdate()->first(),409,'Documento con referencias sin atribución válida.');
            }
            $receipts=$tipo==='recepcion'?collect([$doc]):DB::table('recepciones')->where('recepcion_consolidada_id',$id)->orderBy('id')->lockForUpdate()->get();
            foreach($receipts as $rec){
                abort_unless((int)$rec->sucursal_id===$sid&&(int)$rec->bodega_id===(int)$doc->bodega_id,409,'Recepción vinculada sin atribución válida.');
                abort_unless(DB::table('compras')->where('id',$rec->compra_id)->where('sucursal_id',$sid)->lockForUpdate()->first(),409,'Compra vinculada sin atribución válida.');
            }
            if($tipo==='consolidacion'){
                $ids=$receipts->pluck('id')->all();
                $foreign=DB::table('solicitudes_regularizacion')->where('recepcion_consolidada_id',$id)
                    ->where(fn($q)=>$q->whereNull('recepcion_id')->orWhereNotIn('recepcion_id',$ids))->exists();
                abort_if($foreign,409,'Solicitud vinculada sin recepción válida.');
            }
            return (new \App\Services\PdfRecepcionCompra())->generar($tipo,$id);
        },3);
        return response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="'.$tipo.'-'.$id.'.pdf"','Cache-Control'=>'private, no-store']);
    }
    public function listar(Request $request, string $modulo)
    {
        $v=$request->validate(['page'=>'sometimes|integer|min:1','exportar'=>'nullable|in:1','buscar'=>'nullable|string|max:120','producto_id'=>'nullable|integer|min:1','bodega_id'=>'nullable|integer|min:1','estado'=>'nullable|in:ACTIVA,PENDIENTE,RECEPCIONADA,DESCUADRADA,FINALIZADA,CONSOLIDADA','proveedor'=>'nullable|string|max:120','orden_compra'=>'nullable|string|max:80','desde'=>'nullable|date_format:Y-m-d','hasta'=>'nullable|date_format:Y-m-d|after_or_equal:desde']);
        $sid=$this->sucursal($request);
        if(!empty($v['bodega_id'])&&in_array($modulo,['stock','kardex','recepciones','consolidaciones'],true)){
            abort_unless(DB::table('inventario_bodegas_sucursales')->where('bodega_id',$v['bodega_id'])->where('sucursal_id',$sid)->exists(),404,'Bodega no disponible.');
        }
        if($modulo==='kardex'){
            if(!$request->filled('producto_id')){
                $q=DB::table('productos')->select('id','sku','nombre as producto');
                if(!empty($v['buscar']))$q->where(fn($w)=>$w->where('sku','like','%'.$v['buscar'].'%')->orWhere('nombre','like','%'.$v['buscar'].'%'));
                $data=$q->orderBy('nombre')->orderBy('id')->paginate(25)->toArray();
                $data['bodegas']=$this->bodegas($sid);
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
                ->join('bodegas as b','b.id','=','k.bodega_id')->leftJoin('tipo_documentos as td','td.id','=','k.tipo_documento_id')
                ->select('k.id','p.sku','p.nombre as producto','b.nombre as bodega','k.fecha_movimiento','k.operacion_codigo','k.tipo_documento_id','td.nombre as tipo_documento','k.folio_documento','k.cantidad_documento','k.fisico_anterior','k.fisico_actual','k.costo_unitario_neto'),
            'libro'=>DB::table('libro_compras as l')->join('compras as c','c.id','=','l.compra_id')
                ->join('estados as e','e.id','=','c.estado_id')->leftJoin('ordenes_compra as oc','oc.id','=','c.orden_compra_id')->join('tipo_documentos as td','td.id','=','l.documento_id')->where('e.codigo','<>','NULA')
                ->select('l.id','l.periodo_tributario','l.folio','l.fecha_documento','oc.numero as orden_compra','td.nombre as documento','l.rut_emisor','l.razon_social_emisor','l.monto_neto','l.monto_exento','l.monto_iva_recuperable','l.monto_iva_no_recuperable','l.monto_iva_uso_comun','l.monto_impuestos_adicionales','l.monto_retenciones','l.monto_total','l.signo_contable','l.estado_rcv'),
            default=>abort(404)
        };
        if(in_array($modulo,['facturas','libro'],true))$q->where('c.sucursal_id',$sid);
        if(in_array($modulo,['recepciones','consolidaciones'],true)){
            $q->where('r.sucursal_id',$sid);
            $this->limitarBodega($q,'r.bodega_id',$sid);
        }
        if($modulo==='stock')$this->limitarBodega($q,'s.bodega_id',$sid);
        if($modulo==='kardex'){
            $q->where('k.sucursal_id',$sid);
            $this->limitarBodega($q,'k.bodega_id',$sid);
        }
        // No presentar número de OC no atribuida como si fuera de la sucursal.
        if(in_array($modulo,['facturas','libro','recepciones','consolidaciones'],true))
            $q->where(fn($w)=>$w->whereNull('oc.id')->orWhere('oc.sucursal_id',$sid));
        if($modulo==='recepciones')$q->selectSub(DB::table('solicitudes_regularizacion')->whereColumn('recepcion_id','r.id')->where('estado','<>','NULA')->selectRaw('COUNT(*)'),'solicitudes_count');
        if($modulo==='consolidaciones')$q->selectSub(DB::table('solicitudes_regularizacion')->whereColumn('recepcion_consolidada_id','r.id')->where('estado','<>','NULA')->selectRaw('COUNT(*)'),'solicitudes_count');
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
        if($request->filled('exportar')){
            abort_unless($modulo==='libro',422,'La exportación solo corresponde al Libro de compras.');
            $rows=$q->orderByDesc($alias.'.id')->limit(5001)->get();
            abort_if($rows->count()>5000,422,'La exportación supera 5.000 registros. Acote las fechas o el proveedor.');
            return response()->json(['data'=>$rows,'total'=>$rows->count()])->header('Cache-Control','private, no-store');
        }
        $data=$q->orderByDesc($alias.'.id')->paginate(25)->toArray();
        if(in_array($modulo,['recepciones','consolidaciones','stock'],true))$data['bodegas']=$this->bodegas($sid);
        return response()->json($data);
    }
}
