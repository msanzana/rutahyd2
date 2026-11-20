<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Rol extends Model
{
    protected $table = 'roles';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'creado_en' => 'date',
            'actualizado_en' => 'date',
        ];
    }
}
