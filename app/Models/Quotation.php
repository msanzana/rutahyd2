<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    protected $table = 'quotations';

    protected $fillable = [
        'number',
        'customer_id',
        'seller_user_id',
        'status',
        'valid_until',
        'subtotal_net',
        'discount_total',
        'tax_total',
        'total_gross',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'subtotal_net' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total_gross' => 'decimal:4',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function sellerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }
}
