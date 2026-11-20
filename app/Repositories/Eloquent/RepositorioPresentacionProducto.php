<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\PresentacionProducto;
use App\Repositories\Contratos\RepositorioPresentacionProductoInterface;
final class RepositorioPresentacionProducto extends RepositorioEloquent implements RepositorioPresentacionProductoInterface
{
    public function __construct(PresentacionProducto $modelo) { parent::__construct($modelo); }
}
