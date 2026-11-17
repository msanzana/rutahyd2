<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Supplier;
use App\Repositories\Contracts\SupplierRepositoryInterface;

final class SupplierRepository extends EloquentRepository implements SupplierRepositoryInterface
{
    public function __construct(Supplier $model)
    {
        parent::__construct($model);
    }
}
