<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ProductImage;
use App\Repositories\Contracts\ProductImageRepositoryInterface;

final class ProductImageRepository extends EloquentRepository implements ProductImageRepositoryInterface
{
    public function __construct(ProductImage $model)
    {
        parent::__construct($model);
    }
}
