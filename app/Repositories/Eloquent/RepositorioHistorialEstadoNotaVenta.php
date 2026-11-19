<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\HistorialEstadoNotaVenta;
use App\Repositories\Contratos\RepositorioHistorialEstadoNotaVentaInterface;
final class RepositorioHistorialEstadoNotaVenta extends RepositorioEloquent implements RepositorioHistorialEstadoNotaVentaInterface
{
    public function __construct(HistorialEstadoNotaVenta $modelo) { parent::__construct($modelo); }
}
