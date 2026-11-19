<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Para DTE recupera el catalogo por sale_details.product_id.
 * NO utiliza product_presentations. Reintentos usan dte_request_payload,
 * el snapshot inmutable del primer intento, para evitar deriva del catalogo.
 */
final class SaleDtePayloadService
{
    public function build(Sale $sale): array
    {
        if (is_array($sale->dte_request_payload) && $sale->dte_request_payload !== []) {
            return $sale->dte_request_payload;
        }

        $sale->loadMissing('details.product');
        if ($sale->details->isEmpty() || $sale->details->count() > 60) {
            throw ValidationException::withMessages(['items' => 'El contrato exige entre 1 y 60 lineas']);
        }

        if (in_array((int) $sale->dte_type, [39, 41], true)
            && !in_array($sale->header_payload['ind_servicio'] ?? null, [1,2,3,4], true)) {
            throw ValidationException::withMessages(['header_payload.ind_servicio' => 'Requerido para boletas 39/41']);
        }
        if ((int) $sale->dte_type === 33 && !$sale->receiver_address) {
            throw ValidationException::withMessages(['receiver.address' => 'Direccion requerida para factura 33']);
        }
        if ($sale->dte_external_system_id !== null && $sale->dte_proposed_folio === null) {
            throw ValidationException::withMessages(['proposed_folio' => 'Folio requerido con sistema externo']);
        }
        if (!$sale->dte_external_id) {
            throw ValidationException::withMessages(['external_id' => 'Guardar UUID estable antes de emitir']);
        }

        $items = $sale->details->sortBy('line_number')->values()->map(function ($line): array {
            $product = $line->product;
            if (!$product) {
                throw ValidationException::withMessages(['product_id' => 'Producto inexistente para la linea '.$line->line_number]);
            }
            // Datos historicos de un esquema anterior se respaldan en extra_payload.
            $old = $line->extra_payload['legacy_product_snapshot'] ?? [];
            $name = $old['name'] ?? $product->name;
            $desc = $old['description'] ?? $product->description;
            $code = $old['item_code'] ?? $product->sku;
            $codeType = $old['item_code_type'] ?? ($code ? 'SKU' : null);
            if (trim((string) $name) === '' || mb_strlen((string) $name) > 120) {
                throw ValidationException::withMessages(['items' => 'El nombre DTE del producto debe tener entre 1 y 120 caracteres']);
            }
            if ($code !== null && mb_strlen((string) $code) > 50) {
                throw ValidationException::withMessages(['items' => 'El codigo DTE del producto supera 50 caracteres']);
            }
            return [
                'item_code_type' => $codeType,
                'item_code' => $code,
                'name' => $name,
                'description' => $desc,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'discount_percent' => (float) $line->discount_percent,
                'discount_amount' => (float) $line->discount_amount,
                'tax_exempt' => (bool) $line->tax_exempt,
                // No reenviar el respaldo interno historico al servidor DTE.
                'extra_payload' => array_diff_key($line->extra_payload ?? [], ['legacy_product_snapshot'=>true]) ?: null,
            ];
        })->all();

        return array_filter([
            'external_id' => $sale->dte_external_id,
            'external_system_id' => $sale->dte_external_system_id,
            'proposed_folio' => $sale->dte_proposed_folio,
            'proposed_sii_document_type' => $sale->dte_proposed_sii_document_type,
            'branch_office_number' => $sale->dte_branch_office_number,
            'facility_number' => $sale->dte_facility_number,
            'external_branch_code' => $sale->dte_external_branch_code,
            'dte_type' => (int) $sale->dte_type,
            'issue_date' => $sale->issue_date->format('Y-m-d'),
            'receiver' => [
                'document' => $sale->receiver_document,
                'name' => $sale->receiver_name,
                'giro' => $sale->receiver_giro,
                'address' => $sale->receiver_address,
                'city_id' => $sale->receiver_city_id,
                'email' => $sale->receiver_email,
            ],
            'items' => $items,
            'references' => $sale->references_payload ?? [],
            'header_payload' => $sale->header_payload ?? [],
        ], fn($value) => $value !== null);
    }

    public function buildAndFreeze(int $saleId): array
    {
        return DB::transaction(function () use ($saleId): array {
            $sale = Sale::query()->lockForUpdate()->findOrFail($saleId);
            $payload = $this->build($sale);
            if (!$sale->dte_request_payload) {
                $sale->dte_request_payload = $payload;
                $sale->save();
            }
            return $payload;
        }, 3);
    }
}
