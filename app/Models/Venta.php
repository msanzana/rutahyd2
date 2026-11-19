<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Venta extends Model
{
    protected $table = 'ventas';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'nota_venta_id',
        'cliente_id',
        'numero_venta',
        'estado',
        'codigo_moneda',
        'fecha_emision',
        'tipo_dte',
        'dte_id_externo',
        'dte_empresa_id',
        'dte_sistema_externo_id',
        'dte_folio_propuesto',
        'dte_tipo_documento_sii_propuesto',
        'dte_numero_sucursal',
        'dte_numero_establecimiento',
        'dte_codigo_sucursal_externa',
        'dte_documento_remoto_id',
        'dte_folio',
        'dte_estado',
        'dte_ambiente_sii',
        'documento_receptor',
        'nombre_receptor',
        'giro_receptor',
        'direccion_receptor',
        'ciudad_receptor_id',
        'correo_receptor',
        'monto_neto',
        'monto_exento',
        'monto_impuestos',
        'monto_descuento',
        'monto_total',
        'datos_encabezado_json',
        'datos_referencias_json',
        'dte_solicitud_json',
        'dte_respuesta_json',
        'dte_codigo_ultimo_error',
        'dte_mensaje_ultimo_error',
        'dte_solicitado_en',
        'dte_creado_en',
        'dte_aceptado_en',
        'dte_rechazado_en',
        'observaciones',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'monto_neto' => 'decimal:2',
            'monto_exento' => 'decimal:2',
            'monto_impuestos' => 'decimal:2',
            'monto_descuento' => 'decimal:2',
            'monto_total' => 'decimal:2',
            'datos_encabezado_json' => 'array',
            'datos_referencias_json' => 'array',
            'dte_solicitud_json' => 'array',
            'dte_respuesta_json' => 'array',
            'dte_solicitado_en' => 'date',
            'dte_creado_en' => 'date',
            'dte_aceptado_en' => 'date',
            'dte_rechazado_en' => 'date',
            'creado_en' => 'date',
            'actualizado_en' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function notaVenta(): BelongsTo
    {
        return $this->belongsTo(NotaVenta::class, 'nota_venta_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'venta_id');
    }
}
