<?php
use Illuminate\Support\Facades\Route;
Route::get('/',fn()=>response()->json(['servicio'=>'RutaH&D2 API','estado'=>'operativo']));
