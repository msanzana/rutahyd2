<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\ProductPresentationRepositoryInterface;
use App\Repositories\Contracts\SaleNoteRepositoryInterface;
use App\Repositories\Contracts\SaleNoteItemRepositoryInterface;
use App\Repositories\Contracts\SaleNoteStatusHistoryRepositoryInterface;
use App\Repositories\Contracts\SalePaymentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class SaleNoteService
{
    public function __construct(
        private CustomerRepositoryInterface $customers,
        private ProductPresentationRepositoryInterface $presentations,
        private SaleNoteRepositoryInterface $notes,
        private SaleNoteItemRepositoryInterface $items,
        private SaleNoteStatusHistoryRepositoryInterface $history,
        private SalePaymentRepositoryInterface $payments
    ) {}
    public function create(array $payload,int $userId): int
    {
        return DB::transaction(function () use ($payload,$userId) {
            if (!$this->customers->find((int)$payload['customer_id'])) throw ValidationException::withMessages(['customer_id'=>'Cliente inexistente']);
            if ($this->notes->firstWhere('number',$payload['number'])) throw ValidationException::withMessages(['number'=>'Folio existente']);
            $net=0.0; $tax=0.0; $lines=[];
            foreach ($payload['items'] as $line) {
                $presentation=$this->presentations->find((int)$line['presentation_id']);
                if (!$presentation || !$presentation->active || (float)$presentation->price_net <= 0) throw ValidationException::withMessages(['items'=>'Presentacion o precio invalido']);
                $qty=(float)$line['quantity'];
                if ($qty <= 0) throw ValidationException::withMessages(['items'=>'Cantidad invalida']);
                $lineNet=round((float)$presentation->price_net*$qty,2);
                $lineTax=round($lineNet*(float)$presentation->tax_rate/100,2);
                $net+=$lineNet; $tax+=$lineTax;
                $lines[]=['presentation_id'=>$presentation->id,'quantity_ordered'=>$line['quantity'],'quantity_delivered'=>0,
                    'unit_price_net'=>$presentation->price_net,'discount_percent'=>0,'subtotal_net'=>$lineNet];
            }
            $sale=$this->notes->create([
                'number'=>$payload['number'],'customer_id'=>$payload['customer_id'],
                'seller_user_id'=>$userId,'delivery_route_id'=>$payload['delivery_route_id']??null,
                'delivery_at'=>$payload['delivery_at']??null,'delivery_address'=>$payload['delivery_address']??null,
                'payment_method'=>$payload['payment_method']??null,'notes'=>$payload['notes']??null,
                'status'=>'received','payment_status'=>'pending',
                'subtotal_net'=>round($net,2),'discount_total'=>0,
                'tax_total'=>round($tax,2),'total_gross'=>round($net+$tax,2)
            ]);
            foreach($lines as $line) $this->items->create(['sale_note_id'=>$sale->id]+$line);
            $this->history->create(['sale_note_id'=>$sale->id,'new_status'=>'received','changed_by'=>$userId]);
            return (int)$sale->id;
        },3);
    }
    public function changeStatus(int $id,string $newStatus,int $userId): void
    {
        $transitions=[
            'received'=>['to_pick','rejected'],
            'to_pick'=>['ready_to_dispatch','rejected'],
            'ready_to_dispatch'=>['pending_delivery','rejected'],
            'pending_delivery'=>['delivered','rejected'],
            'delivered'=>['completed'],
            'completed'=>[], 'rejected'=>[]
        ];
        DB::transaction(function () use ($id,$newStatus,$userId,$transitions) {
            $note=$this->notes->lock($id);
            if (!$note) abort(404);
            if (!in_array($newStatus,$transitions[$note->status]??[],true)) throw ValidationException::withMessages(['status'=>'Transicion no autorizada']);
            $previous=$note->status;
            $this->notes->update($id,['status'=>$newStatus]);
            $this->history->create(['sale_note_id'=>$id,'previous_status'=>$previous,'new_status'=>$newStatus,'changed_by'=>$userId]);
        },3);
    }
    public function recordPayment(int $id,array $data,int $userId): int
    {
        return DB::transaction(function () use ($id,$data,$userId) {
            $note=$this->notes->lock($id);
            if (!$note) abort(404);
            $previous=(float) DB::table('sale_payments')->where('sale_note_id',$id)->sum('amount_gross');
            $payment=round((float)$data['amount_gross'],2);
            if ($payment<=0 || $previous+$payment>(float)$note->total_gross+0.001) throw ValidationException::withMessages(['amount_gross'=>'Abono invalido o superior al saldo']);
            if (!empty($data['reference'])) {
                $duplicate=DB::table('sale_payments')->where('sale_note_id',$id)->where('reference',$data['reference'])->first();
                if ($duplicate) return (int)$duplicate->id;
            }
            $p=$this->payments->create(['sale_note_id'=>$id,'amount_gross'=>$payment,'method'=>$data['method'],
                'payment_at'=>now(),'reference'=>$data['reference']??null,'recorded_by'=>$userId]);
            $this->notes->update($id,['payment_status'=>abs($previous+$payment-(float)$note->total_gross)<0.01?'paid':'partial']);
            return (int)$p->id;
        },3);
    }
}