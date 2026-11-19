<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ProductPresentation;
use App\Repositories\Contracts\ProductPresentationRepositoryInterface;

final class ProductPresentationRepository extends EloquentRepository implements ProductPresentationRepositoryInterface
{
    public function __construct(ProductPresentation $model)
    {
        parent::__construct($model);
    }
}
