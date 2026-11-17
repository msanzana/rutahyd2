<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PurchaseOrderItem;
use App\Repositories\Contracts\PurchaseOrderItemRepositoryInterface;

final class PurchaseOrderItemRepository extends EloquentRepository implements PurchaseOrderItemRepositoryInterface
{
    public function __construct(PurchaseOrderItem $model)
    {
        parent::__construct($model);
    }
}
