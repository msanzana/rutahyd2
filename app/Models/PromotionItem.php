<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionItem extends Model
{
    protected $table = 'promotion_items';
    public $timestamps = false;

    protected $fillable = [
        'promotion_id',
        'presentation_id',
        'discount_percent',
        'price_net',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:4',
            'price_net' => 'decimal:4',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'presentation_id');
    }
}
