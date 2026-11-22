<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\SaleNoteItem;
use App\Repositories\Contracts\SaleNoteItemRepositoryInterface;

final class SaleNoteItemRepository extends EloquentRepository implements SaleNoteItemRepositoryInterface
{
    public function __construct(SaleNoteItem $model)
    {
        parent::__construct($model);
    }
}
