<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\Request;
final class ProductController extends Controller
{
    public function __construct(private ProductRepositoryInterface $products) {}
    public function index(Request $request)
    {
        $params=$request->validate(['active'=>['sometimes','boolean'],'category_id'=>['sometimes','integer'],'per_page'=>['sometimes','integer','min:1','max:100']]);
        return response()->json($this->products->paginate(array_intersect_key($params,array_flip(['active','category_id'])),(int)($params['per_page']??25)));
    }
    public function show(int $id)
    {
        $product=$this->products->find($id);
        abort_unless($product,404);
        return response()->json($product);
    }
}