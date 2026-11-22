<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GestionListasPrecios;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Mantenedor de listas: acceso protegido por JWT y permisos de productos.
 * Un producto figura a lo sumo una vez por lista; BASE nunca se elimina.
 */
final class ListasPreciosControlador extends Controller
{
    public function __construct(private GestionListasPrecios $precios) {}

    private function admin(Request $request): void
    {
        $user=$request->user();
        abort_unless($user&&$user->activo,401);
        if($user->rol?->codigo==='administrador')return;
        abort_unless(DB::table('roles_permisos')->join('permisos','permisos.id','=','roles_permisos.permiso_id')
            ->where('roles_permisos.rol_id',$user->rol_id)
            ->where('permisos.codigo','productos.gestionar')->exists(),403,
            'Tu perfil no puede mantener listas de precios');
    }
    public function listar(Request $request)
    {
        $this->admin($request);
        $data=$request->validate([
            'buscar'=>['sometimes','nullable','string','max:120'],
            'activo'=>['sometimes','boolean'],
            'por_pagina'=>['sometimes','integer','min:1','max:100']
        ]);
        $q=DB::table('listas_precios');
        if(isset($data['activo']))$q->where('activo',(int)$data['activo']);
        if($buscar=trim((string)($data['buscar']??'')))$q->where(fn($b)=>
            $b->where('nombre','like','%'.$buscar.'%')->orWhere('codigo','like','%'.$buscar.'%'));
        return response()->json($q->orderByDesc('es_base')->orderBy('nombre')
            ->paginate((int)($data['por_pagina']??100)))->header('Cache-Control','no-store');
    }
    public function crear(Request $request)
    {
        $this->admin($request);
        $data=$request->validate([
            'codigo'=>['required','string','max:40','regex:/^[A-Za-z0-9_-]+$/'],
            'nombre'=>['required','string','max:120'],
            'observacion'=>['nullable','string','max:255'],
            'activo'=>['sometimes','boolean']
        ]);
        try{
            $id=$this->precios->crearLista($data['codigo'],$data['nombre']);
            if(isset($data['observacion'])||isset($data['activo']))
                $this->precios->actualizarLista($id, array_intersect_key($data,
                    array_flip(['observacion','activo'])));
            return response()->json(DB::table('listas_precios')->where('id',$id)->first(),201);
        }catch(QueryException $e){
            if((string)$e->getCode()==='23000')abort(409,'Código o lista duplicada');
            throw $e;
        }
    }
    public function actualizar(Request $request,int $id)
    {
        $this->admin($request);
        $data=Validator::make($request->all(),[
            'codigo'=>['sometimes','required','string','max:40','regex:/^[A-Za-z0-9_-]+$/'],
            'nombre'=>['sometimes','required','string','max:120'],
            'observacion'=>['sometimes','nullable','string','max:255'],
            'activo'=>['sometimes','boolean'],
        ])->validate();
        abort_if(array_diff(array_keys($request->all()),array_keys($data))!==[],422,
            'Campos no permitidos');
        try{
            return response()->json($this->precios->actualizarLista($id,$data));
        }catch(QueryException $e){
            if((string)$e->getCode()==='23000')abort(409,'Código repetido');
            throw $e;
        }
    }
    public function desactivar(Request $request,int $id)
    {
        $this->admin($request);
        $actual=$this->precios->actualizarLista($id,['activo'=>false]);
        return response()->json($actual);
    }

    public function detallesProducto(Request $request,int $productoId)
    {
        $this->admin($request);
        $p=DB::table('productos')->where('id',$productoId)->first();
        abort_unless($p,404,'Producto inexistente');
        $minimo=(float)(DB::table('lineas')->where('id',$p->linea_id)
            ->value('minimo_margen_precio_venta')??0);
        $items=DB::table('listas_precios_detalle as d')
            ->join('listas_precios as l','l.id','=','d.lista_precio_id')
            ->where('d.producto_id',$productoId)
            ->orderByDesc('l.es_base')->orderBy('l.nombre')
            ->get(['d.id','d.lista_precio_id','l.nombre as lista_nombre',
                'l.codigo as lista_codigo','l.es_base','l.activo as lista_activa',
                'd.sku','d.costo','d.margen_porcentaje','d.precio_venta','d.activo']);
        return response()->json([
            'producto_id'=>$productoId,'sku'=>$p->sku,'costo_neto'=>$p->costo_neto,
            'minimo_margen_precio_venta'=>$minimo,'items'=>$items
        ])->header('Cache-Control','no-store');
    }
    public function guardarDetalle(Request $request,int $productoId,int $listaId)
    {
        $this->admin($request);
        $d=Validator::make($request->all(),[
            'costo'=>['required','numeric','min:0','max:99999999999'],
            'precio_venta'=>['sometimes','nullable','numeric','min:0','max:99999999999'],
            'margen_porcentaje'=>['sometimes','nullable','numeric','min:0','max:999999'],
            'activo'=>['sometimes','boolean']
        ])->validate();
        abort_if(array_diff(array_keys($request->all()),array_keys($d))!==[],422,
            'Campos de precio no permitidos');
        $p=isset($d['precio_venta'])?(float)$d['precio_venta']:null;
        $m=isset($d['margen_porcentaje'])?(float)$d['margen_porcentaje']:null;
        $item=$this->precios->guardarPrecioLista($productoId,$listaId,(float)$d['costo'],
            $p,$m,(bool)($d['activo']??true));
        return response()->json($item);
    }
    public function quitarDetalle(Request $request,int $productoId,int $listaId)
    {
        $this->admin($request);
        return response()->json(DB::transaction(function()use($productoId,$listaId){
            $list=DB::table('listas_precios')->where('id',$listaId)->first();
            abort_unless($list,404,'Lista inexistente');
            abort_if((bool)$list->es_base,409,'La Lista Base no puede retirarse');
            $changed=DB::table('listas_precios_detalle')
                ->where('producto_id',$productoId)->where('lista_precio_id',$listaId)
                ->update(['activo'=>0,'actualizado_en'=>now()]);
            abort_unless($changed,404,'El producto no tiene esta lista');
            return ['producto_id'=>$productoId,'lista_precio_id'=>$listaId,'activo'=>false];
        },3));
    }
}
