<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Repositories\Contracts\CrudRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
abstract class EloquentRepository implements CrudRepositoryInterface
{
    public function __construct(protected Model $model) {}
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->model->newQuery();
        foreach ($filters as $field => $value) {
            if (!in_array($field, $this->model->getFillable(), true)) {
                throw new InvalidArgumentException('Filtro no autorizado: '.$field);
            }
            if ($value !== null && $value !== '') $query->where($field, $value);
        }
        return $query->orderByDesc('id')->paginate(max(1, min(100, $perPage)));
    }
    public function find(int $id): ?Model { return $this->model->newQuery()->find($id); }
    public function lock(int $id): ?Model { return $this->model->newQuery()->lockForUpdate()->find($id); }
    public function firstWhere(string $field, mixed $value): ?Model
    {
        if (!in_array($field, $this->model->getFillable(), true)) {
            throw new InvalidArgumentException('Campo no autorizado: '.$field);
        }
        return $this->model->newQuery()->where($field, $value)->first();
    }
    public function create(array $data): Model { return $this->model->newQuery()->create($data); }
    public function update(int $id, array $data): ?Model
    {
        $record = $this->lock($id);
        if (!$record) return null;
        $record->fill($data);
        $record->save();
        return $record->refresh();
    }
    public function delete(int $id): bool
    {
        $record = $this->lock($id);
        return $record ? (bool) $record->delete() : false;
    }
}