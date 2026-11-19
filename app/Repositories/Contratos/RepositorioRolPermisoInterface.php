<?php
declare(strict_types=1);
namespace App\Repositories\Contratos;
interface RepositorioRolPermisoInterface
{
    public function tiene(int $rolId,int $permisoId): bool;
    public function conceder(int $rolId,int $permisoId): void;
    public function revocar(int $rolId,int $permisoId): void;
}
