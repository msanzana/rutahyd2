<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class GuardarVentaSolicitud extends FormRequest
{
    public function authorize(): bool { return $this->user()!==null; }
    public function rules(): array
    {
        return [
            'cliente_id'=>['required','integer','exists:clientes,id'],
            'numero_venta'=>['nullable','string','max:50'],
            'tipo_dte'=>['required','integer','in:33,34,39,41,46,52,56,61'],
            'fecha_emision'=>['required','date'],
            'documento_receptor'=>['required','string','max:20'],
            'nombre_receptor'=>['required','string','max:120'],
            'giro_receptor'=>['nullable','string','max:150'],
            'direccion_receptor'=>['required_if:tipo_dte,33','nullable','string','max:150'],
            'ciudad_receptor_id'=>['nullable','integer','min:1'],
            'correo_receptor'=>['nullable','email','max:150'],
            'datos_encabezado_json'=>['nullable','array'],
            'datos_referencias_json'=>['nullable','array'],
            'nota_venta_id'=>['nullable','integer','exists:notas_venta,id'],
            'observaciones'=>['nullable','string','max:3000'],
            'detalles'=>['required','array','min:1','max:60'],
            'detalles.*.producto_id'=>['required','integer','exists:productos,id'],
            'detalles.*.cantidad'=>['required','numeric','gt:0'],
            'detalles.*.precio_unitario'=>['required','numeric','min:0'],
            'detalles.*.porcentaje_descuento'=>['nullable','numeric','between:0,100'],
            'detalles.*.monto_descuento'=>['nullable','numeric','min:0'],
            'detalles.*.exento_impuestos'=>['nullable','boolean'],
            'detalles.*.datos_adicionales_json'=>['nullable','array'],
        ];
    }
}
