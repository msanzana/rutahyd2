<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = null;

    protected $fillable = [
        'presentacion_id',
        'bodega_origen_id',
        'bodega_destino_id',
        'cantidad',
        'tipo_movimiento',
        'tipo_referencia',
        'referencia_id',
        'observaciones',
        'realizado_por',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'creado_en' => 'date',
        ];
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(PresentacionProducto::class, 'presentacion_id');
    }

    public function bodegaOrigen(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function realizadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'realizado_por');
    }
}
