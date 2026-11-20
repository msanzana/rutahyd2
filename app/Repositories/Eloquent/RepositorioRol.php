<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\Rol;
use App\Repositories\Contratos\RepositorioRolInterface;
final class RepositorioRol extends RepositorioEloquent implements RepositorioRolInterface
{
    public function __construct(Rol $modelo) { parent::__construct($modelo); }
}
