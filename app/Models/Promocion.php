<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Promocion extends Model
{
    protected $table = 'promociones';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'titulo',
        'cliente_destino_id',
        'vigente_desde',
        'vigente_hasta',
        'activo',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
            'activo' => 'boolean',
            'creado_en' => 'date',
            'actualizado_en' => 'date',
        ];
    }

    public function clienteDestino(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_destino_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
