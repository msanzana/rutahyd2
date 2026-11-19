<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PromotionItem;
use App\Repositories\Contracts\PromotionItemRepositoryInterface;

final class PromotionItemRepository extends EloquentRepository implements PromotionItemRepositoryInterface
{
    public function __construct(PromotionItem $model)
    {
        parent::__construct($model);
    }
}
