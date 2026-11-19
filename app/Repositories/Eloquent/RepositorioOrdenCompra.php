<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\OrdenCompra;
use App\Repositories\Contratos\RepositorioOrdenCompraInterface;
final class RepositorioOrdenCompra extends RepositorioEloquent implements RepositorioOrdenCompraInterface
{
    public function __construct(OrdenCompra $modelo) { parent::__construct($modelo); }
}
