<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
/**
 * Adaptador ES -> JSON del contrato msanzana/sii-dte-backend/develop.
 * NO envia DTE al SII: solo fija el payload idempotente.
 */
final class PreparadorDte
{
    public function congelar(int $ventaId): array
    {
        return DB::transaction(function() use($ventaId): array {
            $venta=Venta::query()->lockForUpdate()->findOrFail($ventaId);
            if (is_array($venta->dte_solicitud_json) && $venta->dte_solicitud_json!==[]) {
                return $venta->dte_solicitud_json;
            }
            $venta->load('detalles.producto');
            if($venta->detalles->isEmpty() || $venta->detalles->count()>60) {
                throw ValidationException::withMessages(['detalles'=>'El DTE requiere de 1 a 60 líneas']);
            }
            if(!$venta->dte_id_externo) throw ValidationException::withMessages(['dte_id_externo'=>'UUID no definido']);
            if((int)$venta->tipo_dte===33 && !$venta->direccion_receptor) {
                throw ValidationException::withMessages(['direccion_receptor'=>'Requerida para factura 33']);
            }
            if(in_array((int)$venta->tipo_dte,[39,41],true)
                && !in_array($venta->datos_encabezado_json['ind_servicio']??null,[1,2,3,4],true)) {
                throw ValidationException::withMessages(['datos_encabezado_json.ind_servicio'=>'Requerido en boletas 39/41']);
            }
            $lineas=$venta->detalles->sortBy('numero_linea')->values()->map(function($detalle): array {
                $producto=$detalle->producto;
                if(!$producto)throw ValidationException::withMessages(['producto_id'=>'Producto no encontrado']);
                if(mb_strlen($producto->nombre)>120)throw ValidationException::withMessages(['producto_id'=>'Nombre excede 120 caracteres del contrato DTE']);
                if($producto->sku!==null && mb_strlen($producto->sku)>50) {
                    throw ValidationException::withMessages(['producto_id'=>'SKU excede 50 caracteres del DTE']);
                }
                return [
                    'item_code_type'=>$producto->sku?'SKU':null,
                    'item_code'=>$producto->sku,
                    'name'=>$producto->nombre,
                    'description'=>$producto->descripcion,
                    'quantity'=>(float)$detalle->cantidad,
                    'unit_price'=>(float)$detalle->precio_unitario,
                    'discount_percent'=>(float)$detalle->porcentaje_descuento,
                    'discount_amount'=>(float)$detalle->monto_descuento,
                    'tax_exempt'=>(bool)$detalle->exento_impuestos,
                    'extra_payload'=>$detalle->datos_adicionales_json,
                ];
            })->all();
            $solicitud=[
                'external_id'=>$venta->dte_id_externo,
                'dte_type'=>(int)$venta->tipo_dte,
                'issue_date'=>$venta->fecha_emision->format('Y-m-d'),
                'receiver'=>[
                    'document'=>$venta->documento_receptor,
                    'name'=>$venta->nombre_receptor,
                    'giro'=>$venta->giro_receptor,
                    'address'=>$venta->direccion_receptor,
                    'city_id'=>$venta->ciudad_receptor_id,
                    'email'=>$venta->correo_receptor,
                ],
                'items'=>$lineas,
                'references'=>$venta->datos_referencias_json??[],
                'header_payload'=>$venta->datos_encabezado_json??[],
            ];
            $opcionales=[
                'external_system_id'=>'dte_sistema_externo_id',
                'proposed_folio'=>'dte_folio_propuesto',
                'proposed_sii_document_type'=>'dte_tipo_documento_sii_propuesto',
                'branch_office_number'=>'dte_numero_sucursal',
                'facility_number'=>'dte_numero_establecimiento',
                'external_branch_code'=>'dte_codigo_sucursal_externa',
            ];
            foreach($opcionales as $campoExterno=>$interno){
                if($venta->{$interno}!==null)$solicitud[$campoExterno]=$venta->{$interno};
            }
            if(isset($solicitud['external_system_id'])&&!isset($solicitud['proposed_folio'])) {
                throw ValidationException::withMessages(['dte_folio_propuesto'=>'Folio requerido con sistema externo']);
            }
            $venta->dte_solicitud_json=$solicitud;
            $venta->dte_estado='preparado';
            $venta->save();
            return $solicitud;
        },3);
    }
}
