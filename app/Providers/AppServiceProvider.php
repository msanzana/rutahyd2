<?php
declare(strict_types=1);
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Repositories\Contratos\RepositorioRolInterface::class, \App\Repositories\Eloquent\RepositorioRol::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioPermisoInterface::class, \App\Repositories\Eloquent\RepositorioPermiso::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioRolPermisoInterface::class, \App\Repositories\Eloquent\RepositorioRolPermiso::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioUsuarioInterface::class, \App\Repositories\Eloquent\RepositorioUsuario::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioClienteInterface::class, \App\Repositories\Eloquent\RepositorioCliente::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioProveedorInterface::class, \App\Repositories\Eloquent\RepositorioProveedor::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioCategoriaProductoInterface::class, \App\Repositories\Eloquent\RepositorioCategoriaProducto::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioProductoInterface::class, \App\Repositories\Eloquent\RepositorioProducto::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioPresentacionProductoInterface::class, \App\Repositories\Eloquent\RepositorioPresentacionProducto::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioImagenProductoInterface::class, \App\Repositories\Eloquent\RepositorioImagenProducto::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioPrecioClienteInterface::class, \App\Repositories\Eloquent\RepositorioPrecioCliente::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioBodegaInterface::class, \App\Repositories\Eloquent\RepositorioBodega::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioSaldoInventarioInterface::class, \App\Repositories\Eloquent\RepositorioSaldoInventario::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioMovimientoInventarioInterface::class, \App\Repositories\Eloquent\RepositorioMovimientoInventario::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioPromocionInterface::class, \App\Repositories\Eloquent\RepositorioPromocion::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioDetallePromocionInterface::class, \App\Repositories\Eloquent\RepositorioDetallePromocion::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioOrdenCompraInterface::class, \App\Repositories\Eloquent\RepositorioOrdenCompra::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioDetalleOrdenCompraInterface::class, \App\Repositories\Eloquent\RepositorioDetalleOrdenCompra::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioRutaRepartoInterface::class, \App\Repositories\Eloquent\RepositorioRutaReparto::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioCotizacionInterface::class, \App\Repositories\Eloquent\RepositorioCotizacion::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioDetalleCotizacionInterface::class, \App\Repositories\Eloquent\RepositorioDetalleCotizacion::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioNotaVentaInterface::class, \App\Repositories\Eloquent\RepositorioNotaVenta::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioDetalleNotaVentaInterface::class, \App\Repositories\Eloquent\RepositorioDetalleNotaVenta::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioHistorialEstadoNotaVentaInterface::class, \App\Repositories\Eloquent\RepositorioHistorialEstadoNotaVenta::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioPagoVentaInterface::class, \App\Repositories\Eloquent\RepositorioPagoVenta::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioGuiaDespachoInterface::class, \App\Repositories\Eloquent\RepositorioGuiaDespacho::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioDetalleGuiaDespachoInterface::class, \App\Repositories\Eloquent\RepositorioDetalleGuiaDespacho::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioDocumentoEmitidoInterface::class, \App\Repositories\Eloquent\RepositorioDocumentoEmitido::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioEventoAgendaInterface::class, \App\Repositories\Eloquent\RepositorioEventoAgenda::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioMetaMensualVentaInterface::class, \App\Repositories\Eloquent\RepositorioMetaMensualVenta::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioDiaLaboralProgramadoInterface::class, \App\Repositories\Eloquent\RepositorioDiaLaboralProgramado::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioRegistroAuditoriaInterface::class, \App\Repositories\Eloquent\RepositorioRegistroAuditoria::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioVentaInterface::class, \App\Repositories\Eloquent\RepositorioVenta::class);
        $this->app->bind(\App\Repositories\Contratos\RepositorioDetalleVentaInterface::class, \App\Repositories\Eloquent\RepositorioDetalleVenta::class);
    }
    public function boot(): void {}
}
