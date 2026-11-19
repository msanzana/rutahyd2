<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Models\RegistroAuditoria;
use App\Repositories\Contratos\RepositorioRegistroAuditoriaInterface;
final class RepositorioRegistroAuditoria extends RepositorioEloquent implements RepositorioRegistroAuditoriaInterface
{
    public function __construct(RegistroAuditoria $modelo) { parent::__construct($modelo); }
}
