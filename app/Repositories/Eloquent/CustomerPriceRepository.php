<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\CustomerPrice;
use App\Repositories\Contracts\CustomerPriceRepositoryInterface;

final class CustomerPriceRepository extends EloquentRepository implements CustomerPriceRepositoryInterface
{
    public function __construct(CustomerPrice $model)
    {
        parent::__construct($model);
    }
}
