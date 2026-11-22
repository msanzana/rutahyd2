<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Contratos de abastecimiento por producto y proveedor; nunca altera inventario. */
final class ProductosProveedoresControlador extends Controller
{
    private function base()
    {
        return DB::table('productos_proveedores as pp')
            ->join('productos as p','p.id','=','pp.producto_id')
            ->join('proveedores as prov','prov.id','=','pp.proveedor_id')
            ->select('pp.id','pp.producto_id','pp.proveedor_id',
                'p.sku as codigo_producto','p.nombre as producto',
                'prov.nombre as proveedor','pp.codigo_proveedor_producto',
                'pp.costo_proveedor','pp.ultima_recepcion_id',
                'pp.fecha_ultima_recepcion','pp.activo',
                'pp.creado_en','pp.actualizado_en');
    }

    public function listar(Request $request)
    {
        $data=$request->validate([
            'proveedor_id'=>['sometimes','nullable','integer','min:1'],
            'producto_id'=>['sometimes','nullable','integer','min:1'],
            'buscar'=>['sometimes','nullable','string','max:120'],
            'por_pagina'=>['sometimes','integer','min:1','max:100'],
            'incluye_inactivos'=>['sometimes','boolean']
        ]);
        $query=$this->base();
        if (!empty($data['proveedor_id'])) $query->where('pp.proveedor_id',$data['proveedor_id']);
        if (!empty($data['producto_id'])) $query->where('pp.producto_id',$data['producto_id']);
        if (!(bool)($data['incluye_inactivos']??false)) $query->where('pp.activo',1);
        if (!empty($data['buscar'])) {
            $like='%'.trim($data['buscar']).'%';
            $query->where(function($q)use($like){
                $q->where('p.sku','like',$like)->orWhere('p.nombre','like',$like)
                    ->orWhere('prov.nombre','like',$like)
                    ->orWhere('pp.codigo_proveedor_producto','like',$like);
            });
        }
        return response()->json($query->orderBy('pp.id','desc')
            ->paginate((int)($data['por_pagina']??25)))->header('Cache-Control','no-store');
    }

    public function ver(int $id)
    {
        $row=$this->base()->where('pp.id',$id)->first();
        abort_unless($row,404,'Relación no encontrada');
        return response()->json($row)->header('Cache-Control','no-store');
    }

    private function validar(Request $request, bool $new): array
    {
        $data=Validator::make($request->all(),[
            'producto_id'=>[$new?'required':'sometimes','integer','min:1'],
            'proveedor_id'=>[$new?'required':'sometimes','integer','min:1'],
            'codigo_proveedor_producto'=>['sometimes','nullable','string','max:80'],
            'costo_proveedor'=>['sometimes','nullable','numeric','min:0','max:99999999999'],
            'activo'=>['sometimes','boolean'],
        ])->validate();
        abort_if(array_diff(array_keys($request->all()),array_keys(
            ['producto_id'=>1,'proveedor_id'=>1,'codigo_proveedor_producto'=>1,
             'costo_proveedor'=>1,'activo'=>1]))!==[],
            422,'Los campos de última recepción son de solo lectura');
        abort_if(!$new && $data===[],422,'No existen cambios');
        if (isset($data['codigo_proveedor_producto'])) {
            $code=trim($data['codigo_proveedor_producto']);
            $data['codigo_proveedor_producto']=$code!==''?$code:null;
        }
        return $data;
    }

    private function save(Request $request, ?int $id)
    {
        $new=$id===null;
        $data=$this->validar($request,$new);
        try {
            return DB::transaction(function()use($new,$id,$data){
                if (!$new) {
                    $record=DB::table('productos_proveedores')->where('id',$id)
                        ->lockForUpdate()->first();
                    abort_unless($record,404,'Relación no encontrada');
                    // Reasignar proveedor o producto de un contrato altera el historial.
                    abort_if(isset($data['producto_id']) && (int)$data['producto_id']!==(int)$record->producto_id,
                        422,'El producto no puede cambiarse en esta relación');
                    abort_if(isset($data['proveedor_id']) && (int)$data['proveedor_id']!==(int)$record->proveedor_id,
                        422,'El proveedor no puede cambiarse en esta relación');
                    unset($data['producto_id'],$data['proveedor_id']);
                }
                if ($new){
                    abort_unless(DB::table('productos')->where('id',$data['producto_id'])
                        ->where('activo',1)->exists(),422,'Producto no disponible');
                    abort_unless(DB::table('proveedores')->where('id',$data['proveedor_id'])
                        ->where('activo',1)->exists(),422,'Proveedor no disponible');
                    $data['activo']=$data['activo']??1;
                    $id=(int)DB::table('productos_proveedores')->insertGetId($data);
                }else DB::table('productos_proveedores')->where('id',$id)->update($data);
                return $this->base()->where('pp.id',$id)->first();
            });
        }catch(QueryException $e){
            if((string)$e->getCode()==='23000')abort(409,
                'Ya existe la relación proveedor-producto o su código de compra');
            throw $e;
        }
    }

    public function crear(Request $request)
    {
        return response()->json($this->save($request,null),201)
            ->header('Cache-Control','no-store');
    }
    public function actualizar(Request $request,int $id)
    {
        return response()->json($this->save($request,$id))
            ->header('Cache-Control','no-store');
    }
    public function desactivar(int $id)
    {
        $found=DB::table('productos_proveedores')->where('id',$id)->first();
        abort_unless($found,404,'Relación no encontrada');
        DB::table('productos_proveedores')->where('id',$id)->update(['activo'=>0]);
        return response()->json(['id'=>$id,'activo'=>false,'eliminacion'=>'logica']);
    }
}
