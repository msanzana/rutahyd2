<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Sale;
use Illuminate\Validation\ValidationException;
/**
 * Construye exclusivamente el JSON esperado por el controlador DTE inspeccionado
 * en msanzana/sii-dte-backend (rama develop). NO realiza emisión ni reserva folios.
 * company_id se resuelve en el servidor desde JWT, no desde este payload.
 */
final class SaleDtePayloadService
{
    public function build(Sale $sale): array
    {
        $sale->loadMissing('details');
        if ($sale->details->isEmpty() || $sale->details->count() > 60) {
            throw ValidationException::withMessages(['items'=>'El contrato requiere entre 1 y 60 líneas']);
        }
        if (in_array((int)$sale->dte_type,[39,41],true)
            && !in_array($sale->header_payload['ind_servicio'] ?? null,[1,2,3,4],true)) {
            throw ValidationException::withMessages(['header_payload.ind_servicio'=>'Requerido para boletas 39/41']);
        }
        if ((int)$sale->dte_type===33 && !$sale->receiver_address) {
            throw ValidationException::withMessages(['receiver.address'=>'Dirección requerida para factura 33']);
        }
        if ($sale->dte_external_system_id !== null && $sale->dte_proposed_folio === null) {
            throw ValidationException::withMessages(['proposed_folio'=>'Folio requerido con sistema externo']);
        }
        $items=$sale->details->sortBy('line_number')->values()->map(function($line) {
            return [
                'item_code_type'=>$line->item_code_type,
                'item_code'=>$line->item_code,
                'name'=>$line->name,
                'description'=>$line->description,
                'quantity'=>(float)$line->quantity,
                'unit_price'=>(float)$line->unit_price,
                'discount_percent'=>(float)$line->discount_percent,
                'discount_amount'=>(float)$line->discount_amount,
                'tax_exempt'=>(bool)$line->tax_exempt,
                'extra_payload'=>$line->extra_payload,
            ];
        })->all();
        $payload=[
            'external_id'=>$sale->dte_external_id,
            'external_system_id'=>$sale->dte_external_system_id,
            'proposed_folio'=>$sale->dte_proposed_folio,
            'proposed_sii_document_type'=>$sale->dte_proposed_sii_document_type,
            'branch_office_number'=>$sale->dte_branch_office_number,
            'facility_number'=>$sale->dte_facility_number,
            'external_branch_code'=>$sale->dte_external_branch_code,
            'dte_type'=>(int)$sale->dte_type,
            'issue_date'=>$sale->issue_date->format('Y-m-d'),
            'receiver'=>[
                'document'=>$sale->receiver_document,'name'=>$sale->receiver_name,
                'giro'=>$sale->receiver_giro,'address'=>$sale->receiver_address,
                'city_id'=>$sale->receiver_city_id,'email'=>$sale->receiver_email
            ],
            'items'=>$items,
            'references'=>$sale->references_payload ?? [],
            'header_payload'=>$sale->header_payload ?? [],
        ];
        // Omite NULLs: la petición no debe contener propiedades ausentes como null.
        return array_filter($payload,fn($value)=>$value!==null);
    }
}
