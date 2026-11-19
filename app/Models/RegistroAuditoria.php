<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RegistroAuditoria extends Model
{
    protected $table = 'registros_auditoria';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id',
        'accion',
        'tipo_entidad',
        'entidad_id',
        'valores_anteriores',
        'valores_nuevos',
    ];

    protected function casts(): array
    {
        return [
            'creado_en' => 'date',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
