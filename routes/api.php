<?php
declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SesionControlador;
use App\Http\Controllers\RecursoControlador;
use App\Http\Controllers\VentaControlador;
use App\Http\Controllers\NotaVentaControlador;
use App\Http\Controllers\InventarioControlador;

Route::get('/estado',fn()=>response()->json(['servicio'=>'RutaH&D2 API','estado'=>'disponible','version'=>'v2-es']));
Route::post('/v1/sesion',[SesionControlador::class,'iniciar'])->middleware('throttle:5,1');

Route::prefix('v1')->middleware('token.rutahd')->group(function(): void {
    Route::get('/perfil',[SesionControlador::class,'perfil']);
    Route::delete('/sesion',[SesionControlador::class,'salir']);
    // Consultas paginadas solo de recursos admitidos y con permiso por rol.
    Route::get('/recursos/{recurso}',[RecursoControlador::class,'listar']);
    Route::get('/recursos/{recurso}/{id}',[RecursoControlador::class,'ver'])->whereNumber('id');

    Route::get('/ventas/{id}/detalles',[VentaControlador::class,'detalles'])->middleware('permiso.rutahd:ventas.ver')->whereNumber('id');
    Route::post('/ventas',[VentaControlador::class,'guardar'])->middleware('permiso.rutahd:ventas.gestionar');
    Route::post('/ventas/{id}/preparar-dte',[VentaControlador::class,'prepararDte'])->middleware('permiso.rutahd:ventas.gestionar')->whereNumber('id');

    Route::post('/notas-venta',[NotaVentaControlador::class,'guardar'])->middleware('permiso.rutahd:ventas.gestionar');
    Route::post('/notas-venta/{id}/estado',[NotaVentaControlador::class,'cambiarEstado'])->middleware('permiso.rutahd:ventas.gestionar')->whereNumber('id');
    Route::post('/notas-venta/{id}/pagos',[NotaVentaControlador::class,'abonar'])->middleware('permiso.rutahd:ventas.gestionar')->whereNumber('id');

    Route::post('/inventario/traslados',[InventarioControlador::class,'trasladar'])->middleware('permiso.rutahd:inventario.gestionar');
});
