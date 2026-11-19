<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\Sale;
use App\Repositories\Contracts\SaleRepositoryInterface;
final class SaleRepository extends EloquentRepository implements SaleRepositoryInterface
{
    public function __construct(Sale $model) { parent::__construct($model); }
}
