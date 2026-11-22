<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\PermisosSubmodulos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AutorizarSubmodulo
{
    public function handle(Request $request, Closure $next, string $submodulo, string $accion='ver'): Response
    {
        $usuario=$request->user();
        abort_unless($usuario&&$usuario->activo,401,'Sesión requerida');
        PermisosSubmodulos::exigir($usuario,$submodulo,$accion);
        return $next($request);
    }
}
