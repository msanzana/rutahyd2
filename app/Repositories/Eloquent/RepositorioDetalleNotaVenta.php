<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\DetalleNotaVenta;
use App\Repositories\Contratos\RepositorioDetalleNotaVentaInterface;
final class RepositorioDetalleNotaVenta extends RepositorioEloquent implements RepositorioDetalleNotaVentaInterface
{
    public function __construct(DetalleNotaVenta $modelo) { parent::__construct($modelo); }
}
