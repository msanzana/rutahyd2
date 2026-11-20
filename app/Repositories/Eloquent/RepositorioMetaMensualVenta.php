<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\MetaMensualVenta;
use App\Repositories\Contratos\RepositorioMetaMensualVentaInterface;
final class RepositorioMetaMensualVenta extends RepositorioEloquent implements RepositorioMetaMensualVentaInterface
{
    public function __construct(MetaMensualVenta $modelo) { parent::__construct($modelo); }
}
