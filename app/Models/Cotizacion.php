<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Cotizacion extends Model
{
    protected $table = 'cotizaciones';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'numero',
        'cliente_id',
        'usuario_vendedor_id',
        'estado',
        'vigente_hasta',
        'subtotal_neto',
        'total_descuentos',
        'total_impuestos',
        'total_bruto',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'vigente_hasta' => 'date',
            'subtotal_neto' => 'decimal:2',
            'total_descuentos' => 'decimal:2',
            'total_impuestos' => 'decimal:2',
            'total_bruto' => 'decimal:2',
            'creado_en' => 'date',
            'actualizado_en' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuarioVendedor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_vendedor_id');
    }
}
