<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlySalesTarget extends Model
{
    protected $table = 'monthly_sales_targets';

    protected $fillable = [
        'target_month',
        'target_gross',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'target_month' => 'date',
            'target_gross' => 'decimal:4',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
