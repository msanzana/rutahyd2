<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\DetalleCotizacion;
use App\Repositories\Contratos\RepositorioDetalleCotizacionInterface;
final class RepositorioDetalleCotizacion extends RepositorioEloquent implements RepositorioDetalleCotizacionInterface
{
    public function __construct(DetalleCotizacion $modelo) { parent::__construct($modelo); }
}
