<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** V109E6A. Barrera complementaria al JWT, permiso de acción y contexto de sucursal. */
final class AutorizarReservas
{
    public static function consultas(): bool {
        return config('reservas.consultas_verificadas')===true;
    }
    public static function pdf(): bool {
        return self::consultas()&&config('reservas.pdf_verificado')===true;
    }
    public static function escrituras(): bool {
        $alta=config('reservas.tipo_kardex_reservar');$baja=config('reservas.tipo_kardex_liberar');
        return self::consultas()&&config('reservas.integracion_verificada')===true
            &&config('reservas.escrituras')===true&&config('reservas.solicitudes_verificadas')===true
            &&config('reservas.clase_operacion')==='RESERVA'&&config('reservas.confirmacion_verificada')===true
            &&is_int($alta)&&$alta>0&&config('reservas.liberacion_verificada')===true&&is_int($baja)&&$baja>0
            &&config('reservas.estado_liberada')==='LIBERADA'&&config('reservas.anulacion_verificada')===true
            &&config('reservas.estado_anulada')==='ANULADA'&&config('reservas.vencimiento_verificado')===true
            &&config('reservas.aislamiento_inventario_verificado')===true;
    }
    public function handle(Request $r,Closure $next,string $modo='consulta'): Response {
        $ok=match($modo){'consulta'=>self::consultas(),'pdf'=>self::pdf(),'escritura'=>self::escrituras(),default=>false};
        abort_unless($ok,409,'Operación de reservas todavía no habilitada.');
        return $next($r);
    }
}
