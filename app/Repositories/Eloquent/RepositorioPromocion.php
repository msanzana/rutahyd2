<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\Promocion;
use App\Repositories\Contratos\RepositorioPromocionInterface;
final class RepositorioPromocion extends RepositorioEloquent implements RepositorioPromocionInterface
{
    public function __construct(Promocion $modelo) { parent::__construct($modelo); }
}
