<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\Cotizacion;
use App\Repositories\Contratos\RepositorioCotizacionInterface;
final class RepositorioCotizacion extends RepositorioEloquent implements RepositorioCotizacionInterface
{
    public function __construct(Cotizacion $modelo) { parent::__construct($modelo); }
}
