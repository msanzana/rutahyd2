<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CategoriaProducto extends Model
{
    protected $table = 'categorias_productos';
    public $timestamps = false;

    protected $fillable = [
        'padre_id',
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'padre_id');
    }
}
