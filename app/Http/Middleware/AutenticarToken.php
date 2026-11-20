<?php
declare(strict_types=1);
namespace App\Http\Middleware;
use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
final class AutenticarToken
{
    public function handle(Request $solicitud,Closure $siguiente): Response
    {
        $token=$solicitud->bearerToken();
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/i',$token)) {
            abort(401,'Token de acceso requerido');
        }
        $registro=DB::table('tokens_acceso')->where('hash_token',hash('sha256',$token))
            ->where('expira_en','>',now())->first();
        if (!$registro) abort(401,'Sesión vencida o inválida');
        $usuario=Usuario::query()->find($registro->usuario_id);
        if (!$usuario || !$usuario->activo) abort(401,'Usuario inactivo o inexistente');
        $solicitud->attributes->set('id_token_acceso',$registro->id);
        $solicitud->setUserResolver(fn()=>$usuario);
        return $siguiente($solicitud);
    }
}
