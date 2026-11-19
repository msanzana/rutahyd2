<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleNoteItem extends Model
{
    protected $table = 'sale_note_items';
    public $timestamps = false;

    protected $fillable = [
        'sale_note_id',
        'presentation_id',
        'quantity_ordered',
        'quantity_delivered',
        'unit_price_net',
        'discount_percent',
        'subtotal_net',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:4',
            'quantity_delivered' => 'decimal:4',
            'discount_percent' => 'decimal:4',
            'subtotal_net' => 'decimal:4',
        ];
    }

    public function saleNote(): BelongsTo
    {
        return $this->belongsTo(SaleNote::class, 'sale_note_id');
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'presentation_id');
    }
}
