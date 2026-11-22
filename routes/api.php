<?php
declare(strict_types=1);
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SesionControlador;
use App\Http\Controllers\PreferenciasUsuarioControlador;
use App\Http\Controllers\RecursoControlador;
use App\Http\Controllers\EntidadesComercialesControlador;
use App\Http\Controllers\VentaControlador;
use App\Http\Controllers\NotaVentaControlador;
use App\Http\Controllers\InventarioControlador;
use App\Http\Controllers\CatalogoComercialControlador;
use App\Http\Controllers\ClasificacionProductoControlador;
use App\Http\Controllers\ProductoComercialControlador;
use App\Http\Controllers\FotosProductoControlador;
use App\Http\Controllers\ListasPreciosControlador;
use App\Http\Controllers\ListasPreciosClientesControlador;
use App\Http\Controllers\ProductosProveedoresControlador;
use App\Http\Controllers\CodigosBarrasProductoControlador;
use App\Http\Controllers\PerfilesCatalogosControlador;
use App\Http\Controllers\UsuariosControlador;
use App\Http\Controllers\UbicacionesControlador;
use App\Http\Controllers\RecuperacionContrasenaControlador;

// El frontend pregunta /api/v1/estado; la ruta antigua /api/estado sigue disponible.
Route::get('/estado',fn()=>response()->json(['servicio'=>'RutaH&D2 API','estado'=>'disponible','version'=>'v2-es']));
Route::get('/v1/estado',fn()=>response()->json(['servicio'=>'Ruta H&D2 API','estado'=>'disponible','version'=>'v2-es']))->middleware('throttle:30,1');
Route::post('/v1/sesion',[SesionControlador::class,'iniciar'])->middleware('throttle:5,1');
Route::post('/v1/recuperacion/solicitar',[RecuperacionContrasenaControlador::class,'solicitar'])->middleware('throttle:5,1');
Route::post('/v1/recuperacion/restablecer',[RecuperacionContrasenaControlador::class,'restablecer'])->middleware('throttle:5,1');

Route::prefix('v1')->middleware('token.rutahd')->group(function(): void {
    // Geografía normalizada. Lecturas JWT para entidades comerciales;
    // edición de países/regiones/provincias/comunas/ciudades: solo administrador.
    Route::get('/ubicaciones/jerarquia/{ciudadId}',[UbicacionesControlador::class,'jerarquia'])
        ->whereNumber('ciudadId');
    Route::get('/ubicaciones/{tipo}',[UbicacionesControlador::class,'listar'])
        ->where('tipo','paises|regiones|provincias|comunas|ciudades');
    Route::post('/ubicaciones/{tipo}',[UbicacionesControlador::class,'crear'])
        ->where('tipo','paises|regiones|provincias|comunas|ciudades');
    Route::put('/ubicaciones/{tipo}/{id}',[UbicacionesControlador::class,'actualizar'])
        ->where('tipo','paises|regiones|provincias|comunas|ciudades')->whereNumber('id');
    Route::delete('/ubicaciones/{tipo}/{id}',[UbicacionesControlador::class,'desactivar'])
        ->where('tipo','paises|regiones|provincias|comunas|ciudades')->whereNumber('id');
    // Vista Mix de productos por proveedor: contar vínculos únicos desde SQL, sin recepciones.
    Route::get('/proveedores/mix',[ProductosProveedoresControlador::class,'mix'])
        ->middleware('permiso.rutahd:compras.gestionar')
        ->middleware('submodulo.rutahd:proveedores_productos,ver');
    Route::get('/catalogos/{catalogo}',[CatalogoComercialControlador::class,'listar']);
    Route::post('/catalogos/{catalogo}',[CatalogoComercialControlador::class,'crear']);
    Route::put('/catalogos/{catalogo}/{id}',[CatalogoComercialControlador::class,'actualizar'])->whereNumber('id');
    Route::delete('/catalogos/{catalogo}/{id}',[CatalogoComercialControlador::class,'eliminar'])->whereNumber('id');
    // CRUD de productos reales. Precio y envase residen en presentaciones_productos,
    // stock se gestiona exclusivamente en Inventario; DELETE es baja lógica.
    Route::get('/productos',[ProductoComercialControlador::class,'listar'])->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,ver');
    Route::post('/productos',[ProductoComercialControlador::class,'crear'])->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,gestionar');
    Route::get('/productos/{id}',[ProductoComercialControlador::class,'ver'])->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,ver')->whereNumber('id');
    Route::put('/productos/{id}',[ProductoComercialControlador::class,'actualizar'])->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,gestionar')->whereNumber('id');
    Route::delete('/productos/{id}',[ProductoComercialControlador::class,'desactivar'])->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,gestionar')->whereNumber('id');
    Route::get('/productos/{id}/clasificacion',[ClasificacionProductoControlador::class,'ver'])->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,ver')->whereNumber('id');
    Route::patch('/productos/{id}/clasificacion',[ClasificacionProductoControlador::class,'guardar'])->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,gestionar')->whereNumber('id');

    // Fotos de productos - solo JWT con permiso de Productos, sin enlaces públicos.
    Route::get('/productos/{productoId}/fotos',[FotosProductoControlador::class,'listar'])
        ->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,ver')->whereNumber('productoId');
    Route::get('/productos/{productoId}/fotos/{fotoId}',[FotosProductoControlador::class,'imagen'])
        ->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,ver')->whereNumber('productoId')->whereNumber('fotoId');
    Route::post('/productos/{productoId}/fotos',[FotosProductoControlador::class,'subir'])
        ->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,gestionar')
        ->middleware('throttle:12,1')->whereNumber('productoId');
    Route::delete('/productos/{productoId}/fotos/{fotoId}',[FotosProductoControlador::class,'eliminar'])
        ->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,gestionar')
        ->whereNumber('productoId')->whereNumber('fotoId');

    // Listas de precios: precio base y listas especiales. JWT + autorización backend.
    Route::get('/listas-precios',[ListasPreciosControlador::class,'listar']);
    Route::post('/listas-precios',[ListasPreciosControlador::class,'crear']);
    Route::put('/listas-precios/{id}',[ListasPreciosControlador::class,'actualizar'])->whereNumber('id');
    Route::delete('/listas-precios/{id}',[ListasPreciosControlador::class,'desactivar'])->whereNumber('id');
    Route::get('/productos/{productoId}/precios',[ListasPreciosControlador::class,'detallesProducto'])->whereNumber('productoId');
    Route::put('/productos/{productoId}/precios/{listaId}',[ListasPreciosControlador::class,'guardarDetalle'])
        ->whereNumber('productoId')->whereNumber('listaId');
    Route::delete('/productos/{productoId}/precios/{listaId}',[ListasPreciosControlador::class,'quitarDetalle'])
        ->whereNumber('productoId')->whereNumber('listaId');
    Route::get('/clientes/{clienteId}/lista-precio',[ListasPreciosClientesControlador::class,'listaCliente'])
        ->whereNumber('clienteId');
    Route::put('/clientes/{clienteId}/lista-precio',[ListasPreciosClientesControlador::class,'asignar'])
        ->whereNumber('clienteId');
    Route::get('/ventas/clientes-disponibles',[ListasPreciosClientesControlador::class,'clientesVenta']);
    Route::get('/ventas/productos-disponibles',[ListasPreciosClientesControlador::class,'productosVenta']);
    Route::get('/ventas/precio-cliente',[ListasPreciosClientesControlador::class,'cotizar']);

    // Catálogo de productos de cada proveedor. No modifica inventario, costos históricos ni recepciones.
    Route::get('/proveedores-productos',[ProductosProveedoresControlador::class,'listar'])
        ->middleware('permiso.rutahd:compras.gestionar')->middleware('submodulo.rutahd:proveedores_productos,ver');
    Route::post('/proveedores-productos',[ProductosProveedoresControlador::class,'crear'])
        ->middleware('permiso.rutahd:compras.gestionar')->middleware('submodulo.rutahd:proveedores_productos,gestionar');
    Route::get('/proveedores-productos/{id}',[ProductosProveedoresControlador::class,'ver'])
        ->middleware('permiso.rutahd:compras.gestionar')->middleware('submodulo.rutahd:proveedores_productos,ver')->whereNumber('id');
    Route::put('/proveedores-productos/{id}',[ProductosProveedoresControlador::class,'actualizar'])
        ->middleware('permiso.rutahd:compras.gestionar')->middleware('submodulo.rutahd:proveedores_productos,gestionar')->whereNumber('id');
    Route::delete('/proveedores-productos/{id}',[ProductosProveedoresControlador::class,'desactivar'])
        ->middleware('permiso.rutahd:compras.gestionar')->middleware('submodulo.rutahd:proveedores_productos,gestionar')->whereNumber('id');

    // Cada relación proveedor-producto posee códigos de identificación de compra.
    Route::get('/proveedores-productos/{vinculoId}/codigos-compra',[CodigosBarrasProductoControlador::class,'listarCompra'])
        ->middleware('permiso.rutahd:compras.gestionar')->middleware('submodulo.rutahd:proveedores_productos,ver')->whereNumber('vinculoId');
    Route::post('/proveedores-productos/{vinculoId}/codigos-compra',[CodigosBarrasProductoControlador::class,'crearCompra'])
        ->middleware('permiso.rutahd:compras.gestionar')->middleware('submodulo.rutahd:proveedores_productos,gestionar')->whereNumber('vinculoId');
    Route::put('/proveedores-productos/{vinculoId}/codigos-compra/{id}',[CodigosBarrasProductoControlador::class,'actualizarCompra'])
        ->middleware('permiso.rutahd:compras.gestionar')->middleware('submodulo.rutahd:proveedores_productos,gestionar')->whereNumber('vinculoId')->whereNumber('id');
    Route::delete('/proveedores-productos/{vinculoId}/codigos-compra/{id}',[CodigosBarrasProductoControlador::class,'desactivarCompra'])
        ->middleware('permiso.rutahd:compras.gestionar')->middleware('submodulo.rutahd:proveedores_productos,gestionar')->whereNumber('vinculoId')->whereNumber('id');

    // Venta: producto + tipo EAN/DUN/CODE + código y unidades. Nunca hay proveedor.
    Route::get('/productos/{productoId}/codigos-venta',[CodigosBarrasProductoControlador::class,'listarVenta'])
        ->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,ver')->whereNumber('productoId');
    Route::post('/productos/{productoId}/codigos-venta',[CodigosBarrasProductoControlador::class,'crearVenta'])
        ->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,gestionar')->whereNumber('productoId');
    Route::put('/productos/{productoId}/codigos-venta/{id}',[CodigosBarrasProductoControlador::class,'actualizarVenta'])
        ->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,gestionar')->whereNumber('productoId')->whereNumber('id');
    Route::delete('/productos/{productoId}/codigos-venta/{id}',[CodigosBarrasProductoControlador::class,'desactivarVenta'])
        ->middleware('permiso.rutahd:productos.gestionar')->middleware('submodulo.rutahd:productos_listado,gestionar')->whereNumber('productoId')->whereNumber('id');

    Route::get('/perfiles/catalogos',[PerfilesCatalogosControlador::class,'listar']);
    Route::get('/perfiles/usuarios',[PerfilesCatalogosControlador::class,'usuarios']);
    Route::get('/usuarios',[UsuariosControlador::class,'index']);
    Route::post('/usuarios',[UsuariosControlador::class,'crear']);
    Route::put('/usuarios/{id}',[UsuariosControlador::class,'actualizar'])->whereNumber('id');
    Route::delete('/usuarios/{id}',[UsuariosControlador::class,'eliminar'])->whereNumber('id');
    Route::put('/perfiles/usuarios/{id}/rol',[PerfilesCatalogosControlador::class,'asignarPerfil'])->whereNumber('id');
    Route::put('/perfiles/{id}/catalogos',[PerfilesCatalogosControlador::class,'actualizar'])->whereNumber('id');
    Route::get('/perfil',[SesionControlador::class,'perfil']);
    Route::get('/perfil/preferencias',[PreferenciasUsuarioControlador::class,'ver']);
    Route::put('/perfil/preferencias',[PreferenciasUsuarioControlador::class,'guardar']);
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
