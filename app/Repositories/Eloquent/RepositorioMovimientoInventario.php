<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\MovimientoInventario;
use App\Repositories\Contratos\RepositorioMovimientoInventarioInterface;
final class RepositorioMovimientoInventario extends RepositorioEloquent implements RepositorioMovimientoInventarioInterface
{
    public function __construct(MovimientoInventario $modelo) { parent::__construct($modelo); }
}
