<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDetail extends Model
{
    protected $table = 'sale_details';
    protected $fillable = [
        'sale_id',
        'sale_note_item_id',
        'presentation_id',
        'line_number',
        'item_code_type',
        'item_code',
        'name',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'tax_exempt',
        'line_amount',
        'extra_payload',
    ];
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'line_amount' => 'decimal:2',
            'tax_exempt' => 'boolean',
            'extra_payload' => 'array',
        ];
    }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function presentation(): BelongsTo { return $this->belongsTo(ProductPresentation::class); }
    public function saleNoteItem(): BelongsTo { return $this->belongsTo(SaleNoteItem::class); }
}
