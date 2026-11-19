<?php
declare(strict_types=1);
namespace App\Repositories\Contratos;
interface RepositorioSaldoInventarioInterface
{
    public function cantidadBloqueada(int $bodegaId,int $presentacionId): string;
    public function fijarCantidad(int $bodegaId,int $presentacionId,string $cantidad): void;
}
