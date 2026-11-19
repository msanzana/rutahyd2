<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\EventoAgenda;
use App\Repositories\Contratos\RepositorioEventoAgendaInterface;
final class RepositorioEventoAgenda extends RepositorioEloquent implements RepositorioEventoAgendaInterface
{
    public function __construct(EventoAgenda $modelo) { parent::__construct($modelo); }
}
