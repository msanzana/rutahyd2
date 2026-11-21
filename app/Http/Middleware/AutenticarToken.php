<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class AutenticarToken
{
    public function handle(Request $solicitud, Closure $siguiente): Response
    {
        $token=$solicitud->bearerToken();
        if (!is_string($token) || strlen($token)>4096 ||
            preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D',$token)!==1) {
            abort(401,'Token Bearer JWT requerido');
        }
        $secret=(string)config('rutahd.jwt_secret','');
        abort_unless(strlen($secret)>=32,503,'Autenticación no configurada');

        try {
            $claims=JWT::decode($token,new Key($secret,'HS256'));
        } catch (\Throwable) {
            abort(401,'Sesión JWT inválida o vencida');
        }
        if (
            ($claims->iss??null)!==config('rutahd.jwt_issuer') ||
            ($claims->aud??null)!==config('rutahd.jwt_audience') ||
            !isset($claims->sub,$claims->jti,$claims->exp,$claims->iat,$claims->nbf) ||
            !is_string($claims->sub) ||
            preg_match('/^[1-9][0-9]*$/D',$claims->sub)!==1 ||
            !is_string($claims->jti) ||
            preg_match('/^[a-f0-9]{64}$/D',$claims->jti)!==1 ||
            !is_int($claims->exp)
        ) {
            abort(401,'Sesión JWT inválida');
        }
        $registro=DB::table('tokens_acceso')
            ->where('hash_token',hash('sha256',$claims->jti))
            ->where('usuario_id',(int)$claims->sub)
            ->where('expira_en','>',now())->first();
        if (!$registro) abort(401,'Sesión JWT revocada o vencida');
        $usuario=Usuario::query()->find((int)$claims->sub);
        if (!$usuario || !$usuario->activo) abort(401,'Usuario inexistente o inactivo');

        $solicitud->attributes->set('id_token_acceso',$registro->id);
        $solicitud->setUserResolver(fn()=>$usuario);
        return $siguiente($solicitud);
    }
}
