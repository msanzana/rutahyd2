<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\PrecioCliente;
use App\Repositories\Contratos\RepositorioPrecioClienteInterface;
final class RepositorioPrecioCliente extends RepositorioEloquent implements RepositorioPrecioClienteInterface
{
    public function __construct(PrecioCliente $modelo) { parent::__construct($modelo); }
}
