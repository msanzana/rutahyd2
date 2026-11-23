<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Estado global y único, reutilizable por cualquier módulo. */
final class Estado extends Model
{
    protected $table='estados';
    public const CREATED_AT='creado_en';
    public const UPDATED_AT='actualizado_en';
    protected $fillable=['codigo','nombre','descripcion','activo'];

    public function modulos(): BelongsToMany
    {
        return $this->belongsToMany(Modulo::class,'modulos_estados','estado_id','modulo_id')
            ->withPivot(['id','orden','activo']);
    }
}
