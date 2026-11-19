<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DetalleNotaVenta extends Model
{
    protected $table = 'detalles_notas_venta';
    public $timestamps = false;

    protected $fillable = [
        'nota_venta_id',
        'presentacion_id',
        'cantidad_solicitada',
        'cantidad_entregada',
        'precio_unitario_neto',
        'porcentaje_descuento',
        'subtotal_neto',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_solicitada' => 'decimal:3',
            'cantidad_entregada' => 'decimal:3',
            'precio_unitario_neto' => 'decimal:4',
            'porcentaje_descuento' => 'decimal:2',
            'subtotal_neto' => 'decimal:2',
        ];
    }

    public function notaVenta(): BelongsTo
    {
        return $this->belongsTo(NotaVenta::class, 'nota_venta_id');
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(PresentacionProducto::class, 'presentacion_id');
    }
}
