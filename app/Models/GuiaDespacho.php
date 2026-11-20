<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GuiaDespacho extends Model
{
    protected $table = 'guias_despacho';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = null;

    protected $fillable = [
        'folio_interno',
        'bodega_origen_id',
        'bodega_destino_id',
        'nota_venta_id',
        'total_neto',
        'estado',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'total_neto' => 'decimal:2',
            'creado_en' => 'date',
        ];
    }

    public function bodegaOrigen(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function notaVenta(): BelongsTo
    {
        return $this->belongsTo(NotaVenta::class, 'nota_venta_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
