<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Promotion;
use App\Repositories\Contracts\PromotionRepositoryInterface;

final class PromotionRepository extends EloquentRepository implements PromotionRepositoryInterface
{
    public function __construct(Promotion $model)
    {
        parent::__construct($model);
    }
}
