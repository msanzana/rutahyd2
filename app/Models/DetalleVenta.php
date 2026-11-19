<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DetalleVenta extends Model
{
    protected $table = 'detalles_ventas';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'venta_id',
        'detalle_nota_venta_id',
        'producto_id',
        'numero_linea',
        'cantidad',
        'precio_unitario',
        'porcentaje_descuento',
        'monto_descuento',
        'exento_impuestos',
        'monto_linea',
        'datos_adicionales_json',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'porcentaje_descuento' => 'decimal:4',
            'monto_descuento' => 'decimal:2',
            'exento_impuestos' => 'boolean',
            'monto_linea' => 'decimal:2',
            'datos_adicionales_json' => 'array',
            'creado_en' => 'date',
            'actualizado_en' => 'date',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function detalleNotaVenta(): BelongsTo
    {
        return $this->belongsTo(DetalleNotaVenta::class, 'detalle_nota_venta_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
