<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RutaReparto extends Model
{
    protected $table = 'rutas_reparto';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'usuario_repartidor_id',
        'nombre_vehiculo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function usuarioRepartidor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_repartidor_id');
    }
}
