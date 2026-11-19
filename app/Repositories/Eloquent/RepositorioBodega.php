<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\Bodega;
use App\Repositories\Contratos\RepositorioBodegaInterface;
final class RepositorioBodega extends RepositorioEloquent implements RepositorioBodegaInterface
{
    public function __construct(Bodega $modelo) { parent::__construct($modelo); }
}
