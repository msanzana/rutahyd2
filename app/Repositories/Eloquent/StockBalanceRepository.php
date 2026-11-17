<?php
declare(strict_types=1);
namespace App\Repositories\Eloquent;
use App\Repositories\Contracts\StockBalanceRepositoryInterface;
use Illuminate\Support\Facades\DB;
final class StockBalanceRepository implements StockBalanceRepositoryInterface
{
    public function lockedQuantity(int $warehouseId,int $presentationId): string
    {
        DB::table('stock_balances')->insertOrIgnore([
            'warehouse_id'=>$warehouseId,'presentation_id'=>$presentationId,'quantity'=>0
        ]);
        $balance=DB::table('stock_balances')
           ->where('warehouse_id',$warehouseId)->where('presentation_id',$presentationId)
           ->lockForUpdate()->first();
        return (string) $balance->quantity;
    }
    public function setQuantity(int $warehouseId,int $presentationId,string $quantity): void
    {
        DB::table('stock_balances')->where('warehouse_id',$warehouseId)
          ->where('presentation_id',$presentationId)
          ->update(['quantity'=>$quantity,'updated_at'=>now()]);
    }
}