<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Proveedor extends Model
{
    protected $table = 'proveedores';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'nombre','razon_social','nombre_fantasia',
        'rut','giro','actividad_economica_codigo','tipo_contribuyente',
        'direccion','direccion_numero','ciudad_id',
        'correo_electronico','telefono','plazo_pago_dias','activo',
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
