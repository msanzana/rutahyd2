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
        abort_unless(DB::table('roles_permisos')
            ->join('permisos','permisos.id','=','roles_permisos.permiso_id')
            ->where('roles_permisos.rol_id',$u->rol_id)
            ->where('permisos.codigo',$codigo)->exists(),403);
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
