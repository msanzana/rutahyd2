<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\DeliveryRoute;
use App\Repositories\Contracts\DeliveryRouteRepositoryInterface;

final class DeliveryRouteRepository extends EloquentRepository implements DeliveryRouteRepositoryInterface
{
    public function __construct(DeliveryRoute $model)
    {
        parent::__construct($model);
    }
}
