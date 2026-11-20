<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\Cliente;
use App\Repositories\Contratos\RepositorioClienteInterface;
final class RepositorioCliente extends RepositorioEloquent implements RepositorioClienteInterface
{
    public function __construct(Cliente $modelo) { parent::__construct($modelo); }
}
