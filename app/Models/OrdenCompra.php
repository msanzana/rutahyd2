<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrdenCompra extends Model
{
    protected $table = 'ordenes_compra';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'numero',
        'proveedor_id',
        'estado',
        'estado_id',
        'solicitada_en',
        'recibida_en',
        'subtotal_neto',
        'total_impuestos',
        'total_bruto',
        'observaciones',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'solicitada_en' => 'date',
            'recibida_en' => 'date',
            'subtotal_neto' => 'decimal:2',
            'total_impuestos' => 'decimal:2',
            'total_bruto' => 'decimal:2',
            'creado_en' => 'date',
            'actualizado_en' => 'date',
        ];
    }

    public function estadoCatalogo(): BelongsTo
    {
        return $this->belongsTo(Estado::class,'estado_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
