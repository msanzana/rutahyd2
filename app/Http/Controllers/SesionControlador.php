<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\PermisosCatalogos;
use App\Services\PermisosSubmodulos;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SesionControlador extends Controller
{
    public function iniciar(Request $solicitud)
    {
        $datos=$solicitud->validate([
            'correo_electronico'=>['required','email','max:190'],
            'contrasena'=>['required','string'],
        ]);
        $usuario=Usuario::query()
            ->where('correo_electronico',$datos['correo_electronico'])->first();
        if (!$usuario || !$usuario->activo ||
            !Hash::check($datos['contrasena'],$usuario->hash_contrasena)) {
            throw ValidationException::withMessages([
                'correo_electronico'=>'Credenciales incorrectas'
            ]);
        }
        $secret=(string)config('rutahd.jwt_secret','');
        abort_unless(strlen($secret)>=32, 503, 'Autenticación no configurada');

        $ahora=time();
        $expira=$ahora+(int)config('rutahd.jwt_ttl_seconds',3600);
        $jti=bin2hex(random_bytes(32));
        $claims=[
            'iss'=>(string)config('rutahd.jwt_issuer'),
            'aud'=>(string)config('rutahd.jwt_audience'),
            'sub'=>(string)$usuario->id,
            'iat'=>$ahora,
            'nbf'=>$ahora,
            'exp'=>$expira,
            'jti'=>$jti,
        ];
        $jwt=JWT::encode($claims,$secret,'HS256');
        $vence=now()->addSeconds($expira-$ahora);
        DB::transaction(function () use ($usuario,$jti,$vence): void {
            DB::table('tokens_acceso')->insert([
                'usuario_id'=>$usuario->id,
                // Revocación: solo se guarda SHA-256 del identificador, jamás el JWT.
                'hash_token'=>hash('sha256',$jti),
                'nombre'=>'sesion_jwt',
                'expira_en'=>$vence,
                'creado_en'=>now(),
            ]);
            $usuario->ultimo_acceso_en=now();
            $usuario->save();
        });
        return response()->json([
            'token'=>$jwt,
            'token_type'=>'Bearer',
            'expires_in'=>$expira-$ahora,
            'vence_en'=>$vence->toIso8601String(),
            'usuario'=>[
                'id'=>$usuario->id,
                'nombre'=>$usuario->nombre,
                'rol'=>$usuario->rol?->codigo,
                'permisos'=>PermisosCatalogos::concedidos($usuario),
                'submodulos'=>PermisosSubmodulos::permitidos($usuario),
                'tema_interfaz'=>DB::table('preferencias_usuarios')
                    ->where('usuario_id',$usuario->id)->value('tema_interfaz')??'claro',
            ],
        ])->header('Cache-Control','no-store');
    }

    public function salir(Request $solicitud)
    {
        DB::table('tokens_acceso')
            ->where('id',(int)$solicitud->attributes->get('id_token_acceso'))
            ->delete();
        return response()->noContent();
    }

    public function perfil(Request $solicitud)
    {
        $u=$solicitud->user();
        return response()->json([
            'id'=>$u->id,
            'nombre'=>$u->nombre,
            'correo_electronico'=>$u->correo_electronico,
            'rol'=>$u->rol?->codigo,
            'permisos'=>PermisosCatalogos::concedidos($u),
            'submodulos'=>PermisosSubmodulos::permitidos($u),
            'tema_interfaz'=>DB::table('preferencias_usuarios')
                ->where('usuario_id',$u->id)->value('tema_interfaz')??'claro',
        ])->header('Cache-Control','no-store');
    }
}
