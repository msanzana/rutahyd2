<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\GuiaDespacho;
use App\Repositories\Contratos\RepositorioGuiaDespachoInterface;
final class RepositorioGuiaDespacho extends RepositorioEloquent implements RepositorioGuiaDespachoInterface
{
    public function __construct(GuiaDespacho $modelo) { parent::__construct($modelo); }
}
