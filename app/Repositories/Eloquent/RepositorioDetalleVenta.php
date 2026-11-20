<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\DetalleVenta;
use App\Repositories\Contratos\RepositorioDetalleVentaInterface;
final class RepositorioDetalleVenta extends RepositorioEloquent implements RepositorioDetalleVentaInterface
{
    public function __construct(DetalleVenta $modelo) { parent::__construct($modelo); }
}
