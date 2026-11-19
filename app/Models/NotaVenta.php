<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NotaVenta extends Model
{
    protected $table = 'notas_venta';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'numero',
        'cliente_id',
        'cotizacion_id',
        'usuario_vendedor_id',
        'ruta_reparto_id',
        'entrega_programada_en',
        'direccion_entrega',
        'estado',
        'estado_pago',
        'medio_pago',
        'subtotal_neto',
        'total_descuentos',
        'total_impuestos',
        'total_bruto',
        'monto_entregado_bruto',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'entrega_programada_en' => 'date',
            'subtotal_neto' => 'decimal:2',
            'total_descuentos' => 'decimal:2',
            'total_impuestos' => 'decimal:2',
            'total_bruto' => 'decimal:2',
            'monto_entregado_bruto' => 'decimal:2',
            'creado_en' => 'date',
            'actualizado_en' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function usuarioVendedor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_vendedor_id');
    }

    public function rutaReparto(): BelongsTo
    {
        return $this->belongsTo(RutaReparto::class, 'ruta_reparto_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleNotaVenta::class, 'nota_venta_id');
    }
}
