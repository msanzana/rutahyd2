<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;
interface StockBalanceRepositoryInterface
{
    public function lockedQuantity(int $warehouseId, int $presentationId): string;
    public function setQuantity(int $warehouseId, int $presentationId, string $quantity): void;
}