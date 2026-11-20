<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Http\Requests\GuardarNotaVentaSolicitud;
use App\Services\ServicioNotaVenta;
use Illuminate\Http\Request;
final class NotaVentaControlador extends Controller
{
    public function __construct(private ServicioNotaVenta $servicio) {}
    public function guardar(GuardarNotaVentaSolicitud $solicitud)
    {
        $id=$this->servicio->crear($solicitud->validated(),(int)$solicitud->user()->id);
        return response()->json(['id'=>$id],201);
    }
    public function cambiarEstado(Request $solicitud,int $id)
    {
        $d=$solicitud->validate(['estado'=>['required','in:por_preparar,lista_despacho,pendiente_entrega,entregada,completada,rechazada']]);
        $this->servicio->cambiarEstado($id,$d['estado'],(int)$solicitud->user()->id);
        return response()->noContent();
    }
    public function abonar(Request $solicitud,int $id)
    {
        $d=$solicitud->validate(['monto_bruto'=>['required','numeric','gt:0'],'medio_pago'=>['required','string','max:40'],'referencia'=>['nullable','string','max:120']]);
        return response()->json(['id'=>$this->servicio->abonar($id,$d,(int)$solicitud->user()->id)],201);
    }
}
