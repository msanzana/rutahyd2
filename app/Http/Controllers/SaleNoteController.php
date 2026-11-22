<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Http\Requests\StoreSaleNoteRequest;
use App\Repositories\Contracts\SaleNoteRepositoryInterface;
use App\Services\SaleNoteService;
use Illuminate\Http\Request;
final class SaleNoteController extends Controller
{
    public function __construct(private SaleNoteRepositoryInterface $notes,private SaleNoteService $service) {}
    public function index(Request $request)
    {
        $params=$request->validate(['status'=>['sometimes','string','max:40'],'payment_status'=>['sometimes','string','max:40'],'per_page'=>['sometimes','integer','min:1','max:100']]);
        return response()->json($this->notes->paginate(array_intersect_key($params,array_flip(['status','payment_status'])),(int)($params['per_page']??25)));
    }
    public function show(int $id)
    {
        $note=$this->notes->find($id);
        abort_unless($note,404);
        return response()->json($note->load(['customer','deliveryRoute']));
    }
    public function store(StoreSaleNoteRequest $request)
    {
        $id=$this->service->create($request->validated(),(int)$request->user()->id);
        return response()->json(['id'=>$id],201);
    }
    public function transition(Request $request,int $id)
    {
        $data=$request->validate(['status'=>['required','in:to_pick,ready_to_dispatch,pending_delivery,delivered,completed,rejected']]);
        $this->service->changeStatus($id,$data['status'],(int)$request->user()->id);
        return response()->noContent();
    }
    public function pay(Request $request,int $id)
    {
        $data=$request->validate(['amount_gross'=>['required','numeric','gt:0'],'method'=>['required','string','max:40'],'reference'=>['nullable','string','max:120']]);
        $pid=$this->service->recordPayment($id,$data,(int)$request->user()->id);
        return response()->json(['id'=>$pid],201);
    }
}