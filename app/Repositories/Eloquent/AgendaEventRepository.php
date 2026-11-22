<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\AgendaEvent;
use App\Repositories\Contracts\AgendaEventRepositoryInterface;

final class AgendaEventRepository extends EloquentRepository implements AgendaEventRepositoryInterface
{
    public function __construct(AgendaEvent $model)
    {
        parent::__construct($model);
    }
}
