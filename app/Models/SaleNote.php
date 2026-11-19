<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleNote extends Model
{
    protected $table = 'sale_notes';

    protected $fillable = [
        'number',
        'customer_id',
        'quotation_id',
        'seller_user_id',
        'delivery_route_id',
        'delivery_at',
        'delivery_address',
        'status',
        'payment_status',
        'payment_method',
        'subtotal_net',
        'discount_total',
        'tax_total',
        'total_gross',
        'amount_delivered_gross',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'delivery_at' => 'datetime',
            'subtotal_net' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total_gross' => 'decimal:4',
            'amount_delivered_gross' => 'decimal:4',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function sellerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function deliveryRoute(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'delivery_route_id');
    }
}
