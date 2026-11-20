<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Http\Requests\GuardarVentaSolicitud;
use App\Models\Venta;
use App\Services\ServicioVenta;
use App\Services\PreparadorDte;
final class VentaControlador extends Controller
{
    public function __construct(private ServicioVenta $servicio,private PreparadorDte $preparador) {}
    public function guardar(GuardarVentaSolicitud $solicitud)
    {
        $venta=$this->servicio->crear($solicitud->validated(),(int)$solicitud->user()->id);
        return response()->json(['id'=>$venta->id,'numero_venta'=>$venta->numero_venta],201);
    }
    public function detalles(int $id)
    {
        $venta=Venta::query()->with(['detalles.producto','cliente'])->find($id);
        abort_unless($venta,404);
        return response()->json($venta->makeHidden(['dte_solicitud_json','dte_respuesta_json']));
    }
    public function prepararDte(int $id)
    {
        return response()->json([
            'advertencia'=>'Payload preparado, aun no enviado al Servidor DTE ni al SII',
            'datos'=>$this->preparador->congelar($id),
        ]);
    }
}
