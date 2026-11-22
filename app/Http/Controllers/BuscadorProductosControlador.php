<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contrato de búsqueda transversal: guía, compra, venta, OT, NC, factura, etc.
 * Sólo devuelve identificación y clasificación de productos; no escribe datos.
 * Los filtros preset pertenecen al módulo cliente, no son reglas de autorización.
 */
final class BuscadorProductosControlador extends Controller
{
    private const CLASIFICACIONES=[
        'rubro_id','departamento_id','familia_id','linea_id','marca_id','modelo_id',
        'tipo_unidad_id','tipo_envasado_id','condicion_tributaria_id'
    ];

    private function autorizar(Request $request): void
    {
        $user=$request->user();
        abort_unless($user && $user->activo,401);
        if($user->rol?->codigo==='administrador')return;
        $permitido=DB::table('roles_permisos as rp')
            ->join('permisos as p','p.id','=','rp.permiso_id')
            ->where('rp.rol_id',$user->rol_id)
            ->whereIn('p.codigo',[
                'productos.gestionar','compras.gestionar',
                'ventas.gestionar','inventario.gestionar'
            ])->exists();
        abort_unless($permitido,403,'Sin autorización para consultar productos');
    }

    public function listar(Request $request)
    {
        $this->autorizar($request);
        $reglas=[
            'buscar'=>['sometimes','nullable','string','max:120'],
            'codigo_barras'=>['sometimes','nullable','string','max:100'],
            'sku_desde'=>['sometimes','nullable','string','max:80'],
            'sku_hasta'=>['sometimes','nullable','string','max:80'],
            'id_desde'=>['sometimes','nullable','integer','min:1'],
            'id_hasta'=>['sometimes','nullable','integer','min:1'],
            'proveedor_id'=>['sometimes','nullable','integer','min:1'],
            'solo_sin_proveedor_id'=>['sometimes','nullable','integer','min:1'],
            'page'=>['sometimes','integer','min:1','max:10000'],
            'por_pagina'=>['sometimes','integer','min:1','max:25'],
            'orden'=>['sometimes','string','in:sku,nombre'],
        ];
        foreach(self::CLASIFICACIONES as $col)$reglas[$col]=
            ['sometimes','nullable','integer','min:1'];
        $values=$request->validate($reglas);
        if(isset($values['id_desde'],$values['id_hasta']))
            abort_if($values['id_desde']>$values['id_hasta'],422,
                'El identificador desde supera al hasta');
        if(isset($values['sku_desde'],$values['sku_hasta']))
            abort_if(strcmp($values['sku_desde'],$values['sku_hasta'])>0,422,
                'El SKU desde supera al hasta');
        // Únicamente productos activos; nunca seleccionar un producto desactivado
        // para documentos comerciales. Los módulos deben revalidar reglas al grabar.
        $q=DB::table('productos as p')->where('p.activo',1);
        if(!empty($values['buscar'])){
            $like='%'.trim($values['buscar']).'%';
            $q->where(function($q)use($like){
                $q->where('p.nombre','like',$like)
                  ->orWhere('p.sku','like',$like)
                  ->orWhere('p.descripcion','like',$like)
                  ->orWhereExists(function($b)use($like){
                      $b->selectRaw('1')
                        ->from('productos_codigos_barra_venta as bc')
                        ->whereColumn('bc.producto_id','p.id')
                        ->where('bc.activo',1)->where('bc.codigo_barras','like',$like);
                  })
                  ->orWhereExists(function($b)use($like){
                      $b->selectRaw('1')->from('productos_proveedores as pp')
                        ->join('productos_codigos_barra_compra as bc',
                          'bc.producto_proveedor_id','=','pp.id')
                        ->whereColumn('pp.producto_id','p.id')
                        ->where('pp.activo',1)->where('bc.activo',1)
                        ->where('bc.codigo_barras','like',$like);
                  });
            });
        }
        if(!empty($values['codigo_barras'])){
            $needle='%'.trim($values['codigo_barras']).'%';
            $q->where(function($q)use($needle){
                $q->whereExists(function($b)use($needle){
                    $b->selectRaw('1')->from('productos_codigos_barra_venta as v')
                      ->whereColumn('v.producto_id','p.id')
                      ->where('v.activo',1)->where('v.codigo_barras','like',$needle);
                })->orWhereExists(function($b)use($needle){
                    $b->selectRaw('1')->from('productos_proveedores as pp')
                      ->join('productos_codigos_barra_compra as c',
                        'c.producto_proveedor_id','=','pp.id')
                      ->whereColumn('pp.producto_id','p.id')->where('pp.activo',1)
                      ->where('c.activo',1)->where('c.codigo_barras','like',$needle);
                });
            });
        }
        foreach(self::CLASIFICACIONES as $column)
            if(!empty($values[$column]))$q->where('p.'.$column,(int)$values[$column]);
        foreach(['sku_desde'=>'>=','sku_hasta'=>'<='] as $key=>$op)
            if(isset($values[$key]))$q->where('p.sku',$op,$values[$key]);
        foreach(['id_desde'=>'>=','id_hasta'=>'<='] as $key=>$op)
            if(isset($values[$key]))$q->where('p.id',$op,(int)$values[$key]);
        if(!empty($values['proveedor_id'])){
            $supplierId=(int)$values['proveedor_id'];
            $q->whereExists(fn($b)=>$b->selectRaw('1')
                ->from('productos_proveedores as pp')
                ->whereColumn('pp.producto_id','p.id')
                ->where('pp.proveedor_id',$supplierId)->where('pp.activo',1));
        }
        if(!empty($values['solo_sin_proveedor_id'])){
            $supplierId=(int)$values['solo_sin_proveedor_id'];
            $q->whereNotExists(fn($b)=>$b->selectRaw('1')
                ->from('productos_proveedores as pp')
                ->whereColumn('pp.producto_id','p.id')
                ->where('pp.proveedor_id',$supplierId)->where('pp.activo',1));
        }
        $q->select(array_merge(['p.id','p.sku','p.nombre','p.descripcion'],
            array_map(fn($col)=>'p.'.$col,self::CLASIFICACIONES)));
        $order=$values['orden']??'nombre';
        $page=$q->orderBy('p.'.$order)->orderBy('p.id')
            ->paginate((int)($values['por_pagina']??25));
        $ids=$page->getCollection()->pluck('id')->all();
        $barcodes=empty($ids)?collect():DB::table('productos_codigos_barra_venta')
            ->whereIn('producto_id',$ids)->where('activo',1)
            ->orderBy('id')->get(['producto_id','codigo_barras'])
            ->groupBy('producto_id');
        $page->getCollection()->transform(function(object $p)use($barcodes){
            $clasificacion=[];
            foreach(self::CLASIFICACIONES as $key)
                $clasificacion[$key]=$p->{$key}===null?null:(int)$p->{$key};
            return ['id'=>(int)$p->id,'sku'=>$p->sku,'nombre'=>$p->nombre,
                'descripcion'=>$p->descripcion,'activo'=>true,
                'clasificacion'=>$clasificacion,
                'codigosVenta'=>$barcodes->get($p->id,collect())
                    ->pluck('codigo_barras')->values()->all()];
        });
        return response()->json($page)->header('Cache-Control','no-store');
    }
}
