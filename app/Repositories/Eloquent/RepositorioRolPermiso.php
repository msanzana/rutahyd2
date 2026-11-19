<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Repositories\Contratos\RepositorioRolPermisoInterface;
use Illuminate\Support\Facades\DB;
final class RepositorioRolPermiso implements RepositorioRolPermisoInterface
{
    public function tiene(int $rolId,int $permisoId): bool
    { return DB::table('roles_permisos')->where('rol_id',$rolId)->where('permiso_id',$permisoId)->exists(); }
    public function conceder(int $rolId,int $permisoId): void
    { DB::table('roles_permisos')->insertOrIgnore(['rol_id'=>$rolId,'permiso_id'=>$permisoId]); }
    public function revocar(int $rolId,int $permisoId): void
    { DB::table('roles_permisos')->where('rol_id',$rolId)->where('permiso_id',$permisoId)->delete(); }
}
