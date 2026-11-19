<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchGuide extends Model
{
    protected $table = 'dispatch_guides';
    public const UPDATED_AT = null;

    protected $fillable = [
        'internal_folio',
        'from_warehouse_id',
        'to_warehouse_id',
        'sale_note_id',
        'total_net',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [

        ];
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function saleNote(): BelongsTo
    {
        return $this->belongsTo(SaleNote::class, 'sale_note_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
