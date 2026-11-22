<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\IssuedDocument;
use App\Repositories\Contracts\IssuedDocumentRepositoryInterface;

final class IssuedDocumentRepository extends EloquentRepository implements IssuedDocumentRepositoryInterface
{
    public function __construct(IssuedDocument $model)
    {
        parent::__construct($model);
    }
}
