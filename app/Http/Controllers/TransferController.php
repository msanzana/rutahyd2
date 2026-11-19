<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Http\Requests\TransferStockRequest;
use App\Services\StockTransferService;
final class TransferController extends Controller
{
    public function __construct(private StockTransferService $service) {}
    public function store(TransferStockRequest $request)
    {
        $id=$this->service->transfer($request->validated(),(int)$request->user()->id);
        return response()->json(['dispatch_guide_id'=>$id],201);
    }
}