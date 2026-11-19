<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PagoVenta extends Model
{
    protected $table = 'pagos_ventas';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = null;

    protected $fillable = [
        'nota_venta_id',
        'monto_bruto',
        'medio_pago',
        'pagado_en',
        'referencia',
        'observaciones',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'monto_bruto' => 'decimal:2',
            'pagado_en' => 'date',
            'creado_en' => 'date',
        ];
    }

    public function notaVenta(): BelongsTo
    {
        return $this->belongsTo(NotaVenta::class, 'nota_venta_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }
}
