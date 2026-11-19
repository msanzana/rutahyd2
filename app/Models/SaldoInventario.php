<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SaldoInventario extends Model
{
    protected $table = 'saldos_inventario';
    public const CREATED_AT = null;
    public const UPDATED_AT = 'actualizado_en';
    public $incrementing = false;
    // Clave compuesta: escritura exclusiva a traves del repositorio dedicado.

    protected $fillable = [
        'bodega_id',
        'presentacion_id',
        'cantidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'actualizado_en' => 'date',
        ];
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(PresentacionProducto::class, 'presentacion_id');
    }
}
