<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
final class SesionControlador extends Controller
{
    public function iniciar(Request $solicitud)
    {
        $datos=$solicitud->validate([
            'correo_electronico'=>['required','email','max:190'],
            'contrasena'=>['required','string'],
        ]);
        $usuario=Usuario::query()->where('correo_electronico',$datos['correo_electronico'])->first();
        if (!$usuario || !$usuario->activo || !Hash::check($datos['contrasena'],$usuario->hash_contrasena)) {
            throw ValidationException::withMessages(['correo_electronico'=>'Credenciales incorrectas']);
        }
        $token=bin2hex(random_bytes(32));
        $vence=now()->addHours(12);
        DB::transaction(function() use ($usuario,$token,$vence): void {
            DB::table('tokens_acceso')->insert([
                'usuario_id'=>$usuario->id,
                'hash_token'=>hash('sha256',$token),
                'nombre'=>'sesion_api',
                'expira_en'=>$vence,
                'creado_en'=>now(),
            ]);
            $usuario->ultimo_acceso_en=now();
            $usuario->save();
        });
        return response()->json([
            'token'=>$token,'vence_en'=>$vence->toIso8601String(),
            'usuario'=>['id'=>$usuario->id,'nombre'=>$usuario->nombre,'rol'=>$usuario->rol?->codigo],
        ]);
    }
    public function salir(Request $solicitud)
    {
        DB::table('tokens_acceso')->where('id',(int)$solicitud->attributes->get('id_token_acceso'))->delete();
        return response()->noContent();
    }
    public function perfil(Request $solicitud)
    {
        $u=$solicitud->user();
        return response()->json(['id'=>$u->id,'nombre'=>$u->nombre,
            'correo_electronico'=>$u->correo_electronico,'rol'=>$u->rol?->codigo]);
    }
}
