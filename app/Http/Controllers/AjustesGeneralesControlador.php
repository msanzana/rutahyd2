<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\ContextoCompras;
use App\Services\PlanAjusteGeneral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** V109B1: vista previa de ajustes, sin reservar ni modificar inventario. */
final class AjustesGeneralesControlador extends Controller
{
    public function revisar(Request $r)
    {
        $v=$r->validate([
            'motivo_codigo'=>'required|integer|in:101,102,107',
            'producto_id'=>'required|integer|min:1',
            'bodega_id'=>'required|integer|min:1',
            'cantidad'=>['required','string','regex:/^(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,8})?$/D'],
        ]);
        return response()->json(DB::transaction(function()use($r,$v){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;
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
              ->select('id','sku','nombre','costo_promedio_ponderado')->lockForUpdate()->first();
            abort_unless($p,404,'Producto no disponible.');
            abort_if(DB::table('saldos_inventario as s')->join('presentaciones_productos as pp','pp.id','=','s.presentacion_id')->where('pp.producto_id',$p->id)->where('s.cantidad','<>',0)->exists(),409,'Inventario anterior pendiente de conciliación.');
            abort_if(DB::table('inventario_traspasos_detalle')->where('producto_id',$p->id)->whereRaw('cantidad_despachada>cantidad_recibida+cantidad_regularizada')->exists(),409,'Producto con traspasos en camino.');
            $s=DB::table('stock')->where('producto_id',$p->id)->where('bodega_id',$b->id)->lockForUpdate()->first()
              ??(object)['disponible'=>'0','transito'=>'0','comprometido'=>'0','cantidad_fisica'=>'0'];
            $plan=(new PlanAjusteGeneral())->calcular($m,$v['cantidad'],$s,(string)$p->costo_promedio_ponderado);
            return ['sucursal_id'=>$sid,'producto'=>['id'=>$p->id,'sku'=>$p->sku,'nombre'=>$p->nombre],'bodega'=>$b,'motivo'=>['codigo'=>$m->codigo,'nombre'=>$m->nombre],'plan'=>$plan,'solo_previsualizacion'=>true,'puede_confirmar'=>false,'mensaje'=>'Simulación. No guarda ni modifica stock. La confirmación requiere respaldo, aprobación y nueva validación.'];
        },3))->header('Cache-Control','no-store');
    }
}
