<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PurchaseOrder;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;

final class PurchaseOrderRepository extends EloquentRepository implements PurchaseOrderRepositoryInterface
{
    public function __construct(PurchaseOrder $model)
    {
        parent::__construct($model);
    }
}
