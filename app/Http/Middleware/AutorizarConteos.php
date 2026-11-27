<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** V109D6B. Barrera adicional a JWT, permisos y contexto transaccional. */
final class AutorizarConteos
{
    public static function habilitado(): bool {
        return config('conteos.escrituras')===true
            &&config('conteos.alcance_selectivo_verificado')===true
            &&config('conteos.conteo_ciego_verificado')===true
            &&config('conteos.estado_confirmado')==='CONFIRMADO'
            &&config('conteos.estado_anulado')==='ANULADO';
    }
    public function handle(Request $r,Closure $next): Response {
        abort_unless(self::habilitado(),409,'Las escrituras de conteos todavía no están habilitadas.');
        return $next($r);
    }
}
