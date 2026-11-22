<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermission extends Model
{
    protected $table = 'role_permissions';
    public $timestamps = false;
    public $incrementing = false;
    // IMPORTANTE: Eloquent no admite claves primarias compuestas para save/delete.
    // Use su repositorio de consulta por clave compuesta.

    protected $fillable = [
        'role_id',
        'permission_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
