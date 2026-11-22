<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Bodega extends Model
{
    protected $table = 'bodegas';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'ciudad_id',
        'tipo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
