<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\Proveedor;
use App\Repositories\Contratos\RepositorioProveedorInterface;
final class RepositorioProveedor extends RepositorioEloquent implements RepositorioProveedorInterface
{
    public function __construct(Proveedor $modelo) { parent::__construct($modelo); }
}
