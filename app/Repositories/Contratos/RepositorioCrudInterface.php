<?php
declare(strict_types=1);
namespace App\Repositories\Contratos;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
interface RepositorioCrudInterface
{
    public function listar(array $filtros=[],int $porPagina=25): LengthAwarePaginator;
    public function buscar(int $id): ?Model;
    public function bloquear(int $id): ?Model;
    public function buscarPor(string $campo,mixed $valor): ?Model;
    public function crear(array $datos): Model;
    public function actualizar(int $id,array $datos): ?Model;
    public function eliminar(int $id): bool;
}
