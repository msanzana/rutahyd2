<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('sales')) {
            Schema::create('sales',function(Blueprint $table): void {
                $table->id();
                $table->foreignId('sale_note_id')->nullable()->unique()->constrained('sale_notes')->nullOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
                $table->string('sale_number',50)->unique();
                $table->string('status',40)->default('draft');
                $table->char('currency_code',3)->default('CLP');
                $table->date('issue_date');
                $table->unsignedSmallInteger('dte_type');
                $table->char('dte_external_id',36)->nullable()->unique();
                $table->unsignedBigInteger('dte_company_id')->nullable();
                $table->unsignedBigInteger('dte_external_system_id')->nullable();
                $table->unsignedBigInteger('dte_proposed_folio')->nullable();
                $table->unsignedSmallInteger('dte_proposed_sii_document_type')->nullable();
                $table->unsignedInteger('dte_branch_office_number')->nullable();
                $table->unsignedInteger('dte_facility_number')->nullable();
                $table->string('dte_external_branch_code',100)->nullable();
                $table->unsignedBigInteger('dte_remote_document_id')->nullable();
                $table->unsignedBigInteger('dte_folio')->nullable();
                $table->string('dte_status',40)->default('not_requested');
                $table->string('dte_sii_environment',10)->nullable();
                $table->string('receiver_document',20);
                $table->string('receiver_name',120);
                $table->string('receiver_giro',150)->nullable();
                $table->string('receiver_address',150)->nullable();
                $table->unsignedBigInteger('receiver_city_id')->nullable();
                $table->string('receiver_email',150)->nullable();
                $table->decimal('net_amount',18,2)->default(0);
                $table->decimal('exempt_amount',18,2)->default(0);
                $table->decimal('tax_amount',18,2)->default(0);
                $table->decimal('discount_amount',18,2)->default(0);
                $table->decimal('total_amount',18,2)->default(0);
                $table->json('header_payload')->nullable();
                $table->json('references_payload')->nullable();
                $table->json('dte_request_payload')->nullable();
                $table->json('dte_response_payload')->nullable();
                $table->string('dte_last_error_code',50)->nullable();
                $table->text('dte_last_error_message')->nullable();
                $table->dateTime('dte_requested_at')->nullable();
                $table->dateTime('dte_created_at')->nullable();
                $table->dateTime('dte_accepted_at')->nullable();
                $table->dateTime('dte_rejected_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['customer_id','issue_date']);
                $table->index(['dte_status','dte_type']);
                $table->index('dte_remote_document_id');
            });
        }
        if (!Schema::hasTable('sale_details')) {
            Schema::create('sale_details',function(Blueprint $table): void {
                $table->id();
                $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
                $table->foreignId('sale_note_item_id')->nullable()->constrained('sale_note_items')->nullOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->unsignedInteger('line_number');
                $table->decimal('quantity',18,4)->default(1);
                $table->decimal('unit_price',18,4)->default(0);
                $table->decimal('discount_percent',8,4)->default(0);
                $table->decimal('discount_amount',18,2)->default(0);
                $table->boolean('tax_exempt')->default(false);
                $table->decimal('line_amount',18,2)->default(0);
                $table->json('extra_payload')->nullable();
                $table->timestamps();
                $table->unique(['sale_id','line_number']);
                $table->index('product_id');
            });
        }
    }
    public function down(): void {
        // Destrucción permitida SOLO por rollback explícito, nunca durante despliegues normales.
        Schema::dropIfExists('sale_details');
        Schema::dropIfExists('sales');
    }
};
