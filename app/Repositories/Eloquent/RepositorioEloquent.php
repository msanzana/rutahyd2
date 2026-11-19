<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Repositories\Contratos\RepositorioCrudInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
abstract class RepositorioEloquent implements RepositorioCrudInterface
{
    public function __construct(protected Model $modelo) {}
    public function listar(array $filtros=[],int $porPagina=25): LengthAwarePaginator
    {
        $consulta=$this->modelo->newQuery();
        foreach($filtros as $campo=>$valor) {
            if (!in_array($campo,$this->modelo->getFillable(),true)) {
                throw new InvalidArgumentException('Filtro no permitido: '.$campo);
            }
            if ($valor !== null && $valor !== '') $consulta->where($campo,$valor);
        }
        return $consulta->orderByDesc('id')->paginate(max(1,min(100,$porPagina)));
    }
    public function buscar(int $id): ?Model { return $this->modelo->newQuery()->find($id); }
    public function bloquear(int $id): ?Model { return $this->modelo->newQuery()->lockForUpdate()->find($id); }
    public function buscarPor(string $campo,mixed $valor): ?Model
    {
        if (!in_array($campo,$this->modelo->getFillable(),true))
            throw new InvalidArgumentException('Campo no permitido');
        return $this->modelo->newQuery()->where($campo,$valor)->first();
    }
    public function crear(array $datos): Model { return $this->modelo->newQuery()->create($datos); }
    public function actualizar(int $id,array $datos): ?Model
    {
        $registro=$this->bloquear($id);
        if (!$registro) return null;
        $registro->fill($datos);
        $registro->save();
        return $registro->refresh();
    }
    public function eliminar(int $id): bool
    {
        $registro=$this->bloquear($id);
        return $registro ? (bool)$registro->delete() : false;
    }
}
