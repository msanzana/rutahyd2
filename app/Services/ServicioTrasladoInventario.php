<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\Contratos\RepositorioSaldoInventarioInterface;
use App\Repositories\Contratos\RepositorioGuiaDespachoInterface;
use App\Repositories\Contratos\RepositorioDetalleGuiaDespachoInterface;
use App\Repositories\Contratos\RepositorioMovimientoInventarioInterface;
use App\Repositories\Contratos\RepositorioPresentacionProductoInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class ServicioTrasladoInventario
{
    public function __construct(
      private RepositorioSaldoInventarioInterface $saldos,
      private RepositorioGuiaDespachoInterface $guias,
      private RepositorioDetalleGuiaDespachoInterface $detalles,
      private RepositorioMovimientoInventarioInterface $movimientos,
      private RepositorioPresentacionProductoInterface $presentaciones
    ) {}
    public function trasladar(array $datos,int $usuarioId): int
    {
        return DB::transaction(function()use($datos,$usuarioId): int {
            $existe=$this->guias->buscarPor('folio_interno',$datos['folio_interno']);
            if($existe)return (int)$existe->id;
            $origen=(int)$datos['bodega_origen_id'];$destino=(int)$datos['bodega_destino_id'];
            if($origen===$destino)throw ValidationException::withMessages(['bodega_destino_id'=>'Bodegas idénticas']);
            $items=$datos['detalles'];
            usort($items,fn($a,$b)=>(int)$a['presentacion_id']<=>(int)$b['presentacion_id']);
            $bodegas=[$origen,$destino];sort($bodegas,SORT_NUMERIC);
            $vistos=[];$procesados=[];$total=0.;
            foreach($items as $item){
                $presentacionId=(int)$item['presentacion_id'];
                $cantidad=round((float)$item['cantidad'],3);
                if(isset($vistos[$presentacionId]) || $cantidad<=0)
                    throw ValidationException::withMessages(['detalles'=>'Presentaciones duplicadas o cantidad inválida']);
                $vistos[$presentacionId]=true;
                $p=$this->presentaciones->buscar($presentacionId);
                if(!$p || !$p->activo)throw ValidationException::withMessages(['detalles'=>'Presentación no disponible']);
                // Orden único de bloqueo: id de presentación, luego id de bodega.
                $bloqueados=[];
                foreach($bodegas as $b)$bloqueados[$b]=(float)$this->saldos->cantidadBloqueada($b,$presentacionId);
                if($bloqueados[$origen]+0.00001<$cantidad)
                    throw ValidationException::withMessages(['detalles'=>'Stock insuficiente']);
                $this->saldos->fijarCantidad($origen,$presentacionId,(string)round($bloqueados[$origen]-$cantidad,3));
                $this->saldos->fijarCantidad($destino,$presentacionId,(string)round($bloqueados[$destino]+$cantidad,3));
                $importe=round($cantidad*(float)$p->precio_neto,2);
                $total+=$importe;
                $procesados[]=['presentacion_id'=>$presentacionId,
                    'cantidad'=>$cantidad,'precio_unitario_neto'=>$p->precio_neto,'subtotal_neto'=>$importe];
            }
            $guia=$this->guias->crear([
                'folio_interno'=>$datos['folio_interno'],
                'bodega_origen_id'=>$origen,'bodega_destino_id'=>$destino,
                'nota_venta_id'=>$datos['nota_venta_id']??null,
                'total_neto'=>round($total,2),'estado'=>'generada','creado_por'=>$usuarioId,
            ]);
            foreach($procesados as $p){
                $this->detalles->crear(['guia_despacho_id'=>$guia->id]+$p);
                $this->movimientos->crear([
                    'presentacion_id'=>$p['presentacion_id'],
                    'bodega_origen_id'=>$origen,'bodega_destino_id'=>$destino,
                    'cantidad'=>$p['cantidad'],'tipo_movimiento'=>'traslado',
                    'tipo_referencia'=>'guia_despacho','referencia_id'=>$guia->id,'realizado_por'=>$usuarioId,
                ]);
            }
            return (int)$guia->id;
        },3);
    }
}
