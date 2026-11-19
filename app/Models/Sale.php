<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $table = 'sales';
    protected $fillable = [
        'sale_note_id',
        'customer_id',
        'sale_number',
        'status',
        'currency_code',
        'issue_date',
        'dte_type',
        'dte_external_id',
        'dte_company_id',
        'dte_external_system_id',
        'dte_proposed_folio',
        'dte_proposed_sii_document_type',
        'dte_branch_office_number',
        'dte_facility_number',
        'dte_external_branch_code',
        'dte_remote_document_id',
        'dte_folio',
        'dte_status',
        'dte_sii_environment',
        'receiver_document',
        'receiver_name',
        'receiver_giro',
        'receiver_address',
        'receiver_city_id',
        'receiver_email',
        'net_amount',
        'exempt_amount',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'header_payload',
        'references_payload',
        'dte_request_payload',
        'dte_response_payload',
        'dte_last_error_code',
        'dte_last_error_message',
        'dte_requested_at',
        'dte_created_at',
        'dte_accepted_at',
        'dte_rejected_at',
        'notes',
        'created_by',
    ];
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'dte_requested_at' => 'datetime',
            'dte_created_at' => 'datetime',
            'dte_accepted_at' => 'datetime',
            'dte_rejected_at' => 'datetime',
            'header_payload' => 'array',
            'references_payload' => 'array',
            'dte_request_payload' => 'array',
            'dte_response_payload' => 'array',
            'net_amount' => 'decimal:2',
            'exempt_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function saleNote(): BelongsTo { return $this->belongsTo(SaleNote::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function details(): HasMany { return $this->hasMany(SaleDetail::class); }
}
