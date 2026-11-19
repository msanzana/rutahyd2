<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleNoteStatusHistory extends Model
{
    protected $table = 'sale_note_status_history';
    public const UPDATED_AT = null;

    protected $fillable = [
        'sale_note_id',
        'previous_status',
        'new_status',
        'changed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [

        ];
    }

    public function saleNote(): BelongsTo
    {
        return $this->belongsTo(SaleNote::class, 'sale_note_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
