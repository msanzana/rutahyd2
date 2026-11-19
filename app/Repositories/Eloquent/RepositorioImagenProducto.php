<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\ImagenProducto;
use App\Repositories\Contratos\RepositorioImagenProductoInterface;
final class RepositorioImagenProducto extends RepositorioEloquent implements RepositorioImagenProductoInterface
{
    public function __construct(ImagenProducto $modelo) { parent::__construct($modelo); }
}
