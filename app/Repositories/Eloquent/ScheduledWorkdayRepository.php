<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ScheduledWorkday;
use App\Repositories\Contracts\ScheduledWorkdayRepositoryInterface;

final class ScheduledWorkdayRepository extends EloquentRepository implements ScheduledWorkdayRepositoryInterface
{
    public function __construct(ScheduledWorkday $model)
    {
        parent::__construct($model);
    }
}
