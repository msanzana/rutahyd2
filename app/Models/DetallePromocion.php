<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DetallePromocion extends Model
{
    protected $table = 'detalles_promociones';
    public $timestamps = false;

    protected $fillable = [
        'promocion_id',
        'presentacion_id',
        'porcentaje_descuento',
        'precio_neto',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje_descuento' => 'decimal:2',
            'precio_neto' => 'decimal:4',
        ];
    }

    public function promocion(): BelongsTo
    {
        return $this->belongsTo(Promocion::class, 'promocion_id');
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(PresentacionProducto::class, 'presentacion_id');
    }
}
