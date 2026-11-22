<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Códigos por unidad logística de venta o compra.
 * Ventas: sólo producto, sin proveedor.
 * Compras: vínculo proveedor-producto, con su SKU y código de proveedor derivado.
 * Nunca modifica stock ni órdenes/recepciones.
 */
final class CodigosBarrasProductoControlador extends Controller
{
    private function context(string $modo,int $contextId): array
    {
        if($modo==='venta') {
            abort_unless(DB::table('productos')->where('id',$contextId)->exists(),404,'Producto no encontrado');
            return ['productos_codigos_barra_venta','producto_id'];
        }
        abort_unless(DB::table('productos_proveedores')->where('id',$contextId)->exists(),404,
            'Relación producto-proveedor no encontrada');
        return ['productos_codigos_barra_compra','producto_proveedor_id'];
    }

    private function validateBarcode(Request $request): array
    {
        $data=Validator::make($request->all(),[
            'tipo_codigo_barra_id'=>['required','integer','min:1'],
            'codigo_barras'=>['required','string','min:1','max:100'],
            'unidades_por_codigo'=>['required','numeric','gt:0','max:999999999999'],
            'activo'=>['sometimes','boolean'],
        ])->validate();
        abort_if(array_diff(array_keys($request->all()),array_keys([
            'tipo_codigo_barra_id'=>1,'codigo_barras'=>1,'unidades_por_codigo'=>1,'activo'=>1]))!==[],
            422,'Campos no permitidos');
        $type=DB::table('tipos_codigos_barras')
            ->where('id',$data['tipo_codigo_barra_id'])->where('activo',1)->first();
        abort_unless($type,422,'Tipo de código de barras inexistente o desactivado');
        $barcode=trim($data['codigo_barras']);
        abort_if($barcode==='',422,'El código de barras es obligatorio');
        // Los símbolos EAN/UPC/DUN deben estar codificados sólo con dígitos y longitud correcta.
        $lengths=['EAN13'=>13,'EAN8'=>8,'DUN14'=>14,'ITF14'=>14,'UPCA'=>12,'UPCE'=>8];
        if(isset($lengths[$type->codigo])){
            $len=$lengths[$type->codigo];
            abort_unless(preg_match('/^[0-9]{'.$len.'}$/D',$barcode)===1,422,
                'El tipo '.$type->codigo.' requiere '.$len.' dígitos');
        }
        abort_if(preg_match('/[\x00-\x1F\x7F]/',$barcode)===1,422,
            'El código contiene caracteres de control');
        $data['codigo_barras']=$barcode;
        $data['activo']=$data['activo']??1;
        return $data;
    }

    private function listFor(string $modo,int $contextId)
    {
        [$table,$key]=$this->context($modo,$contextId);
        $query=DB::table($table.' as b')
            ->join('tipos_codigos_barras as t','t.id','=','b.tipo_codigo_barra_id')
            ->select('b.id','b.'.$key.' as referencia_id','b.tipo_codigo_barra_id',
                't.codigo as tipo_codigo','t.nombre as tipo_nombre','b.codigo_barras',
                'b.unidades_por_codigo','b.activo','b.creado_en','b.actualizado_en')
            ->where('b.'.$key,$contextId)
            ->orderBy('b.id','desc');
        if ($modo==='compra') {
            $query->addSelect('pp.producto_id','pp.proveedor_id',
                'p.sku as codigo_producto','pp.codigo_proveedor_producto')
                ->join('productos_proveedores as pp','pp.id','=','b.producto_proveedor_id')
                ->join('productos as p','p.id','=','pp.producto_id');
        }else $query->addSelect('b.producto_id');
        return $query->get();
    }

    public function listarVenta(int $productoId)
    {
        return response()->json(['items'=>$this->listFor('venta',$productoId)])
            ->header('Cache-Control','no-store');
    }

    public function listarCompra(int $vinculoId)
    {
        return response()->json(['items'=>$this->listFor('compra',$vinculoId)])
            ->header('Cache-Control','no-store');
    }

    private function createOrUpdate(Request $request,string $modo,int $contextId,?int $id)
    {
        [$table,$key]=$this->context($modo,$contextId);
        // El control de estado envía solo {activo}; no debe exigir nuevamente
        // código, tipo y unidades al activar/desactivar un código ya registrado.
        // La creación y la edición normal conservan todas las reglas existentes.
        if($id!==null && array_keys($request->all())===['activo']){
            $data=Validator::make($request->all(),[
                'activo'=>['required','boolean'],
            ])->validate();
        }else $data=$this->validateBarcode($request);
        try{
            return DB::transaction(function()use($table,$key,$data,$id,$contextId){
                if($id!==null){
                    $current=DB::table($table)->where('id',$id)->where($key,$contextId)
                        ->lockForUpdate()->first();
                    abort_unless($current,404,'Código no encontrado en este producto');
                    DB::table($table)->where('id',$id)->update($data);
                }else{
                    $data[$key]=$contextId;
                    $id=(int)DB::table($table)->insertGetId($data);
                }
                return DB::table($table)->where('id',$id)->first();
            });
        }catch(QueryException $e){
            if((string)$e->getCode()==='23000')abort(409,
                'Código de barras duplicado o relación inexistente');
            throw $e;
        }
    }

    public function crearVenta(Request $r,int $productoId){
        return response()->json($this->createOrUpdate($r,'venta',$productoId,null),201)
            ->header('Cache-Control','no-store');
    }
    public function actualizarVenta(Request $r,int $productoId,int $id){
        return response()->json($this->createOrUpdate($r,'venta',$productoId,$id))
            ->header('Cache-Control','no-store');
    }
    public function crearCompra(Request $r,int $vinculoId){
        return response()->json($this->createOrUpdate($r,'compra',$vinculoId,null),201)
            ->header('Cache-Control','no-store');
    }
    public function actualizarCompra(Request $r,int $vinculoId,int $id){
        return response()->json($this->createOrUpdate($r,'compra',$vinculoId,$id))
            ->header('Cache-Control','no-store');
    }

    private function desactivar(string $modo,int $contextId,int $id)
    {
        [$table,$key]=$this->context($modo,$contextId);
        $code=DB::table($table)->where('id',$id)->where($key,$contextId)->first();
        abort_unless($code,404,'Código de barras no encontrado');
        DB::table($table)->where('id',$id)->update(['activo'=>0]);
        return response()->json(['id'=>$id,'activo'=>false,'eliminacion'=>'logica']);
    }
    public function desactivarVenta(int $productoId,int $id){
        return $this->desactivar('venta',$productoId,$id);
    }
    public function desactivarCompra(int $vinculoId,int $id){
        return $this->desactivar('compra',$vinculoId,$id);
    }
}
