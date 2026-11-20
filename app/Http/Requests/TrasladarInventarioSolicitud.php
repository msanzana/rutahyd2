<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class TrasladarInventarioSolicitud extends FormRequest
{
    public function authorize(): bool { return $this->user()!==null; }
    public function rules(): array
    {
        return [
            'folio_interno'=>['required','string','max:60'],
            'bodega_origen_id'=>['required','integer','exists:bodegas,id','different:bodega_destino_id'],
            'bodega_destino_id'=>['required','integer','exists:bodegas,id'],
            'nota_venta_id'=>['nullable','integer','exists:notas_venta,id'],
            'detalles'=>['required','array','min:1','max:100'],
            'detalles.*.presentacion_id'=>['required','integer','exists:presentaciones_productos,id','distinct'],
            'detalles.*.cantidad'=>['required','numeric','gt:0'],
        ];
    }
}
