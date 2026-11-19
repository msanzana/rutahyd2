<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\DetalleGuiaDespacho;
use App\Repositories\Contratos\RepositorioDetalleGuiaDespachoInterface;
final class RepositorioDetalleGuiaDespacho extends RepositorioEloquent implements RepositorioDetalleGuiaDespachoInterface
{
    public function __construct(DetalleGuiaDespacho $modelo) { parent::__construct($modelo); }
}
