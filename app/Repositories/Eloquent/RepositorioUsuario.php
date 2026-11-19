<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\Usuario;
use App\Repositories\Contratos\RepositorioUsuarioInterface;
final class RepositorioUsuario extends RepositorioEloquent implements RepositorioUsuarioInterface
{
    public function __construct(Usuario $modelo) { parent::__construct($modelo); }
}
