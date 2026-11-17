<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;
interface RolePermissionRepositoryInterface
{
    public function hasPermission(int $roleId, int $permissionId): bool;
    public function grant(int $roleId, int $permissionId): void;
    public function revoke(int $roleId, int $permissionId): void;
}