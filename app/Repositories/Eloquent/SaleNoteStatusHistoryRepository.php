<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\SaleNoteStatusHistory;
use App\Repositories\Contracts\SaleNoteStatusHistoryRepositoryInterface;

final class SaleNoteStatusHistoryRepository extends EloquentRepository implements SaleNoteStatusHistoryRepositoryInterface
{
    public function __construct(SaleNoteStatusHistory $model)
    {
        parent::__construct($model);
    }
}
