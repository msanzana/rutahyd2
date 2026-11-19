<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Http\Requests\TrasladarInventarioSolicitud;
use App\Services\ServicioTrasladoInventario;
final class InventarioControlador extends Controller
{
    public function __construct(private ServicioTrasladoInventario $servicio) {}
    public function trasladar(TrasladarInventarioSolicitud $solicitud)
    {
        $id=$this->servicio->trasladar($solicitud->validated(),(int)$solicitud->user()->id);
        return response()->json(['guia_despacho_id'=>$id],201);
    }
}
