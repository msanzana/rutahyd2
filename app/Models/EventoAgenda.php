<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventoAgenda extends Model
{
    protected $table = 'eventos_agenda';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'tipo_evento',
        'titulo',
        'descripcion',
        'inicia_en',
        'finaliza_en',
        'cliente_id',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'inicia_en' => 'date',
            'finaliza_en' => 'date',
            'creado_en' => 'date',
            'actualizado_en' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
