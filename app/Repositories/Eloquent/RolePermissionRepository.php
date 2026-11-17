<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Repositories\Contracts\RolePermissionRepositoryInterface;
use Illuminate\Support\Facades\DB;
final class RolePermissionRepository implements RolePermissionRepositoryInterface
{
    public function hasPermission(int $roleId, int $permissionId): bool
    { return DB::table('role_permissions')->where('role_id',$roleId)->where('permission_id',$permissionId)->exists(); }
    public function grant(int $roleId, int $permissionId): void
    { DB::table('role_permissions')->insertOrIgnore(['role_id'=>$roleId,'permission_id'=>$permissionId]); }
    public function revoke(int $roleId, int $permissionId): void
    { DB::table('role_permissions')->where('role_id',$roleId)->where('permission_id',$permissionId)->delete(); }
}