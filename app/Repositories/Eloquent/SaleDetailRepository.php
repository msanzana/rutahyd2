<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\SaleDetail;
use App\Repositories\Contracts\SaleDetailRepositoryInterface;
final class SaleDetailRepository extends EloquentRepository implements SaleDetailRepositoryInterface
{
    public function __construct(SaleDetail $model) { parent::__construct($model); }
}
