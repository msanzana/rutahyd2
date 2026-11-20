<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\Contratos\RepositorioNotaVentaInterface;
use App\Repositories\Contratos\RepositorioDetalleNotaVentaInterface;
use App\Repositories\Contratos\RepositorioPresentacionProductoInterface;
use App\Repositories\Contratos\RepositorioHistorialEstadoNotaVentaInterface;
use App\Repositories\Contratos\RepositorioPagoVentaInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
final class ServicioNotaVenta
{
    public function __construct(
        private RepositorioNotaVentaInterface $notas,
        private RepositorioDetalleNotaVentaInterface $detalles,
        private RepositorioPresentacionProductoInterface $presentaciones,
        private RepositorioHistorialEstadoNotaVentaInterface $historial,
        private RepositorioPagoVentaInterface $pagos
    ) {}
    public function crear(array $datos,int $usuarioId): int
    {
        return DB::transaction(function() use($datos,$usuarioId): int {
            $numero=$datos['numero']??('NV-'.now()->format('Ymd').'-'.strtoupper(substr((string)Str::ulid(),-12)));
            if($this->notas->buscarPor('numero',$numero))throw ValidationException::withMessages(['numero'=>'Número repetido']);
            $neto=0.;$impuesto=0.;$lineas=[];
            foreach($datos['detalles'] as $detalle){
                $presentacion=$this->presentaciones->buscar((int)$detalle['presentacion_id']);
                if(!$presentacion || !$presentacion->activo)throw ValidationException::withMessages(['detalles'=>'Presentación inexistente o inactiva']);
                $cantidad=(float)$detalle['cantidad_solicitada'];
                $monto=round($cantidad*(float)$presentacion->precio_neto,2);
                $neto+=$monto;
                $impuesto+=round($monto*(float)$presentacion->tasa_iva/100,2);
                $lineas[]=['presentacion_id'=>$presentacion->id,
                  'cantidad_solicitada'=>$detalle['cantidad_solicitada'],
                  'cantidad_entregada'=>0,'precio_unitario_neto'=>$presentacion->precio_neto,
                  'porcentaje_descuento'=>0,'subtotal_neto'=>$monto];
            }
            $nota=$this->notas->crear([
                'numero'=>$numero,'cliente_id'=>$datos['cliente_id'],
                'usuario_vendedor_id'=>$usuarioId,
                'ruta_reparto_id'=>$datos['ruta_reparto_id']??null,
                'entrega_programada_en'=>$datos['entrega_programada_en']??null,
                'direccion_entrega'=>$datos['direccion_entrega']??null,
                'observaciones'=>$datos['observaciones']??null,
                'estado'=>'recibida','estado_pago'=>'pendiente',
                'subtotal_neto'=>round($neto,2),'total_descuentos'=>0,
                'total_impuestos'=>round($impuesto,2),'total_bruto'=>round($neto+$impuesto,2)
            ]);
            foreach($lineas as $l)$this->detalles->crear(['nota_venta_id'=>$nota->id]+$l);
            $this->historial->crear(['nota_venta_id'=>$nota->id,'estado_nuevo'=>'recibida','modificado_por'=>$usuarioId]);
            return (int)$nota->id;
        },3);
    }
    public function cambiarEstado(int $notaId,string $nuevo,int $usuarioId): void
    {
        $transiciones=[
          'recibida'=>['por_preparar','rechazada'],
          'por_preparar'=>['lista_despacho','rechazada'],
          'lista_despacho'=>['pendiente_entrega','rechazada'],
          'pendiente_entrega'=>['entregada','rechazada'],
          'entregada'=>['completada'],'completada'=>[],'rechazada'=>[]
        ];
        DB::transaction(function()use($notaId,$nuevo,$usuarioId,$transiciones): void {
            $nota=$this->notas->bloquear($notaId);
            if(!$nota)abort(404);
            if(!in_array($nuevo,$transiciones[$nota->estado]??[],true))
                throw ValidationException::withMessages(['estado'=>'Cambio de estado no permitido']);
            $anterior=$nota->estado;
            $this->notas->actualizar($notaId,['estado'=>$nuevo]);
            $this->historial->crear(['nota_venta_id'=>$notaId,'estado_anterior'=>$anterior,
                'estado_nuevo'=>$nuevo,'modificado_por'=>$usuarioId]);
        },3);
    }
    public function abonar(int $notaId,array $datos,int $usuarioId): int
    {
        return DB::transaction(function()use($notaId,$datos,$usuarioId): int {
            $nota=$this->notas->bloquear($notaId);
            if(!$nota)abort(404);
            if(($datos['referencia']??null)!==null){
                $anterior=DB::table('pagos_ventas')->where('nota_venta_id',$notaId)
                   ->where('referencia',$datos['referencia'])->first();
                if($anterior)return (int)$anterior->id;
            }
            $pagado=(float)DB::table('pagos_ventas')->where('nota_venta_id',$notaId)->sum('monto_bruto');
            $nuevo=round((float)$datos['monto_bruto'],2);
            if($pagado+$nuevo>(float)$nota->total_bruto+0.001)throw ValidationException::withMessages(['monto_bruto'=>'Abono supera el saldo']);
            $pago=$this->pagos->crear([
              'nota_venta_id'=>$notaId,'monto_bruto'=>$nuevo,'medio_pago'=>$datos['medio_pago'],
              'pagado_en'=>now(),'referencia'=>$datos['referencia']??null,'registrado_por'=>$usuarioId,
            ]);
            $this->notas->actualizar($notaId,[
              'estado_pago'=>abs($pagado+$nuevo-(float)$nota->total_bruto)<0.01?'pagada':'parcial'
            ]);
            return (int)$pago->id;
        },3);
    }
}
