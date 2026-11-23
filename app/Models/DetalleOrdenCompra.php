<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DetalleOrdenCompra extends Model
{
    protected $table = 'detalles_ordenes_compra';
    public $timestamps = false;

    protected $fillable = [
        'orden_compra_id',
        'producto_id',
        'cantidad',
        'costo_unitario_neto',
        'subtotal_neto',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'costo_unitario_neto' => 'decimal:4',
            'subtotal_neto' => 'decimal:2',
        ];
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
