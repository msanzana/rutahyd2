<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GestionListasPrecios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ListasPreciosClientesControlador extends Controller
{
    public function __construct(private GestionListasPrecios $precios) {}
    private function permiso(Request $request,string $codigo): void
    {
        $u=$request->user();abort_unless($u&&$u->activo,401);
        if($u->rol?->codigo==='administrador')return;
        $codes=$codigo==='ventas.ver'?['ventas.ver','ventas.gestionar']:[$codigo];
        abort_unless(DB::table('roles_permisos')
            ->join('permisos','permisos.id','=','roles_permisos.permiso_id')
            ->where('roles_permisos.rol_id',$u->rol_id)
            ->whereIn('permisos.codigo',$codes)->exists(),403);
    }
    public function listaCliente(Request $request,int $clienteId)
    {
        $this->permiso($request,'clientes.gestionar');
        abort_unless(DB::table('clientes')->where('id',$clienteId)->exists(),404,'Cliente inexistente');
        $lista=DB::table('clientes_listas_precios as a')
            ->join('listas_precios as l','l.id','=','a.lista_precio_id')
            ->where('a.cliente_id',$clienteId)
            ->first(['l.id','l.codigo','l.nombre','l.es_base','l.activo']);
        return response()->json(['cliente_id'=>$clienteId,'lista'=>$lista])
            ->header('Cache-Control','no-store');
    }
    public function asignar(Request $request,int $clienteId)
    {
        $this->permiso($request,'clientes.gestionar');
        $data=$request->validate([
            'lista_precio_id'=>['required','integer','min:1','exists:listas_precios,id']
        ]);
        $this->precios->asignarListaCliente($clienteId,(int)$data['lista_precio_id']);
        return $this->listaCliente($request,$clienteId);
    }
    /**
     * Catálogos seguros para vendedores: sólo clientes activos y productos
     * con un precio vigente en la lista EXCLUSIVA del cliente.
     * No conceden acceso al mantenedor de Clientes o Productos.
     */
    public function clientesVenta(Request $request)
    {
        $this->permiso($request,'ventas.ver');
        $data=$request->validate([
            'buscar'=>['sometimes','nullable','string','max:120'],
            'page'=>['sometimes','integer','min:1','max:10000']
        ]);
        $q=DB::table('clientes')->where('activo',1);
        $buscar=trim((string)($data['buscar']??''));
        if($buscar!=='')$q->where(function($query)use($buscar){
            $query->where('nombre','like','%'.$buscar.'%')
                ->orWhere('codigo','like','%'.$buscar.'%')
                ->orWhere('rut','like','%'.$buscar.'%');
        });
        return response()->json($q->select('id','codigo','nombre','rut','direccion','activo')
            ->orderBy('nombre')->paginate(100))->header('Cache-Control','no-store');
    }

    public function productosVenta(Request $request)
    {
        $this->permiso($request,'ventas.ver');
        $data=$request->validate([
            'cliente_id'=>['required','integer','min:1','exists:clientes,id'],
            'buscar'=>['sometimes','nullable','string','max:120'],
            'page'=>['sometimes','integer','min:1','max:10000']
        ]);
        $cliente=(int)$data['cliente_id'];
        abort_unless(DB::table('clientes')->where('id',$cliente)->where('activo',1)->exists(),
            422,'Cliente inactivo');
        $q=DB::table('clientes_listas_precios as clp')
            ->join('listas_precios as lp','lp.id','=','clp.lista_precio_id')
            ->join('listas_precios_detalle as d','d.lista_precio_id','=','lp.id')
            ->join('productos as p','p.id','=','d.producto_id')
            ->where('clp.cliente_id',$cliente)
            ->where('clp.activo',1)->where('lp.activo',1)
            ->where('d.activo',1)->where('p.activo',1);
        $buscar=trim((string)($data['buscar']??''));
        if($buscar!=='')$q->where(function($sub)use($buscar){
            $sub->where('p.nombre','like','%'.$buscar.'%')
                ->orWhere('p.sku','like','%'.$buscar.'%');
        });
        $page=$q->orderBy('p.nombre')->select(
            'p.id','p.sku','p.nombre','p.activo',
            'lp.nombre as lista_precio_nombre',
            DB::raw('(SELECT pp.tasa_iva FROM presentaciones_productos pp
                WHERE pp.producto_id = p.id ORDER BY pp.id LIMIT 1) as tasa_iva')
        )->paginate(100);
        return response()->json($page)->header('Cache-Control','no-store');
    }

    public function cotizar(Request $request)
    {
        $this->permiso($request,'ventas.ver');
        $d=$request->validate([
            'cliente_id'=>['required','integer','min:1','exists:clientes,id'],
            'producto_id'=>['required','integer','min:1','exists:productos,id']
        ]);
        $precio=$this->precios->precioEfectivoCliente(
            (int)$d['cliente_id'],(int)$d['producto_id']
        );
        // No publicar el costo interno en el flujo de ventas.
        return response()->json([
            'cliente_id'=>(int)$d['cliente_id'],'producto_id'=>(int)$d['producto_id'],
            'lista_precio_id'=>(int)$precio->lista_precio_id,
            'lista_precio_nombre'=>$precio->lista_precio_nombre,
            'precio_venta'=>$precio->precio_venta
        ])->header('Cache-Control','no-store');
    }
}
