<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    protected $table = 'stock_balances';
    public const CREATED_AT = null;
    public $incrementing = false;
    // IMPORTANTE: Eloquent no admite claves primarias compuestas para save/delete.
    // Use su repositorio de consulta por clave compuesta.

    protected $fillable = [
        'warehouse_id',
        'presentation_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'presentation_id');
    }
}
