<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\Permiso;
use App\Repositories\Contratos\RepositorioPermisoInterface;
final class RepositorioPermiso extends RepositorioEloquent implements RepositorioPermisoInterface
{
    public function __construct(Permiso $modelo) { parent::__construct($modelo); }
}
