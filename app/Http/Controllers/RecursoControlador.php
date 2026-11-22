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

    public function listar(Request $solicitud,string $recurso)
    {
        $modelo=$this->obtener($solicitud,$recurso);
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
        $columnas=DB::getSchemaBuilder()->getColumnListing($modelo->getTable());
        if (in_array('id',$columnas,true)) $q->orderByDesc('id');
        $filas=$q->paginate((int)($datos['por_pagina']??25));
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
        if ($recurso==='ventas') $registro->makeHidden(['dte_solicitud_json','dte_respuesta_json']);
        return response()->json($registro);
    }
}
