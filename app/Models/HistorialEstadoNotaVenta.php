<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HistorialEstadoNotaVenta extends Model
{
    protected $table = 'historial_estados_notas_venta';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = null;

    protected $fillable = [
        'nota_venta_id',
        'estado_anterior',
        'estado_nuevo',
        'modificado_por',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'creado_en' => 'date',
        ];
    }

    public function notaVenta(): BelongsTo
    {
        return $this->belongsTo(NotaVenta::class, 'nota_venta_id');
    }

    public function modificadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'modificado_por');
    }
}
