<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DetalleGuiaDespacho extends Model
{
    protected $table = 'detalles_guias_despacho';
    public $timestamps = false;

    protected $fillable = [
        'guia_despacho_id',
        'presentacion_id',
        'cantidad',
        'precio_unitario_neto',
        'subtotal_neto',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario_neto' => 'decimal:4',
            'subtotal_neto' => 'decimal:2',
        ];
    }

    public function guiaDespacho(): BelongsTo
    {
        return $this->belongsTo(GuiaDespacho::class, 'guia_despacho_id');
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(PresentacionProducto::class, 'presentacion_id');
    }
}
