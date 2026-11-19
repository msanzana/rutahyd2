<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\DetallePromocion;
use App\Repositories\Contratos\RepositorioDetallePromocionInterface;
final class RepositorioDetallePromocion extends RepositorioEloquent implements RepositorioDetallePromocionInterface
{
    public function __construct(DetallePromocion $modelo) { parent::__construct($modelo); }
}
