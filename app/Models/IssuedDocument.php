<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssuedDocument extends Model
{
    protected $table = 'issued_documents';
    public const UPDATED_AT = null;

    protected $fillable = [
        'sale_note_id',
        'document_type',
        'folio',
        'issuer_system',
        'issue_at',
        'status',
        'pdf_path',
        'sii_track_id',
    ];

    protected function casts(): array
    {
        return [
            'issue_at' => 'datetime',
        ];
    }

    public function saleNote(): BelongsTo
    {
        return $this->belongsTo(SaleNote::class, 'sale_note_id');
    }
}
