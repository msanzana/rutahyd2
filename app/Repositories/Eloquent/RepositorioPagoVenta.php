<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\PagoVenta;
use App\Repositories\Contratos\RepositorioPagoVentaInterface;
final class RepositorioPagoVenta extends RepositorioEloquent implements RepositorioPagoVentaInterface
{
    public function __construct(PagoVenta $modelo) { parent::__construct($modelo); }
}
