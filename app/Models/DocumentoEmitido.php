<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentoEmitido extends Model
{
    protected $table = 'documentos_emitidos';
    public const CREATED_AT = 'creado_en';
    public const UPDATED_AT = null;

    protected $fillable = [
        'nota_venta_id',
        'tipo_documento',
        'folio',
        'sistema_emisor',
        'emitido_en',
        'estado',
        'ruta_pdf',
        'identificador_seguimiento_sii',
    ];

    protected function casts(): array
    {
        return [
            'emitido_en' => 'date',
            'creado_en' => 'date',
        ];
    }

    public function notaVenta(): BelongsTo
    {
        return $this->belongsTo(NotaVenta::class, 'nota_venta_id');
    }
}
