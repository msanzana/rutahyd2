<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\SaleNote;
use App\Repositories\Contracts\SaleNoteRepositoryInterface;

final class SaleNoteRepository extends EloquentRepository implements SaleNoteRepositoryInterface
{
    public function __construct(SaleNote $model)
    {
        parent::__construct($model);
    }
}
