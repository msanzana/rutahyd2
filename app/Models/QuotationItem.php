<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $table = 'quotation_items';
    public $timestamps = false;

    protected $fillable = [
        'quotation_id',
        'presentation_id',
        'quantity',
        'unit_price_net',
        'discount_percent',
        'subtotal_net',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'discount_percent' => 'decimal:4',
            'subtotal_net' => 'decimal:4',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'presentation_id');
    }
}
