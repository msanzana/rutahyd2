<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\CategoriaProducto;
use App\Repositories\Contratos\RepositorioCategoriaProductoInterface;
final class RepositorioCategoriaProducto extends RepositorioEloquent implements RepositorioCategoriaProductoInterface
{
    public function __construct(CategoriaProducto $modelo) { parent::__construct($modelo); }
}
