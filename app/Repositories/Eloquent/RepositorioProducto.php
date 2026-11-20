<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\Producto;
use App\Repositories\Contratos\RepositorioProductoInterface;
final class RepositorioProducto extends RepositorioEloquent implements RepositorioProductoInterface
{
    public function __construct(Producto $modelo) { parent::__construct($modelo); }
}
