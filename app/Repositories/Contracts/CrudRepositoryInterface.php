<?php
declare(strict_types=1);
namespace App\Repositories\Contracts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
interface CrudRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function find(int $id): ?Model;
    public function lock(int $id): ?Model;
    public function firstWhere(string $field, mixed $value): ?Model;
    public function create(array $data): Model;
    public function update(int $id, array $data): ?Model;
    public function delete(int $id): bool;
}