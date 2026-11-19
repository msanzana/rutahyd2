<?php
declare(strict_types=1);
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
final class AutorizarPermiso
{
    public function handle(Request $solicitud, Closure $siguiente, string $codigo): Response
    {
        $usuario=$solicitud->user();
        if (!$usuario || !$usuario->activo) abort(403,'Acceso denegado');
        if ($usuario->rol?->codigo === 'administrador') return $siguiente($solicitud);
        $autorizado=DB::table('roles_permisos')
            ->join('permisos','roles_permisos.permiso_id','=','permisos.id')
            ->where('roles_permisos.rol_id',$usuario->rol_id)
            ->where('permisos.codigo',$codigo)->exists();
        abort_unless($autorizado,403,'No tiene permiso');
        return $siguiente($solicitud);
    }
}
