<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ProductCategory;
use App\Repositories\Contracts\ProductCategoryRepositoryInterface;

final class ProductCategoryRepository extends EloquentRepository implements ProductCategoryRepositoryInterface
{
    public function __construct(ProductCategory $model)
    {
        parent::__construct($model);
    }
}
