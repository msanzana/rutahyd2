<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\Venta;
use App\Repositories\Contratos\RepositorioVentaInterface;
final class RepositorioVenta extends RepositorioEloquent implements RepositorioVentaInterface
{
    public function __construct(Venta $modelo) { parent::__construct($modelo); }
}
