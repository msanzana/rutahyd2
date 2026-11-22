<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Catálogo oficial público SII en servidor, pero protegido por JWT y permisos. */
final class GirosSiiControlador extends Controller
{
    public function listar(Request $request)
    {
        $user=$request->user();
        abort_unless($user&&$user->activo,401);
        if($user->rol?->codigo!=='administrador'){
            $authorized=DB::table('roles_permisos as rp')
                ->join('permisos as p','p.id','=','rp.permiso_id')
                ->where('rp.rol_id',$user->rol_id)
                ->whereIn('p.codigo',['compras.gestionar','clientes.gestionar',
                    'ventas.gestionar'])->exists();
            abort_unless($authorized,403,'El perfil no puede consultar giros SII');
        }
        $v=$request->validate([
            'buscar'=>['sometimes','nullable','string','max:120'],
            'page'=>['sometimes','integer','min:1','max:10000'],
            'por_pagina'=>['sometimes','integer','min:1','max:100'],
            'codigo'=>['sometimes','nullable','regex:/^[0-9]{6}$/D'],
        ]);
        $query=DB::table('giros_sii')->where('activo',1);
        if(isset($v['codigo']))$query->where('codigo',$v['codigo']);
        if(!empty($v['buscar'])){
            $term=trim($v['buscar']);
            if($term!==''){
                $like='%'.$term.'%';
                // codigo es CHAR(6) ascii_bin y descripcion es utf8mb4:
                // comparar texto acentuado contra codigo provoca SQLSTATE HY000/1267
                // (mezcla de collations) en MariaDB. Filtrar texto sólo por
                // descripción; aceptar búsquedas por código únicamente con dígitos.
                if(preg_match('/^[0-9]{1,6}$/D',$term)===1){
                    $query->where(static fn($q)=>$q->where('codigo','like',$like)
                        ->orWhere('descripcion','like',$like));
                }else{
                    $query->where('descripcion','like',$like);
                }
            }
        }
        $page=$query->select(['codigo','descripcion','afecta_iva',
            'categoria_tributaria','disponible_internet','rubro_sii'])
            ->orderBy('codigo')
            ->paginate((int)($v['por_pagina']??25));
        return response()->json($page)->header('Cache-Control','no-store');
    }
}
