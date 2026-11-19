<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\SalePayment;
use App\Repositories\Contracts\SalePaymentRepositoryInterface;

final class SalePaymentRepository extends EloquentRepository implements SalePaymentRepositoryInterface
{
    public function __construct(SalePayment $model)
    {
        parent::__construct($model);
    }
}
