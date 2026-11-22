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
        'inicio_actividades','situacion_tributaria','correo_dte',
        'telefono_secundario','sitio_web','representante_legal','representante_rut',
        'contacto_nombre','contacto_cargo','contacto_telefono','contacto_correo',
        'direccion_numero','direccion_detalle','codigo_postal',
        'ciudad_id','observaciones','moneda_preferida','plazo_pago_dias',
        'correo_electronico',
        'telefono',
        'direccion',
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
