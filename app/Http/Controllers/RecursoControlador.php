<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Services\PermisosSubmodulos;
use Illuminate\Support\Facades\DB;
final class RecursoControlador extends Controller
{
    private const RECURSOS = [
        'roles' => [\App\Models\Rol::class,'usuarios.gestionar'],
        'permisos' => [\App\Models\Permiso::class,'usuarios.gestionar'],
        'usuarios' => [\App\Models\Usuario::class,'usuarios.gestionar'],
        'clientes' => [\App\Models\Cliente::class,'clientes.gestionar'],
        'proveedores' => [\App\Models\Proveedor::class,'compras.gestionar'],
        'categorias_productos' => [\App\Models\CategoriaProducto::class,'productos.gestionar'],
        'productos' => [\App\Models\Producto::class,'productos.gestionar'],
        'presentaciones_productos' => [\App\Models\PresentacionProducto::class,'productos.gestionar'],
        'imagenes_productos' => [\App\Models\ImagenProducto::class,'productos.gestionar'],
        'precios_clientes' => [\App\Models\PrecioCliente::class,'clientes.gestionar'],
        'bodegas' => [\App\Models\Bodega::class,'inventario.ver'],
        'movimientos_inventario' => [\App\Models\MovimientoInventario::class,'inventario.ver'],
        'promociones' => [\App\Models\Promocion::class,'ofertas.gestionar'],
        'detalles_promociones' => [\App\Models\DetallePromocion::class,'ofertas.gestionar'],
        'ordenes_compra' => [\App\Models\OrdenCompra::class,'compras.gestionar'],
        'detalles_ordenes_compra' => [\App\Models\DetalleOrdenCompra::class,'compras.gestionar'],
        'rutas_reparto' => [\App\Models\RutaReparto::class,'rutas.gestionar'],
        'cotizaciones' => [\App\Models\Cotizacion::class,'cotizaciones.gestionar'],
        'detalles_cotizaciones' => [\App\Models\DetalleCotizacion::class,'cotizaciones.gestionar'],
        'notas_venta' => [\App\Models\NotaVenta::class,'ventas.ver'],
        'detalles_notas_venta' => [\App\Models\DetalleNotaVenta::class,'ventas.ver'],
        'historial_estados_notas_venta' => [\App\Models\HistorialEstadoNotaVenta::class,'ventas.ver'],
        'pagos_ventas' => [\App\Models\PagoVenta::class,'ventas.ver'],
        'guias_despacho' => [\App\Models\GuiaDespacho::class,'inventario.ver'],
        'detalles_guias_despacho' => [\App\Models\DetalleGuiaDespacho::class,'inventario.ver'],
        'documentos_emitidos' => [\App\Models\DocumentoEmitido::class,'ventas.ver'],
        'eventos_agenda' => [\App\Models\EventoAgenda::class,'clientes.gestionar'],
        'metas_mensuales_ventas' => [\App\Models\MetaMensualVenta::class,'informes.ver'],
        'dias_laborales_programados' => [\App\Models\DiaLaboralProgramado::class,'informes.ver'],
        'registros_auditoria' => [\App\Models\RegistroAuditoria::class,'usuarios.gestionar'],
        'ventas' => [\App\Models\Venta::class,'ventas.ver'],
        'detalles_ventas' => [\App\Models\DetalleVenta::class,'ventas.ver'],
    ];

    private function obtener(Request $solicitud,string $recurso): \Illuminate\Database\Eloquent\Model
    {
        $definicion=self::RECURSOS[$recurso]??null;
        abort_unless($definicion,404,'Recurso no disponible');
        $usuario=$solicitud->user();
        abort_unless($usuario && $usuario->activo,401);
        if ($usuario->rol?->codigo !== 'administrador') {
            $autorizado=DB::table('roles_permisos')
                ->join('permisos','roles_permisos.permiso_id','=','permisos.id')
                ->where('roles_permisos.rol_id',$usuario->rol_id)
                ->where('permisos.codigo',$definicion[1])->exists();
            abort_unless($autorizado,403);
        }
        if($recurso==='proveedores') PermisosSubmodulos::exigir($usuario,'proveedores_listado','ver');
        $clase=$definicion[0];
        return new $clase();
    }

    public function tiposJuridicosClientes(Request $solicitud)
    {
        $this->obtener($solicitud,'clientes');
        return response()->json(['items'=>DB::table('tipos_juridicos_clientes')
            ->select('id','codigo','nombre','activo')
            ->orderBy('nombre')->get()])->header('Cache-Control','no-store');
    }

    private function listarClientes(Request $solicitud)
    {
        $datos=$solicitud->validate([
            'page'=>['sometimes','integer','min:1','max:10000'],
            'por_pagina'=>['sometimes','integer','min:1','max:100'],
            'buscar'=>['sometimes','nullable','string','max:120'],
            'filtros'=>['sometimes','array','max:9'],
            'filtros.rut'=>['sometimes','nullable','string','max:25'],
            'filtros.nombre'=>['sometimes','nullable','string','max:190'],
            'filtros.direccion'=>['sometimes','nullable','string','max:190'],
            'filtros.ciudad'=>['sometimes','nullable','string','max:120'],
            'filtros.correo_electronico'=>['sometimes','nullable','string','max:190'],
            'filtros.giro'=>['sometimes','nullable','string','max:120'],
            'filtros.tipo_juridico_id'=>['sometimes','nullable','integer','min:1'],
            'filtros.lista_precio_id'=>['sometimes','nullable','integer','min:1'],
            'filtros.activo'=>['sometimes','nullable','in:0,1'],
            'orden'=>['sometimes','in:id,rut,nombre,direccion,ciudad,correo_electronico,tipo_juridico_nombre,lista_precio_nombre'],
            'direccion_orden'=>['sometimes','in:asc,desc'],
        ]);
        $q=DB::table('clientes as c')
            ->leftJoin('ciudades as ci','ci.id','=','c.ciudad_id')
            ->leftJoin('tipos_juridicos_clientes as tj','tj.id','=','c.tipo_juridico_id')
            ->leftJoin('clientes_listas_precios as clp',function($join){
                $join->on('clp.cliente_id','=','c.id')->where('clp.activo','=',1);
            })
            ->leftJoin('listas_precios as lp',function($join){
                $join->on('lp.id','=','clp.lista_precio_id')->where('lp.activo','=',1);
            })
            ->select('c.*','ci.nombre as ciudad','tj.nombre as tipo_juridico_nombre',
                'lp.id as lista_precio_id','lp.nombre as lista_precio_nombre');
        $term=trim((string)($datos['buscar']??''));
        if($term!==''){
            $like='%'.$term.'%';
            $q->where(function($where)use($like){
                $where->where('c.nombre','like',$like)
                    ->orWhere('c.rut','like',$like)
                    ->orWhere('c.correo_electronico','like',$like)
                    ->orWhere('c.direccion','like',$like)
                    ->orWhere('c.giro','like',$like)
                    ->orWhere('ci.nombre','like',$like)
                    ->orWhere('tj.nombre','like',$like)
                    ->orWhere('lp.nombre','like',$like);
            });
        }
        $campos=[
            'rut'=>'c.rut','nombre'=>'c.nombre','direccion'=>'c.direccion',
            'ciudad'=>'ci.nombre','correo_electronico'=>'c.correo_electronico',
            'giro'=>'c.giro'
        ];
        foreach(($datos['filtros']??[]) as $key=>$value){
            if($value===null||$value==='')continue;
            if(isset($campos[$key]))$q->where($campos[$key],'like','%'.trim((string)$value).'%');
            elseif($key==='tipo_juridico_id')$q->where('c.tipo_juridico_id',(int)$value);
            elseif($key==='lista_precio_id')$q->where('lp.id',(int)$value);
            elseif($key==='activo')$q->where('c.activo',(int)$value);
        }
        $sortable=[
            'id'=>'c.id','rut'=>'c.rut','nombre'=>'c.nombre',
            'direccion'=>'c.direccion','ciudad'=>'ci.nombre',
            'correo_electronico'=>'c.correo_electronico',
            'tipo_juridico_nombre'=>'tj.nombre','lista_precio_nombre'=>'lp.nombre'
        ];
        $sort=$datos['orden']??'id';
        $q->orderBy($sortable[$sort],$datos['direccion_orden']??'desc');
        if($sort!=='id')$q->orderBy('c.id','desc');
        return response()->json($q->paginate((int)($datos['por_pagina']??25)))
            ->header('Cache-Control','no-store');
    }

    public function listar(Request $solicitud,string $recurso)
    {
        $modelo=$this->obtener($solicitud,$recurso);
        if($recurso==='clientes')return $this->listarClientes($solicitud);
        $datos=$solicitud->validate([
            'por_pagina'=>['sometimes','integer','min:1','max:100'],
            'filtros'=>['sometimes','array','max:5'],
            'buscar'=>['sometimes','nullable','string','max:120'],
        ]);
        $q=$modelo->newQuery();
        foreach(($datos['filtros']??[]) as $campo=>$valor) {
            abort_unless(in_array($campo,$modelo->getFillable(),true),422,'Filtro no permitido');
            abort_unless(is_scalar($valor) && strlen((string)$valor)<=190,422,'Valor de filtro inválido');
            $q->where($campo,$valor);
        }
        if (!empty($datos['buscar']) && in_array($recurso,['clientes','proveedores'],true)) {
            $term='%'.trim($datos['buscar']).'%';
            $q->where(function($where)use($term,$recurso){
                $where->where('nombre','like',$term)->orWhere('rut','like',$term)
                    ->orWhere('correo_electronico','like',$term);
                if ($recurso==='clientes') $where->orWhere('codigo','like',$term);
            });
        }
        // El contrato de estos recursos incluye id: evitar introspección restringida.
        $q->orderByDesc('id');
        $filas=$q->paginate((int)($datos['por_pagina']??25));
        if($recurso==='clientes'){
            $ids=$filas->getCollection()->pluck('id')->map(fn($id)=>(int)$id)->all();
            $listas=empty($ids)?collect():DB::table('clientes_listas_precios as clp')
                ->join('listas_precios as lp','lp.id','=','clp.lista_precio_id')
                ->whereIn('clp.cliente_id',$ids)
                ->get(['clp.cliente_id','lp.nombre'])->keyBy('cliente_id');
            $filas->getCollection()->each(function($cliente)use($listas){
                $cliente->setAttribute('lista_precio_nombre',
                    $listas->get((int)$cliente->id)?->nombre??'LISTA BASE');
            });
        }
        // Domicilio normalizado a partir de ciudad_id; no duplicar FK país/región/comuna.
        if(in_array($recurso,['clientes','proveedores'],true)){
            $ids=$filas->getCollection()->pluck('ciudad_id')->filter()->unique()->values()->all();
            $ubicaciones=empty($ids)?collect():DB::table('ciudades as ci')
                ->join('comunas as co','co.id','=','ci.comuna_id')
                ->join('provincias as pr','pr.id','=','co.provincia_id')
                ->join('regiones as re','re.id','=','pr.region_id')
                ->join('paises as pa','pa.id','=','re.pais_id')
                ->whereIn('ci.id',$ids)
                ->get(['ci.id','ci.nombre as ciudad','co.nombre as comuna_nombre',
                    'pr.nombre as provincia','re.nombre as region','pa.nombre as pais'])
                ->keyBy('id');
            $filas->getCollection()->each(function($row)use($ubicaciones){
                $l=$ubicaciones->get((int)($row->ciudad_id??0));
                foreach(['ciudad','comuna_nombre','provincia','region','pais'] as $key)
                    $row->setAttribute($key,$l?->{$key}??null);
            });
        }
        // Nunca devolver hashes de contraseñas ni cuerpos JSON sensibles.
        if ($recurso==='ventas') {
            $filas->getCollection()->makeHidden(['dte_solicitud_json','dte_respuesta_json']);
        }
        return response()->json($filas);
    }

    public function ver(Request $solicitud,string $recurso,int $id)
    {
        $modelo=$this->obtener($solicitud,$recurso);
        $registro=$modelo->newQuery()->find($id);
        abort_unless($registro,404);
        if(in_array($recurso,['clientes','proveedores'],true)&&$registro->ciudad_id){
            $place=DB::table('ciudades as ci')
                ->join('comunas as co','co.id','=','ci.comuna_id')
                ->join('provincias as pr','pr.id','=','co.provincia_id')
                ->join('regiones as re','re.id','=','pr.region_id')
                ->join('paises as pa','pa.id','=','re.pais_id')
                ->where('ci.id',$registro->ciudad_id)
                ->first(['ci.nombre as ciudad','co.nombre as comuna_nombre',
                    'pr.nombre as provincia','re.nombre as region','pa.nombre as pais']);
            if($place)foreach((array)$place as $key=>$value)$registro->setAttribute($key,$value);
        }
        if ($recurso==='ventas') $registro->makeHidden(['dte_solicitud_json','dte_respuesta_json']);
        return response()->json($registro);
    }
}
