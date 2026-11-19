<?php
declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleNoteController;
use App\Http\Controllers\TransferController;
Route::get('/health',fn()=>response()->json(['status'=>'ok','service'=>'rutahyd2-api']));
Route::post('/v1/auth/login',[AuthController::class,'login'])->middleware('throttle:5,1');
Route::middleware('auth:sanctum')->prefix('v1')->group(function() {
    Route::post('/auth/logout',[AuthController::class,'logout']);
    Route::get('/customers',[CustomerController::class,'index'])->middleware('permission:clients.manage');
    Route::get('/customers/{id}',[CustomerController::class,'show'])->middleware('permission:clients.manage')->whereNumber('id');
    Route::get('/products',[ProductController::class,'index'])->middleware('permission:products.manage');
    Route::get('/products/{id}',[ProductController::class,'show'])->middleware('permission:products.manage')->whereNumber('id');
    Route::get('/sale-notes',[SaleNoteController::class,'index'])->middleware('permission:sales.view');
    Route::get('/sale-notes/{id}',[SaleNoteController::class,'show'])->middleware('permission:sales.view')->whereNumber('id');
    Route::post('/sale-notes',[SaleNoteController::class,'store'])->middleware('permission:sales.manage');
    Route::post('/sale-notes/{id}/transition',[SaleNoteController::class,'transition'])->middleware('permission:sales.manage')->whereNumber('id');
    Route::post('/sale-notes/{id}/payments',[SaleNoteController::class,'pay'])->middleware('permission:sales.manage')->whereNumber('id');
    Route::post('/stock-transfers',[TransferController::class,'store'])->middleware('permission:inventory.manage');
});
