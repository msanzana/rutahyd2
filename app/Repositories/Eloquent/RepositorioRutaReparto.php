<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\RutaReparto;
use App\Repositories\Contratos\RepositorioRutaRepartoInterface;
final class RepositorioRutaReparto extends RepositorioEloquent implements RepositorioRutaRepartoInterface
{
    public function __construct(RutaReparto $modelo) { parent::__construct($modelo); }
}
