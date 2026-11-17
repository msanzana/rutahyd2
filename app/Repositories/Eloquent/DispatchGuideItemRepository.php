<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\DispatchGuideItem;
use App\Repositories\Contracts\DispatchGuideItemRepositoryInterface;

final class DispatchGuideItemRepository extends EloquentRepository implements DispatchGuideItemRepositoryInterface
{
    public function __construct(DispatchGuideItem $model)
    {
        parent::__construct($model);
    }
}
