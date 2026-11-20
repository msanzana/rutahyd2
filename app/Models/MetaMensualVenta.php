<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MetaMensualVenta extends Model
{
    protected $table = 'metas_mensuales_ventas';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'mes_objetivo',
        'meta_bruta',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'mes_objetivo' => 'date',
            'meta_bruta' => 'decimal:2',
            'creado_en' => 'date',
            'actualizado_en' => 'date',
        ];
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'actualizado_por');
    }
}
