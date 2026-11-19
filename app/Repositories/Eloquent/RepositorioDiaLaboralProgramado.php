<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\DiaLaboralProgramado;
use App\Repositories\Contratos\RepositorioDiaLaboralProgramadoInterface;
final class RepositorioDiaLaboralProgramado extends RepositorioEloquent implements RepositorioDiaLaboralProgramadoInterface
{
    public function __construct(DiaLaboralProgramado $modelo) { parent::__construct($modelo); }
}
