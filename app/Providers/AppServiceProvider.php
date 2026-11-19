<?php
declare(strict_types=1);
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Repositories\Contracts\RoleRepositoryInterface::class, \App\Repositories\Eloquent\RoleRepository::class);
        $this->app->bind(\App\Repositories\Contracts\PermissionRepositoryInterface::class, \App\Repositories\Eloquent\PermissionRepository::class);
        $this->app->bind(\App\Repositories\Contracts\RolePermissionRepositoryInterface::class, \App\Repositories\Eloquent\RolePermissionRepository::class);
        $this->app->bind(\App\Repositories\Contracts\UserRepositoryInterface::class, \App\Repositories\Eloquent\UserRepository::class);
        $this->app->bind(\App\Repositories\Contracts\CustomerRepositoryInterface::class, \App\Repositories\Eloquent\CustomerRepository::class);
        $this->app->bind(\App\Repositories\Contracts\SupplierRepositoryInterface::class, \App\Repositories\Eloquent\SupplierRepository::class);
        $this->app->bind(\App\Repositories\Contracts\ProductCategoryRepositoryInterface::class, \App\Repositories\Eloquent\ProductCategoryRepository::class);
        $this->app->bind(\App\Repositories\Contracts\ProductRepositoryInterface::class, \App\Repositories\Eloquent\ProductRepository::class);
        $this->app->bind(\App\Repositories\Contracts\ProductPresentationRepositoryInterface::class, \App\Repositories\Eloquent\ProductPresentationRepository::class);
        $this->app->bind(\App\Repositories\Contracts\ProductImageRepositoryInterface::class, \App\Repositories\Eloquent\ProductImageRepository::class);
        $this->app->bind(\App\Repositories\Contracts\CustomerPriceRepositoryInterface::class, \App\Repositories\Eloquent\CustomerPriceRepository::class);
        $this->app->bind(\App\Repositories\Contracts\WarehouseRepositoryInterface::class, \App\Repositories\Eloquent\WarehouseRepository::class);
        $this->app->bind(\App\Repositories\Contracts\StockBalanceRepositoryInterface::class, \App\Repositories\Eloquent\StockBalanceRepository::class);
        $this->app->bind(\App\Repositories\Contracts\StockMovementRepositoryInterface::class, \App\Repositories\Eloquent\StockMovementRepository::class);
        $this->app->bind(\App\Repositories\Contracts\PromotionRepositoryInterface::class, \App\Repositories\Eloquent\PromotionRepository::class);
        $this->app->bind(\App\Repositories\Contracts\PromotionItemRepositoryInterface::class, \App\Repositories\Eloquent\PromotionItemRepository::class);
        $this->app->bind(\App\Repositories\Contracts\PurchaseOrderRepositoryInterface::class, \App\Repositories\Eloquent\PurchaseOrderRepository::class);
        $this->app->bind(\App\Repositories\Contracts\PurchaseOrderItemRepositoryInterface::class, \App\Repositories\Eloquent\PurchaseOrderItemRepository::class);
        $this->app->bind(\App\Repositories\Contracts\DeliveryRouteRepositoryInterface::class, \App\Repositories\Eloquent\DeliveryRouteRepository::class);
        $this->app->bind(\App\Repositories\Contracts\QuotationRepositoryInterface::class, \App\Repositories\Eloquent\QuotationRepository::class);
        $this->app->bind(\App\Repositories\Contracts\QuotationItemRepositoryInterface::class, \App\Repositories\Eloquent\QuotationItemRepository::class);
        $this->app->bind(\App\Repositories\Contracts\SaleNoteRepositoryInterface::class, \App\Repositories\Eloquent\SaleNoteRepository::class);
        $this->app->bind(\App\Repositories\Contracts\SaleNoteItemRepositoryInterface::class, \App\Repositories\Eloquent\SaleNoteItemRepository::class);
        $this->app->bind(\App\Repositories\Contracts\SaleNoteStatusHistoryRepositoryInterface::class, \App\Repositories\Eloquent\SaleNoteStatusHistoryRepository::class);
        $this->app->bind(\App\Repositories\Contracts\SalePaymentRepositoryInterface::class, \App\Repositories\Eloquent\SalePaymentRepository::class);
        $this->app->bind(\App\Repositories\Contracts\DispatchGuideRepositoryInterface::class, \App\Repositories\Eloquent\DispatchGuideRepository::class);
        $this->app->bind(\App\Repositories\Contracts\DispatchGuideItemRepositoryInterface::class, \App\Repositories\Eloquent\DispatchGuideItemRepository::class);
        $this->app->bind(\App\Repositories\Contracts\IssuedDocumentRepositoryInterface::class, \App\Repositories\Eloquent\IssuedDocumentRepository::class);
        $this->app->bind(\App\Repositories\Contracts\AgendaEventRepositoryInterface::class, \App\Repositories\Eloquent\AgendaEventRepository::class);
        $this->app->bind(\App\Repositories\Contracts\MonthlySalesTargetRepositoryInterface::class, \App\Repositories\Eloquent\MonthlySalesTargetRepository::class);
        $this->app->bind(\App\Repositories\Contracts\ScheduledWorkdayRepositoryInterface::class, \App\Repositories\Eloquent\ScheduledWorkdayRepository::class);
        $this->app->bind(\App\Repositories\Contracts\AuditLogRepositoryInterface::class, \App\Repositories\Eloquent\AuditLogRepository::class);
        $this->app->bind(\App\Repositories\Contracts\SaleRepositoryInterface::class, \App\Repositories\Eloquent\SaleRepository::class);
        $this->app->bind(\App\Repositories\Contracts\SaleDetailRepositoryInterface::class, \App\Repositories\Eloquent\SaleDetailRepository::class);
    }
    public function boot(): void {}
}