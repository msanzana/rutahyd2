<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\QuotationItem;
use App\Repositories\Contracts\QuotationItemRepositoryInterface;

final class QuotationItemRepository extends EloquentRepository implements QuotationItemRepositoryInterface
{
    public function __construct(QuotationItem $model)
    {
        parent::__construct($model);
    }
}
