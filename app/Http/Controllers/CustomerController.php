<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Http\Request;
final class CustomerController extends Controller
{
    public function __construct(private CustomerRepositoryInterface $customers) {}
    public function index(Request $request)
    {
        $params=$request->validate(['active'=>['sometimes','boolean'],'per_page'=>['sometimes','integer','min:1','max:100']]);
        $filters=array_intersect_key($params,array_flip(['active']));
        return response()->json($this->customers->paginate($filters,(int)($params['per_page']??25)));
    }
    public function show(int $id)
    {
        $customer=$this->customers->find($id);
        abort_unless($customer,404);
        return response()->json($customer);
    }
}