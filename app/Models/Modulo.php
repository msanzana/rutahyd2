<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Modulo extends Model
{
    protected $table='modulos';
    public const CREATED_AT='creado_en';
    public const UPDATED_AT='actualizado_en';
    protected $fillable=['codigo','nombre','activo'];

    public function estados(): BelongsToMany
    {
        return $this->belongsToMany(Estado::class,'modulos_estados','modulo_id','estado_id')
            ->withPivot(['id','orden','activo']);
    }
}
