<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\DetalleOrdenCompra;
use App\Repositories\Contratos\RepositorioDetalleOrdenCompraInterface;
final class RepositorioDetalleOrdenCompra extends RepositorioEloquent implements RepositorioDetalleOrdenCompraInterface
{
    public function __construct(DetalleOrdenCompra $modelo) { parent::__construct($modelo); }
}
