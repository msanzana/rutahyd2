<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\MonthlySalesTarget;
use App\Repositories\Contracts\MonthlySalesTargetRepositoryInterface;

final class MonthlySalesTargetRepository extends EloquentRepository implements MonthlySalesTargetRepositoryInterface
{
    public function __construct(MonthlySalesTarget $model)
    {
        parent::__construct($model);
    }
}
