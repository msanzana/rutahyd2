<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class PreferenciasUsuarioControlador extends Controller
{
    public function ver(Request $request)
    {
        $usuario=$request->user();abort_unless($usuario&&$usuario->activo,401);
        $tema=DB::table('preferencias_usuarios')
            ->where('usuario_id',$usuario->id)->value('tema_interfaz') ?? 'claro';
        return response()->json(['tema_interfaz'=>$tema])->header('Cache-Control','no-store');
    }

    public function guardar(Request $request)
    {
        $usuario=$request->user();abort_unless($usuario&&$usuario->activo,401);
        $datos=$request->validate([
            'tema_interfaz'=>['required','string',Rule::in(['claro','oscuro'])]
        ]);
        DB::table('preferencias_usuarios')->updateOrInsert(
            ['usuario_id'=>$usuario->id],
            ['tema_interfaz'=>$datos['tema_interfaz'],'actualizado_en'=>now()]
        );
        return response()->json(['tema_interfaz'=>$datos['tema_interfaz']])
            ->header('Cache-Control','no-store');
    }
}
