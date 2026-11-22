<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchGuideItem extends Model
{
    protected $table = 'dispatch_guide_items';
    public $timestamps = false;

    protected $fillable = [
        'dispatch_guide_id',
        'presentation_id',
        'quantity',
        'unit_price_net',
        'subtotal_net',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'subtotal_net' => 'decimal:4',
        ];
    }

    public function dispatchGuide(): BelongsTo
    {
        return $this->belongsTo(DispatchGuide::class, 'dispatch_guide_id');
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'presentation_id');
    }
}
