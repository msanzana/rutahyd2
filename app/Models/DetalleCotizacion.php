<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DetalleCotizacion extends Model
{
    protected $table = 'detalles_cotizaciones';
    public $timestamps = false;

    protected $fillable = [
        'cotizacion_id',
        'presentacion_id',
        'cantidad',
        'precio_unitario_neto',
        'porcentaje_descuento',
        'subtotal_neto',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario_neto' => 'decimal:4',
            'porcentaje_descuento' => 'decimal:2',
            'subtotal_neto' => 'decimal:2',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(PresentacionProducto::class, 'presentacion_id');
    }
}
