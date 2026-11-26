<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Services\ContextoCompras;
use App\Services\HabilitacionTraspasos;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class AutorizarTraspasoInterno
{
    public function handle(Request $r,Closure $next): Response {
        $sid=(int)app(ContextoCompras::class)->resolver($r)->id;
        $gate=new HabilitacionTraspasos();
        abort_unless($gate->pares($sid)!==[],409,'Las operaciones de traspaso aún no están habilitadas para esta sucursal.');
        if($r->route('id')!==null){
            $t=DB::table('inventario_traspasos')->where('id',(int)$r->route('id'))->where('sucursal_origen_id',$sid)->where('sucursal_destino_id',$sid)->first();
            abort_unless($t,404);
            abort_unless($t->guia_despacho_id===null&&$gate->permite($sid,(int)$t->bodega_origen_id,(int)$t->bodega_destino_id),409,'Bodegas no verificadas para movimiento interno sin guía.');
        }else{
            $v=$r->validate(['bodega_origen_id'=>'required|integer|min:1','bodega_destino_id'=>'required|integer|min:1|different:bodega_origen_id']);
            abort_unless($gate->permite($sid,(int)$v['bodega_origen_id'],(int)$v['bodega_destino_id']),409,'Bodegas no verificadas para movimiento interno sin guía.');
        }
        return $next($r);
    }
}
