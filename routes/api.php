<?php
declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SesionControlador;
use App\Http\Controllers\RecursoControlador;
use App\Http\Controllers\EntidadesComercialesControlador;
use App\Http\Controllers\VentaControlador;
use App\Http\Controllers\NotaVentaControlador;
use App\Http\Controllers\InventarioControlador;
use App\Http\Controllers\CatalogoComercialControlador;
use App\Http\Controllers\ClasificacionProductoControlador;
use App\Http\Controllers\ProductoComercialControlador;
use App\Http\Controllers\PerfilesCatalogosControlador;
use App\Http\Controllers\UsuariosControlador;
use App\Http\Controllers\RecuperacionContrasenaControlador;

// El frontend pregunta /api/v1/estado; la ruta antigua /api/estado sigue disponible.
Route::get('/estado',fn()=>response()->json(['servicio'=>'RutaH&D2 API','estado'=>'disponible','version'=>'v2-es']));
Route::get('/v1/estado',fn()=>response()->json(['servicio'=>'Ruta H&D2 API','estado'=>'disponible','version'=>'v2-es']))->middleware('throttle:30,1');
Route::post('/v1/sesion',[SesionControlador::class,'iniciar'])->middleware('throttle:5,1');
Route::post('/v1/recuperacion/solicitar',[RecuperacionContrasenaControlador::class,'solicitar'])->middleware('throttle:5,1');
Route::post('/v1/recuperacion/restablecer',[RecuperacionContrasenaControlador::class,'restablecer'])->middleware('throttle:5,1');

Route::prefix('v1')->middleware('token.rutahd')->group(function(): void {
    Route::get('/catalogos/{catalogo}',[CatalogoComercialControlador::class,'listar']);
    Route::post('/catalogos/{catalogo}',[CatalogoComercialControlador::class,'crear']);
    Route::put('/catalogos/{catalogo}/{id}',[CatalogoComercialControlador::class,'actualizar'])->whereNumber('id');
    Route::delete('/catalogos/{catalogo}/{id}',[CatalogoComercialControlador::class,'eliminar'])->whereNumber('id');
    // CRUD de productos reales. Precio y envase residen en presentaciones_productos,
    // stock se gestiona exclusivamente en Inventario; DELETE es baja lógica.
    Route::get('/productos',[ProductoComercialControlador::class,'listar'])->middleware('permiso.rutahd:productos.gestionar');
    Route::post('/productos',[ProductoComercialControlador::class,'crear'])->middleware('permiso.rutahd:productos.gestionar');
    Route::get('/productos/{id}',[ProductoComercialControlador::class,'ver'])->middleware('permiso.rutahd:productos.gestionar')->whereNumber('id');
    Route::put('/productos/{id}',[ProductoComercialControlador::class,'actualizar'])->middleware('permiso.rutahd:productos.gestionar')->whereNumber('id');
    Route::delete('/productos/{id}',[ProductoComercialControlador::class,'desactivar'])->middleware('permiso.rutahd:productos.gestionar')->whereNumber('id');
    Route::get('/productos/{id}/clasificacion',[ClasificacionProductoControlador::class,'ver'])->middleware('permiso.rutahd:productos.gestionar')->whereNumber('id');
    Route::patch('/productos/{id}/clasificacion',[ClasificacionProductoControlador::class,'guardar'])->middleware('permiso.rutahd:productos.gestionar')->whereNumber('id');
    Route::get('/perfiles/catalogos',[PerfilesCatalogosControlador::class,'listar']);
    Route::get('/perfiles/usuarios',[PerfilesCatalogosControlador::class,'usuarios']);
    Route::get('/usuarios',[UsuariosControlador::class,'index']);
    Route::post('/usuarios',[UsuariosControlador::class,'crear']);
    Route::put('/usuarios/{id}',[UsuariosControlador::class,'actualizar'])->whereNumber('id');
    Route::delete('/usuarios/{id}',[UsuariosControlador::class,'eliminar'])->whereNumber('id');
    Route::put('/perfiles/usuarios/{id}/rol',[PerfilesCatalogosControlador::class,'asignarPerfil'])->whereNumber('id');
    Route::put('/perfiles/{id}/catalogos',[PerfilesCatalogosControlador::class,'actualizar'])->whereNumber('id');
    Route::get('/perfil',[SesionControlador::class,'perfil']);
    Route::delete('/sesion',[SesionControlador::class,'salir']);
    // Consultas paginadas solo de recursos admitidos y con permiso por rol.
    Route::get('/recursos/{recurso}',[RecursoControlador::class,'listar']);
    Route::get('/recursos/{recurso}/{id}',[RecursoControlador::class,'ver'])->whereNumber('id');
    // Escrituras permitidas solo para clientes, proveedores y bodegas.
    // Nunca crear/eliminar productos, inventario, ventas u órdenes mediante una ruta genérica.
    Route::post('/recursos/{recurso}',[EntidadesComercialesControlador::class,'crear'])
        ->where('recurso','clientes|proveedores|bodegas');
    Route::put('/recursos/{recurso}/{id}',[EntidadesComercialesControlador::class,'actualizar'])
        ->where('recurso','clientes|proveedores|bodegas')->whereNumber('id');
    Route::delete('/recursos/{recurso}/{id}',[EntidadesComercialesControlador::class,'desactivar'])
        ->where('recurso','clientes|proveedores|bodegas')->whereNumber('id');

    Route::get('/ventas/{id}/detalles',[VentaControlador::class,'detalles'])->middleware('permiso.rutahd:ventas.ver')->whereNumber('id');
    Route::post('/ventas',[VentaControlador::class,'guardar'])->middleware('permiso.rutahd:ventas.gestionar');
    Route::post('/ventas/{id}/preparar-dte',[VentaControlador::class,'prepararDte'])->middleware('permiso.rutahd:ventas.gestionar')->whereNumber('id');

    Route::post('/notas-venta',[NotaVentaControlador::class,'guardar'])->middleware('permiso.rutahd:ventas.gestionar');
    Route::post('/notas-venta/{id}/estado',[NotaVentaControlador::class,'cambiarEstado'])->middleware('permiso.rutahd:ventas.gestionar')->whereNumber('id');
    Route::post('/notas-venta/{id}/pagos',[NotaVentaControlador::class,'abonar'])->middleware('permiso.rutahd:ventas.gestionar')->whereNumber('id');

    Route::post('/inventario/traslados',[InventarioControlador::class,'trasladar'])->middleware('permiso.rutahd:inventario.gestionar');
});
