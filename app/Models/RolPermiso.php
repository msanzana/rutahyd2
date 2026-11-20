<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RolPermiso extends Model
{
    protected $table = 'roles_permisos';
    public $timestamps = false;
    public $incrementing = false;
    // Clave compuesta: escritura exclusiva a traves del repositorio dedicado.

    protected $fillable = [
        'rol_id',
        'permiso_id',
    ];

    protected function casts(): array
    {
        return [

        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function permiso(): BelongsTo
    {
        return $this->belongsTo(Permiso::class, 'permiso_id');
    }
}
