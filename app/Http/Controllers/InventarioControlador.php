<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use Illuminate\Http\Request;

/** Ruta histórica conservada para informar al cliente sin escribir inventario legado. */
final class InventarioControlador extends Controller
{
    public function trasladar(Request $solicitud)
    {
        abort(409,'El traslado anterior está deshabilitado. Utilice el módulo Bodega / Traspasos cuando esté habilitado.');
    }
}
