<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPresentation extends Model
{
    protected $table = 'product_presentations';

    protected $fillable = [
        'product_id',
        'barcode',
        'name',
        'units_per_package',
        'price_net',
        'tax_rate',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'units_per_package' => 'decimal:4',
            'price_net' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
