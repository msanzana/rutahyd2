<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Usuario extends Authenticatable
{
    protected $table = 'usuarios';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'rol_id',
        'nombre',
        'rut',
        'correo_electronico',
        'ciudad_id',
        'hash_contrasena',
        'activo',
        'ultimo_acceso_en',
    ];

    protected $hidden = ['hash_contrasena'];

    public function getAuthPasswordName(): string
    {
        return 'hash_contrasena';
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultimo_acceso_en' => 'date',
            'creado_en' => 'date',
            'actualizado_en' => 'date',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }
}
