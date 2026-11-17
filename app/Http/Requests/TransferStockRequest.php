<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class TransferStockRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()!==null; }
    public function rules(): array
    {
        return [
            'internal_folio'=>['required','string','max:60'],
            'from_warehouse_id'=>['required','integer','exists:warehouses,id','different:to_warehouse_id'],
            'to_warehouse_id'=>['required','integer','exists:warehouses,id'],
            'sale_note_id'=>['nullable','integer','exists:sale_notes,id'],
            'items'=>['required','array','min:1','max:100'],
            'items.*.presentation_id'=>['required','integer','exists:product_presentations,id','distinct'],
            'items.*.quantity'=>['required','numeric','gt:0']
        ];
    }
}