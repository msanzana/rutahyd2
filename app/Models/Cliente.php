<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Cliente extends Model
{
    protected $table = 'clientes';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'codigo',
        'nombre',
        'rut',
        'correo_electronico',
        'telefono',
        'direccion',
        'ciudad_id',
        'sector',
        'observaciones',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'creado_en' => 'date',
            'actualizado_en' => 'date',
        ];
    }
}
