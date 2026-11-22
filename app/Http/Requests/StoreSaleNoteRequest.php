<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreSaleNoteRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()!==null; }
    public function rules(): array
    {
        return [
            'number'=>['required','string','max:50'],
            'customer_id'=>['required','integer','exists:customers,id'],
            'delivery_route_id'=>['nullable','integer','exists:delivery_routes,id'],
            'delivery_at'=>['nullable','date'],
            'delivery_address'=>['nullable','string','max:255'],
            'payment_method'=>['nullable','string','max:40'],
            'notes'=>['nullable','string','max:2000'],
            'items'=>['required','array','min:1','max:100'],
            'items.*.presentation_id'=>['required','integer','exists:product_presentations,id','distinct'],
            'items.*.quantity'=>['required','numeric','gt:0']
        ];
    }
}