<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Repositories\Contratos\RepositorioSaldoInventarioInterface;
use Illuminate\Support\Facades\DB;
final class RepositorioSaldoInventario implements RepositorioSaldoInventarioInterface
{
    public function cantidadBloqueada(int $bodegaId,int $presentacionId): string
    {
        DB::table('saldos_inventario')->insertOrIgnore([
            'bodega_id'=>$bodegaId,'presentacion_id'=>$presentacionId,'cantidad'=>'0.000'
        ]);
        $saldo=DB::table('saldos_inventario')->where('bodega_id',$bodegaId)
            ->where('presentacion_id',$presentacionId)->lockForUpdate()->first();
        return (string)$saldo->cantidad;
    }
    public function fijarCantidad(int $bodegaId,int $presentacionId,string $cantidad): void
    {
        DB::table('saldos_inventario')->where('bodega_id',$bodegaId)
            ->where('presentacion_id',$presentacionId)
            ->update(['cantidad'=>$cantidad,'actualizado_en'=>now()]);
    }
}
