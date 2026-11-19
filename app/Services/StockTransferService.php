<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\Contracts\DispatchGuideRepositoryInterface;
use App\Repositories\Contracts\DispatchGuideItemRepositoryInterface;
use App\Repositories\Contracts\ProductPresentationRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Repositories\Contracts\StockBalanceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class StockTransferService
{
    public function __construct(
        private DispatchGuideRepositoryInterface $guides,
        private DispatchGuideItemRepositoryInterface $items,
        private ProductPresentationRepositoryInterface $presentations,
        private StockMovementRepositoryInterface $movements,
        private StockBalanceRepositoryInterface $balances
    ) {}
    // Operacion idempotente por folio unico, realizada dentro de una sola transaccion.
    public function transfer(array $data,int $userId): int
    {
        return DB::transaction(function () use($data,$userId) {
            $existing=$this->guides->firstWhere('internal_folio',$data['internal_folio']);
            if ($existing) return (int)$existing->id;
            $from=(int)$data['from_warehouse_id']; $to=(int)$data['to_warehouse_id'];
            if ($from===$to) throw ValidationException::withMessages(['to_warehouse_id'=>'Bodegas iguales']);
            $total=0.0; $processed=[]; $items=$data['items'];
            // Orden estable previene bloqueos circulares en transferencias concurrentes.
            usort($items,fn($a,$b)=>$a['presentation_id']<=>$b['presentation_id']);
            $ids=[];
            foreach($items as $row) {
                $pid=(int)$row['presentation_id']; $qty=(float)$row['quantity'];
                if(isset($ids[$pid]) || $qty<=0) throw ValidationException::withMessages(['items'=>'Presentacion repetida o cantidad invalida']);
                $ids[$pid]=true;
                $presentation=$this->presentations->find($pid);
                if(!$presentation || (float)$presentation->price_net<=0) throw ValidationException::withMessages(['items'=>'Precio de venta invalido']);
                $fromQty=(float)$this->balances->lockedQuantity($from,$pid);
                $toQty=(float)$this->balances->lockedQuantity($to,$pid);
                if ($fromQty+0.0001<$qty) throw ValidationException::withMessages(['items'=>'Stock insuficiente en bodega origen']);
                $this->balances->setQuantity($from,$pid,(string)round($fromQty-$qty,3));
                $this->balances->setQuantity($to,$pid,(string)round($toQty+$qty,3));
                $subtotal=round((float)$presentation->price_net*$qty,2);
                $total+=$subtotal;
                $processed[]=['pid'=>$pid,'quantity'=>$row['quantity'],'price'=>$presentation->price_net,'subtotal'=>$subtotal];
            }
            $guide=$this->guides->create(['internal_folio'=>$data['internal_folio'],
                'from_warehouse_id'=>$from,'to_warehouse_id'=>$to,
                'sale_note_id'=>$data['sale_note_id']??null,'total_net'=>round($total,2),
                'status'=>'generated','created_by'=>$userId]);
            foreach($processed as $row) {
                $this->items->create(['dispatch_guide_id'=>$guide->id,'presentation_id'=>$row['pid'],
                   'quantity'=>$row['quantity'],'unit_price_net'=>$row['price'],'subtotal_net'=>$row['subtotal']]);
                $this->movements->create(['presentation_id'=>$row['pid'],'from_warehouse_id'=>$from,
                    'to_warehouse_id'=>$to,'quantity'=>$row['quantity'],'movement_type'=>'transfer',
                    'reference_type'=>'dispatch_guide','reference_id'=>$guide->id,'performed_by'=>$userId]);
            }
            return (int)$guide->id;
        },3);
    }
}