<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\DispatchGuide;
use App\Repositories\Contracts\DispatchGuideRepositoryInterface;

final class DispatchGuideRepository extends EloquentRepository implements DispatchGuideRepositoryInterface
{
    public function __construct(DispatchGuide $model)
    {
        parent::__construct($model);
    }
}
