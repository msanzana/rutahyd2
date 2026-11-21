<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD productivo: producto + clasificación + presentación de venta.
 * El stock nunca se edita aquí (es responsabilidad de Inventario).
 * La "eliminación" es lógica para conservar referencias comerciales.
 */
final class ProductoComercialControlador extends Controller
{
    private const CLASIFICACIONES = [
        'rubroId' => ['rubros','rubro_id'],
        'departamentoId' => ['departamentos','departamento_id','rubro_id','rubroId'],
        'familiaId' => ['familias','familia_id','departamento_id','departamentoId'],
        'lineaId' => ['lineas','linea_id','familia_id','familiaId'],
        'marcaId' => ['marcas','marca_id'],
        'modeloId' => ['modelos','modelo_id','marca_id','marcaId'],
        'tipoUnidadId' => ['tipos_unidad','tipo_unidad_id'],
        'gramajeId' => ['gramajes','gramaje_id'],
        'tipoEnvasadoId' => ['tipos_envasado','tipo_envasado_id'],
        'condicionTributariaId' => ['condiciones_tributarias','condicion_tributaria_id'],
    ];

    private function validarClasificacion(array $datos): array
    {
        $reglas=[];
        foreach (self::CLASIFICACIONES as $campo=>$config) {
            $reglas[$campo]=['required','integer','min:1'];
        }
        $validados=Validator::make($datos,$reglas)->validate();
        $columnas=[];$filas=[];
        foreach (self::CLASIFICACIONES as $campo=>$config) {
            $fila=DB::table($config[0])->where('id',(int)$validados[$campo])->first();
            abort_unless($fila && (bool)$fila->activo,422,'Clasificación no disponible: '.$campo);
            $filas[$campo]=$fila;
            $columnas[$config[1]]=(int)$validados[$campo];
        }
        foreach (self::CLASIFICACIONES as $campo=>$config) {
            if (isset($config[2])) {
                abort_unless((int)$filas[$campo]->{$config[2]} === (int)$validados[$config[3]],
                    422,'Jerarquía de clasificación incompatible: '.$campo);
            }
        }
        return [$columnas,$filas['condicionTributariaId']->codigo];
    }

    private function resultado(object $producto, ?object $presentacion=null, bool $conStock=false): array
    {
        $clasificacion=[];
        foreach (self::CLASIFICACIONES as $campo=>$config) {
            $clasificacion[$campo]=$producto->{$config[1]}===null
                ? null : (int)$producto->{$config[1]};
        }
        $data=[
            'id'=>(int)$producto->id,
            'sku'=>$producto->sku,
            'nombre'=>$producto->nombre,
            'descripcion'=>$producto->descripcion,
            'activo'=>(bool)$producto->activo,
            'proveedorId'=>$producto->proveedor_id===null ? null : (int)$producto->proveedor_id,
            'categoriaLegadaId'=>$producto->categoria_id===null ? null : (int)$producto->categoria_id,
            'clasificacion'=>$clasificacion,
            'presentacion'=>$presentacion ? [
                'id'=>(int)$presentacion->id,
                'nombre'=>$presentacion->nombre,
                'codigoBarras'=>$presentacion->codigo_barras,
                'unidadesPorEnvase'=>(string)$presentacion->unidades_por_envase,
                'precioNeto'=>(string)$presentacion->precio_neto,
                'tasaIva'=>(string)$presentacion->tasa_iva,
                'activo'=>(bool)$presentacion->activo,
            ] : null,
        ];
        if ($conStock) {
            $data['stock']= $presentacion
                ? (string)DB::table('saldos_inventario')->where('presentacion_id',$presentacion->id)->sum('cantidad')
                : '0';
        }
        return $data;
    }

    public function listar(Request $request)
    {
        $datos=$request->validate([
            'page'=>['sometimes','integer','min:1','max:10000'],
            'por_pagina'=>['sometimes','integer','min:1','max:100'],
            'buscar'=>['sometimes','nullable','string','max:120'],
            'incluye_inactivos'=>['sometimes','boolean']
        ]);
        $q=DB::table('productos');
        if (!(bool)($datos['incluye_inactivos']??false)) $q->where('activo',1);
        if (!empty($datos['buscar'])) {
            $busqueda=trim($datos['buscar']);
            $q->where(function($sub)use($busqueda){
                $sub->where('nombre','like','%'.$busqueda.'%')
                    ->orWhere('sku','like','%'.$busqueda.'%');
            });
        }
        $paginado=$q->orderByDesc('id')->paginate((int)($datos['por_pagina']??25));
        $ids=$paginado->getCollection()->pluck('id')->all();
        $presentaciones=empty($ids) ? collect() :
            DB::table('presentaciones_productos')->whereIn('producto_id',$ids)
                ->orderBy('id')->get()->groupBy('producto_id');
        $paginado->getCollection()->transform(function(object $p)use($presentaciones){
            $presentacion=$presentaciones->get($p->id)?->first();
            return $this->resultado($p,$presentacion);
        });
        return response()->json($paginado);
    }

    public function ver(int $id)
    {
        $producto=DB::table('productos')->where('id',$id)->first();
        abort_unless($producto,404,'Producto no encontrado');
        $presentaciones=DB::table('presentaciones_productos')
            ->where('producto_id',$id)->orderBy('id')->get();
        $data=$this->resultado($producto,$presentaciones->first(),true);
        $data['totalPresentaciones']=$presentaciones->count();
        return response()->json($data);
    }

    private function reglas(bool $nuevo): array
    {
        $required=$nuevo?'required':'sometimes';
        return [
            'sku'=>[$required,'string','min:1','max:80'],
            'nombre'=>[$required,'string','min:2','max:190'],
            'descripcion'=>['sometimes','nullable','string','max:10000'],
            'activo'=>['sometimes','boolean'],
            'proveedorId'=>['sometimes','nullable','integer','min:1'],
            'clasificacion'=>[$required,'array','size:10'],
            'presentacion'=>[$required,'array'],
            'presentacion.id'=>['sometimes','nullable','integer','min:1'],
            'presentacion.nombre'=>[$required,'string','min:1','max:150'],
            'presentacion.codigoBarras'=>['sometimes','nullable','string','max:80'],
            'presentacion.unidadesPorEnvase'=>[$required,'numeric','min:0.001','max:99999999999'],
            'presentacion.precioNeto'=>[$required,'numeric','min:0','max:99999999999'],
        ];
    }

    private function guardar(Request $request, ?int $id)
    {
        $nuevo=$id===null;
        $datos=Validator::make($request->all(),$this->reglas($nuevo))->validate();
        return DB::transaction(function()use($id,$nuevo,$datos){
            $actual=$nuevo ? null : DB::table('productos')->where('id',$id)->lockForUpdate()->first();
            if (!$nuevo) abort_unless($actual,404,'Producto no encontrado');
            $cambios=[];
            foreach (['sku','nombre','descripcion','activo'] as $campo) {
                if (array_key_exists($campo,$datos)) {
                    $cambios[$campo]=$campo==='sku'||$campo==='nombre'
                        ? trim((string)$datos[$campo]) : $datos[$campo];
                }
            }
            if (isset($cambios['sku'])) {
                abort_if($cambios['sku']==='',422,'SKU obligatorio');
                $repetido=DB::table('productos')->where('sku',$cambios['sku']);
                if (!$nuevo) $repetido->where('id','!=',$id);
                abort_if($repetido->exists(),409,'El SKU ya existe');
            }
            if (isset($cambios['nombre'])) abort_if($cambios['nombre']==='',422,'Nombre obligatorio');
            if (array_key_exists('proveedorId',$datos)) {
                $proveedor=$datos['proveedorId'];
                if ($proveedor !== null) {
                    abort_unless(DB::table('proveedores')->where('id',(int)$proveedor)->where('activo',1)->exists(),
                        422,'Proveedor inexistente o inactivo');
                }
                $cambios['proveedor_id']=$proveedor;
            }
            $codigoTributario=null;
            if (array_key_exists('clasificacion',$datos)) {
                [$columnas,$codigoTributario]=$this->validarClasificacion($datos['clasificacion']);
                $cambios=array_merge($cambios,$columnas);
            }
            if ($nuevo) {
                $cambios['activo']=$cambios['activo']??1;
                // Nunca establece categoria_id legado, que sigue reservado a compatibilidad V1.
                $id=(int)DB::table('productos')->insertGetId($cambios);
            } elseif ($cambios) {
                DB::table('productos')->where('id',$id)->update($cambios);
            }

            if (isset($datos['presentacion'])) {
                $p=$datos['presentacion'];
                $presentacionId=$p['id']??null;
                if ($nuevo) abort_if($presentacionId!==null,422,'Presentación de otro producto');
                if (!$nuevo) {
                    $total=DB::table('presentaciones_productos')->where('producto_id',$id)->count();
                    abort_if($total>1 && $presentacionId===null,422,
                        'Selecciona una presentación concreta para no modificar otro precio');
                    if ($presentacionId===null) {
                        $presentacionId=DB::table('presentaciones_productos')->where('producto_id',$id)->value('id');
                    }
                    if ($presentacionId!==null) abort_unless(
                        DB::table('presentaciones_productos')->where('id',$presentacionId)
                            ->where('producto_id',$id)->exists(),422,'Presentación no pertenece al producto');
                }
                $barcode=array_key_exists('codigoBarras',$p) ? trim((string)($p['codigoBarras']??'')) : '';
                if ($barcode!=='') {
                    $codigoQuery=DB::table('presentaciones_productos')->where('codigo_barras',$barcode);
                    if ($presentacionId!==null) $codigoQuery->where('id','!=',$presentacionId);
                    abort_if($codigoQuery->exists(),409,'Código de barras duplicado');
                }
                $tax=$codigoTributario ?? DB::table('condiciones_tributarias')
                    ->where('id',$actual?->condicion_tributaria_id)->value('codigo');
                abort_unless(in_array($tax,['AFECTO','EXENTO'],true),422,'Condición tributaria requerida');
                $presentacion=[
                    'nombre'=>trim($p['nombre']),
                    'codigo_barras'=>$barcode!=='' ? $barcode : null,
                    'unidades_por_envase'=>$p['unidadesPorEnvase'],
                    'precio_neto'=>$p['precioNeto'],
                    'tasa_iva'=>$tax==='EXENTO' ? 0 : 19,
                    'activo'=>1,
                ];
                abort_if($presentacion['nombre']==='',422,'Nombre de presentación obligatorio');
                $other=DB::table('presentaciones_productos')
                    ->where('producto_id',$id)->where('nombre',$presentacion['nombre']);
                if ($presentacionId!==null) $other->where('id','!=',$presentacionId);
                abort_if($other->exists(),409,'Presentación duplicada');
                if ($presentacionId===null) {
                    $presentacion['producto_id']=$id;
                    DB::table('presentaciones_productos')->insert($presentacion);
                } else {
                    DB::table('presentaciones_productos')->where('id',$presentacionId)->update($presentacion);
                }
            } elseif ($codigoTributario!==null) {
                // No cambiar silenciosamente IVA en ventas ni en presentaciones históricas.
                $presentaciones=DB::table('presentaciones_productos')->where('producto_id',$id)->count();
                abort_if($presentaciones>0,422,'Edita también la presentación al cambiar clasificación tributaria');
            }
            $producto=DB::table('productos')->where('id',$id)->first();
            $principal=DB::table('presentaciones_productos')->where('producto_id',$id)->orderBy('id')->first();
            return $this->resultado($producto,$principal,true);
        });
    }

    public function crear(Request $request)
    {
        try {return response()->json($this->guardar($request,null),201);}
        catch(QueryException $e) {
            if ((string)$e->getCode()==='23000') abort(409,'Producto duplicado o relación inválida');
            throw $e;
        }
    }

    public function actualizar(Request $request,int $id)
    {
        try {return response()->json($this->guardar($request,$id));}
        catch(QueryException $e) {
            if ((string)$e->getCode()==='23000') abort(409,'Producto duplicado o relación inválida');
            throw $e;
        }
    }

    public function desactivar(int $id)
    {
        // Borrado lógico: nunca destruye ventas, trazabilidad, stock ni presentaciones.
        $afectadas=DB::table('productos')->where('id',$id)->update(['activo'=>0]);
        abort_unless($afectadas || DB::table('productos')->where('id',$id)->exists(),
            404,'Producto inexistente');
        return response()->json(['id'=>$id,'activo'=>false,'eliminacion'=>'logica']);
    }
}
