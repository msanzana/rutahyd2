<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class GuardarNotaVentaSolicitud extends FormRequest
{
    public function authorize(): bool { return $this->user()!==null; }
    public function rules(): array
    {
        return [
            'numero'=>['nullable','string','max:50'],
            'cliente_id'=>['required','integer','exists:clientes,id'],
            'ruta_reparto_id'=>['nullable','integer','exists:rutas_reparto,id'],
            'entrega_programada_en'=>['nullable','date'],
            'direccion_entrega'=>['nullable','string','max:255'],
            'observaciones'=>['nullable','string','max:3000'],
            'detalles'=>['required','array','min:1','max:100'],
            'detalles.*.presentacion_id'=>['required','integer','exists:presentaciones_productos,id'],
            'detalles.*.cantidad_solicitada'=>['required','numeric','gt:0'],
        ];
    }
}
