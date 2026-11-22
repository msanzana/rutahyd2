<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPrice extends Model
{
    protected $table = 'customer_prices';
    public const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'presentation_id',
        'price_net',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'price_net' => 'decimal:4',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentation::class, 'presentation_id');
    }
}
