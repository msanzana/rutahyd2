<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PresentacionProducto extends Model
{
    protected $table = 'presentaciones_productos';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'producto_id',
        'codigo_barras',
        'nombre',
        'unidades_por_envase',
        'precio_neto',
        'tasa_iva',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'unidades_por_envase' => 'decimal:3',
            'precio_neto' => 'decimal:4',
            'tasa_iva' => 'decimal:2',
            'activo' => 'boolean',
            'creado_en' => 'date',
            'actualizado_en' => 'date',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
