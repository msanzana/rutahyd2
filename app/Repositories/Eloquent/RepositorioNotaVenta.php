<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\NotaVenta;
use App\Repositories\Contratos\RepositorioNotaVentaInterface;
final class RepositorioNotaVenta extends RepositorioEloquent implements RepositorioNotaVentaInterface
{
    public function __construct(NotaVenta $modelo) { parent::__construct($modelo); }
}
