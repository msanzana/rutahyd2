<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use App\Services\GestionListasPrecios;

/**
 * CRUD productivo: producto + clasificación + presentación de venta.
 * El stock nunca se edita aquí (es responsabilidad de Inventario).
 * La "eliminación" es lógica para conservar referencias comerciales.
 */
final class ProductoComercialControlador extends Controller
{
    public function __construct(private GestionListasPrecios $listasPrecios) {}

    private const CLASIFICACIONES = [
        'rubroId' => ['rubros','rubro_id'],
        'departamentoId' => ['departamentos','departamento_id','rubro_id','rubroId'],
        'familiaId' => ['familias','familia_id','departamento_id','departamentoId'],
        'lineaId' => ['lineas','linea_id','familia_id','familiaId'],
        'marcaId' => ['marcas','marca_id'],
        'modeloId' => ['modelos','modelo_id','marca_id','marcaId'],
        'tipoUnidadId' => ['tipos_unidad','tipo_unidad_id'],
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
            'costoNeto'=>isset($producto->costo_neto)?(string)$producto->costo_neto:'0',
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
            'orden'=>['sometimes','string','in:sku,nombre,rubro,departamento,familia,linea,marca,modelo,activo'],
            'direccion'=>['sometimes','string','in:asc,desc'],
            'buscar'=>['sometimes','nullable','string','max:120'],
            'incluye_inactivos'=>['sometimes','boolean'],
            'sku'=>['sometimes','nullable','string','max:80'],
            'nombre'=>['sometimes','nullable','string','max:190'],
            'descripcion'=>['sometimes','nullable','string','max:200'],
            'codigo_barras'=>['sometimes','nullable','string','max:100'],
            'tipo_codigo_barra_id'=>['sometimes','nullable','integer','min:1'],
            'activo'=>['sometimes','nullable','boolean'],
            'precio_min'=>['sometimes','nullable','numeric','min:0','max:99999999999'],
            'precio_max'=>['sometimes','nullable','numeric','min:0','max:99999999999'],
            'iva'=>['sometimes','nullable','numeric','min:0','max:100'],
            'proveedor_id'=>['sometimes','nullable','integer','min:1'],
            'presentacion'=>['sometimes','nullable','string','max:150'],
            'unidades_min'=>['sometimes','nullable','numeric','min:0'],
            'unidades_max'=>['sometimes','nullable','numeric','min:0'],
        ] + array_fill_keys(array_column(self::CLASIFICACIONES, 1), ['sometimes','nullable','integer','min:1']));
        if (isset($datos['precio_min'],$datos['precio_max'])) {
            abort_if((float)$datos['precio_min']>(float)$datos['precio_max'],422,
                'El precio mínimo no puede superar al máximo');
        }
        if (isset($datos['unidades_min'],$datos['unidades_max'])) {
            abort_if((float)$datos['unidades_min']>(float)$datos['unidades_max'],422,
                'Las unidades mínimas no pueden superar a las máximas');
        }
        $q=DB::table('productos');
        if (array_key_exists('activo',$datos) && $datos['activo']!==null) {
            $q->where('activo',(bool)$datos['activo']?1:0);
        }elseif(!(bool)($datos['incluye_inactivos']??false)){
            $q->where('activo',1);
        }
        if (!empty($datos['buscar'])) {
            $busqueda=trim($datos['buscar']);
            $q->where(function($sub)use($busqueda){
                $sub->where('nombre','like','%'.$busqueda.'%')
                    ->orWhere('sku','like','%'.$busqueda.'%');
            });
        }
        foreach(self::CLASIFICACIONES as $config){
            $columna=$config[1];
            if(isset($datos[$columna]))$q->where($columna,(int)$datos[$columna]);
        }
        foreach(['sku','nombre','descripcion'] as $campo){
            if(isset($datos[$campo])&&trim((string)$datos[$campo])!==''){
                $q->where($campo,'like','%'.trim((string)$datos[$campo]).'%');
            }
        }
        if(isset($datos['proveedor_id']))$q->where('proveedor_id',(int)$datos['proveedor_id']);
        $codigo=trim((string)($datos['codigo_barras']??''));
        $tipo=$datos['tipo_codigo_barra_id']??null;
        if($codigo!==''||$tipo!==null){
            $q->where(function($sub)use($codigo,$tipo){
                $sub->whereExists(function($codes)use($codigo,$tipo){
                    $codes->selectRaw('1')->from('productos_codigos_barra_venta as cb')
                        ->whereColumn('cb.producto_id','productos.id');
                    if($codigo!=='')$codes->where('cb.codigo_barras','like','%'.$codigo.'%');
                    if($tipo!==null)$codes->where('cb.tipo_codigo_barra_id',(int)$tipo);
                });
                if($tipo===null&&$codigo!==''){
                    $sub->orWhereExists(function($legacy)use($codigo){
                        $legacy->selectRaw('1')->from('presentaciones_productos as ppb')
                            ->whereColumn('ppb.producto_id','productos.id')
                            ->where('ppb.codigo_barras','like','%'.$codigo.'%');
                    });
                }
            });
        }
        $presentacionFiltros=['presentacion','precio_min','precio_max','iva','unidades_min','unidades_max'];
        if(array_intersect($presentacionFiltros,array_keys($datos))){
            $q->whereExists(function($sub)use($datos){
                $sub->selectRaw('1')->from('presentaciones_productos as pp')
                    ->whereColumn('pp.producto_id','productos.id');
                if(isset($datos['presentacion'])&&trim((string)$datos['presentacion'])!==''){
                    $sub->where('pp.nombre','like','%'.trim((string)$datos['presentacion']).'%');
                }
                foreach(['precio_min'=>'>=','precio_max'=>'<='] as $campo=>$op){
                    if(isset($datos[$campo]))$sub->where('pp.precio_neto',$op,$datos[$campo]);
                }
                if(isset($datos['iva']))$sub->where('pp.tasa_iva',$datos['iva']);
                foreach(['unidades_min'=>'>=','unidades_max'=>'<='] as $campo=>$op){
                    if(isset($datos[$campo]))$sub->where('pp.unidades_por_envase',$op,$datos[$campo]);
                }
            });
        }
        // Ordenar ANTES de paginar. Solo campos permitidos, no SQL dinámico del cliente.
        $orden=$datos['orden']??'id';
        $direccion=strtolower((string)($datos['direccion']??'asc'));
        $orderColumns=['sku'=>'sku','nombre'=>'nombre','activo'=>'activo'];
        $sortCatalogs=[
            'rubro'=>['rubros','rubro_id'],
            'departamento'=>['departamentos','departamento_id'],
            'familia'=>['familias','familia_id'],
            'linea'=>['lineas','linea_id'],
            'marca'=>['marcas','marca_id'],
            'modelo'=>['modelos','modelo_id']
        ];
        if(isset($sortCatalogs[$orden])){
            [$catalog,$col]=$sortCatalogs[$orden];
            $sub=DB::table($catalog)->select('nombre')
                ->whereColumn($catalog.'.id','productos.'.$col)->limit(1);
            $q->orderBy($sub,$direccion);
        }elseif(isset($orderColumns[$orden])){
            $q->orderBy('productos.'.$orderColumns[$orden],$direccion);
        }else{
            $q->orderByDesc('productos.id');
        }
        $paginado=$q->orderBy('productos.id')->paginate((int)($datos['por_pagina']??25));
        $ids=$paginado->getCollection()->pluck('id')->all();
        $presentaciones=empty($ids) ? collect() :
            DB::table('presentaciones_productos')->whereIn('producto_id',$ids)
                ->orderBy('id')->get()->groupBy('producto_id');
        // Completar exclusivamente los productos de la página (sin N+1).
        $catalogNames=[];
        foreach(self::CLASIFICACIONES as $key=>$cfg){
            if(!in_array($key,['rubroId','departamentoId','familiaId','lineaId','marcaId','modeloId'],true))continue;
            $idsCatalog=$paginado->getCollection()->pluck($cfg[1])->filter()->unique()->all();
            $catalogNames[$key]=empty($idsCatalog)?collect():
                DB::table($cfg[0])->whereIn('id',$idsCatalog)->pluck('nombre','id');
        }
        $precios=empty($ids)?collect():DB::table('listas_precios_detalle as d')
            ->join('listas_precios as l','l.id','=','d.lista_precio_id')
            ->whereIn('d.producto_id',$ids)
            ->where('d.activo',1)->where('l.activo',1)
            ->orderByDesc('l.es_base')->orderBy('l.nombre')
            ->get(['d.producto_id','d.lista_precio_id as listaPrecioId',
                'l.nombre as listaNombre','d.precio_venta as precioVenta'])
            ->groupBy('producto_id');
        $barcodes=empty($ids)?collect():DB::table('productos_codigos_barra_venta as b')
            ->join('tipos_codigos_barras as t','t.id','=','b.tipo_codigo_barra_id')
            ->whereIn('b.producto_id',$ids)->where('b.activo',1)
            ->orderBy('t.nombre')->orderBy('b.id')
            ->get(['b.producto_id','t.nombre as tipoNombre',
                'b.codigo_barras as codigoBarras'])->groupBy('producto_id');
        $fotos=collect();
        $fotosDisponibles=Schema::hasTable('productos_fotos');
        if($fotosDisponibles && $ids){
            $fotos=DB::table('productos_fotos')->select('producto_id')
                ->selectRaw('COUNT(*) as cantidad')->whereIn('producto_id',$ids)
                ->groupBy('producto_id')->pluck('cantidad','producto_id');
        }
        $paginado->getCollection()->transform(function(object $p)use(
            $presentaciones,$catalogNames,$precios,$barcodes,$fotos,$fotosDisponibles
        ){
            $presentacion=$presentaciones->get($p->id)?->first();
            $data=$this->resultado($p,$presentacion);
            $names=[];
            foreach($catalogNames as $key=>$namesById){
                $id=$data['clasificacion'][$key]??null;
                $names[$key]=$id===null?null:$namesById->get($id);
            }
            $data['clasificacionNombres']=$names;
            $data['precios']=$precios->get($p->id,collect())->values()->all();
            $data['codigosVenta']=$barcodes->get($p->id,collect())->values()->all();
            $data['cantidadFotos']=(int)$fotos->get($p->id,0);
            $data['galeriaDisponible']=$fotosDisponibles;
            return $data;
        });
        $respuesta=$paginado->toArray();
        $respuesta['filtros_aplicados']=true;
        return response()->json($respuesta);
    }

    private function codigosVenta(int $productoId): array
    {
        return DB::table('productos_codigos_barra_venta')
            ->where('producto_id',$productoId)->orderBy('id')->get()
            ->map(fn(object $b): array => [
                'id'=>(int)$b->id,
                'tipoCodigoBarraId'=>(int)$b->tipo_codigo_barra_id,
                'codigoBarras'=>$b->codigo_barras,
                'unidadesPorCodigo'=>(string)$b->unidades_por_codigo,
                'activo'=>(bool)$b->activo
            ])->all();
    }

    private function sincronizarCodigosVenta(int $productoId, array $codigos): void
    {
        $ids=[];$seen=[];
        foreach($codigos as $row){
            $type=DB::table('tipos_codigos_barras')->where('id',$row['tipoCodigoBarraId'])
                ->where('activo',1)->first();
            abort_unless($type,422,'Tipo de código de barras inválido');
            $code=trim($row['codigoBarras']);
            abort_if($code===''||isset($seen[$code]),409,'Código de barras repetido o vacío');
            $seen[$code]=true;
            $lengths=['EAN13'=>13,'EAN8'=>8,'DUN14'=>14,'ITF14'=>14,'UPCA'=>12,'UPCE'=>8];
            if(isset($lengths[$type->codigo]))abort_unless(
                preg_match('/^[0-9]{'.$lengths[$type->codigo].'}$/D',$code)===1,
                422,'Formato de código de barras incompatible con '.$type->codigo);
            $data=[
                'tipo_codigo_barra_id'=>(int)$row['tipoCodigoBarraId'],
                'codigo_barras'=>$code,'unidades_por_codigo'=>$row['unidadesPorCodigo'],
                'activo'=>($row['activo']??true)?1:0
            ];
            if(!empty($row['id'])){
                $existing=DB::table('productos_codigos_barra_venta')
                    ->where('id',(int)$row['id'])->where('producto_id',$productoId)
                    ->lockForUpdate()->first();
                abort_unless($existing,422,'El código de barras no pertenece al producto');
                DB::table('productos_codigos_barra_venta')->where('id',$row['id'])->update($data);
                $ids[]=(int)$row['id'];
            }else{
                $data['producto_id']=$productoId;
                $ids[]=(int)DB::table('productos_codigos_barra_venta')->insertGetId($data);
            }
        }
        $q=DB::table('productos_codigos_barra_venta')->where('producto_id',$productoId);
        if($ids) $q->whereNotIn('id',$ids);
        $q->update(['activo'=>0]); // Baja lógica: conservar trazabilidad de etiquetas históricas.
    }

    public function ver(int $id)
    {
        $producto=DB::table('productos')->where('id',$id)->first();
        abort_unless($producto,404,'Producto no encontrado');
        $presentaciones=DB::table('presentaciones_productos')
            ->where('producto_id',$id)->orderBy('id')->get();
        $data=$this->resultado($producto,$presentaciones->first(),true);
        $data['totalPresentaciones']=$presentaciones->count();
        $data['codigosVenta']=$this->codigosVenta($id);
        $minimo=DB::table('lineas')->where('id',$producto->linea_id)
            ->value('minimo_margen_precio_venta');
        $data['minimoMargenPrecioVenta']=(string)($minimo??0);
        $data['precios']=DB::table('listas_precios_detalle as d')
            ->join('listas_precios as l','l.id','=','d.lista_precio_id')
            ->where('d.producto_id',$id)->orderByDesc('l.es_base')
            ->orderBy('l.nombre')->get([
                'd.lista_precio_id as listaPrecioId','l.nombre as listaNombre',
                'l.es_base as esBase','d.precio_venta as precioVenta',
                'd.margen_porcentaje as margenPorcentaje','d.costo','d.activo'
            ])->toArray();
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
            'clasificacion'=>[$required,'array','size:9'],
            'presentacion'=>[$required,'array'],
            'presentacion.id'=>['sometimes','nullable','integer','min:1'],
            'presentacion.nombre'=>[$required,'string','min:1','max:150'],
            'presentacion.codigoBarras'=>['sometimes','nullable','string','max:80'],
            'presentacion.unidadesPorEnvase'=>[$required,'numeric','min:0.001','max:99999999999'],
            'presentacion.precioNeto'=>[$required,'numeric','min:0','max:99999999999'],
            'costoNeto'=>['sometimes','numeric','min:0','max:99999999999'],
            'precios'=>['sometimes','array','max:100'],
            'precios.*.listaPrecioId'=>['required','integer','min:1'],
            'precios.*.precioVenta'=>['sometimes','nullable','numeric','min:0','max:99999999999'],
            'precios.*.margenPorcentaje'=>['sometimes','nullable','numeric','min:0','max:999999'],
            'precios.*.activo'=>['sometimes','boolean'],
            'codigosVenta'=>['sometimes','array','max:100'],
            'codigosVenta.*.id'=>['sometimes','nullable','integer','min:1'],
            'codigosVenta.*.tipoCodigoBarraId'=>['required','integer','min:1'],
            'codigosVenta.*.codigoBarras'=>['required','string','max:100'],
            'codigosVenta.*.unidadesPorCodigo'=>['required','numeric','gt:0','max:999999999999'],
            'codigosVenta.*.activo'=>['sometimes','boolean'],
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
            if(array_key_exists('costoNeto',$datos)){
                $cambios['costo_neto']=$datos['costoNeto'];
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
            if(array_key_exists('codigosVenta',$datos)){
                $this->sincronizarCodigosVenta($id,$datos['codigosVenta']);
            }
            // Alta/edición de listas sin triggers y dentro de ESTA transacción.
            if ($nuevo) {
                $this->listasPrecios->asegurarPrecioBaseProducto($id);
            } elseif (array_key_exists('sku',$cambios)) {
                $this->listasPrecios->sincronizarSkuProducto($id,(string)$cambios['sku']);
                // Fotografías: mismo SKU actualizado en la misma transacción.
                // Compatibilidad con servidores que aún no importaron DDL25.
                if (Schema::hasTable('productos_fotos')) {
                    DB::table('productos_fotos')->where('producto_id',$id)
                        ->update(['sku'=>(string)$cambios['sku'],'actualizado_en'=>now()]);
                }
            }
            $costo=(float)DB::table('productos')->where('id',$id)->value('costo_neto');
            $precios=$datos['precios']??[];
            $enviadas=[];
            foreach($precios as $detalle){
                $listaId=(int)$detalle['listaPrecioId'];
                abort_if(in_array($listaId,$enviadas,true),422,
                    'Una lista de precios no puede repetirse en un producto');
                $enviadas[]=$listaId;
                $this->listasPrecios->guardarPrecioLista($id,$listaId,$costo,
                    isset($detalle['precioVenta'])?(float)$detalle['precioVenta']:null,
                    isset($detalle['margenPorcentaje'])?(float)$detalle['margenPorcentaje']:null,
                    (bool)($detalle['activo']??true),false);
            }
            // Contrato legado: precioNeto de presentación corresponde a LISTA BASE.
            // Cuando el nuevo editor no envía una fila base, conservar funcionalidad
            // del cliente anterior sincronizando el precio general.
            $baseId=$this->listasPrecios->listaBaseId();
            if(!in_array($baseId,$enviadas,true) && isset($datos['presentacion'])){
                $precioLegado=(float)$datos['presentacion']['precioNeto'];
                $this->listasPrecios->guardarPrecioLista($id,$baseId,$costo,
                    $precioLegado,null,true,false);
            }
            if(in_array($baseId,$enviadas,true)){
                $precioBase=DB::table('listas_precios_detalle')
                    ->where('producto_id',$id)->where('lista_precio_id',$baseId)
                    ->value('precio_venta');
                DB::table('presentaciones_productos')->where('producto_id',$id)
                    ->orderBy('id')->limit(1)->update(['precio_neto'=>$precioBase]);
            }
            // Recalcular márgenes de todas las listas, incluidas las no editadas,
            // y rechazar costos que incumplen el mínimo de su línea.
            $this->listasPrecios->sincronizarCostoProducto($id);
            $producto=DB::table('productos')->where('id',$id)->first();
            $principal=DB::table('presentaciones_productos')->where('producto_id',$id)->orderBy('id')->first();
            $res=$this->resultado($producto,$principal,true);
            $res['codigosVenta']=$this->codigosVenta($id);
            return $res;
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
