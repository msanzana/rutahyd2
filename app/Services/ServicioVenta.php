<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Venta;
use App\Repositories\Contratos\RepositorioVentaInterface;
use App\Repositories\Contratos\RepositorioDetalleVentaInterface;
use App\Repositories\Contratos\RepositorioProductoInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
/**
 * Registro transaccional de la venta comercial. Los totales son provisionales:
 * validar con politica tributaria y redondeo del servidor DTE antes de emitir.
 */
final class ServicioVenta
{
    public function __construct(
        private RepositorioVentaInterface $ventas,
        private RepositorioDetalleVentaInterface $detalles,
        private RepositorioProductoInterface $productos,
        private GestionListasPrecios $listasPrecios
    ) {}
    public function crear(array $datos,int $usuarioId): Venta
    {
        return DB::transaction(function() use ($datos,$usuarioId): Venta {
            $numero=$datos['numero_venta']??('VEN-'.now()->format('Ymd').'-'.strtoupper(substr((string)Str::ulid(),-12)));
            if ($this->ventas->buscarPor('numero_venta',$numero)) {
                throw ValidationException::withMessages(['numero_venta'=>'El numero de venta ya existe']);
            }
            $neto=0.0;$exento=0.0;$impuestos=0.0;$descuentoTotal=0.0;
            $lineas=[];
            foreach($datos['detalles'] as $i=>$linea) {
                $producto=$this->productos->buscar((int)$linea['producto_id']);
                if(!$producto || !$producto->activo) {
                    throw ValidationException::withMessages(['detalles'=>"Producto inexistente o inactivo en linea ".($i+1)]);
                }
                // El precio lo decide la lista exclusiva del cliente, no un
                // número enviado libremente por el navegador.
                $tarifa=$this->listasPrecios->precioEfectivoCliente(
                    (int)$datos['cliente_id'],(int)$linea['producto_id']);
                $precio=(float)$tarifa->precio_venta;
                if(abs((float)$linea['precio_unitario']-$precio)>0.01){
                    throw ValidationException::withMessages([
                        'detalles'=> 'El precio de la línea '.($i+1).
                            ' no coincide con la lista asignada al cliente'
                    ]);
                }
                $cantidad=(float)$linea['cantidad'];
                if($cantidad>1000000 || $precio>1000000000) {
                    throw ValidationException::withMessages(['detalles'=>'Cantidad o precio excede limites de seguridad']);
                }
                $bruto=round($cantidad*$precio,2);
                $pct=(float)($linea['porcentaje_descuento']??0);
                $fijo=(float)($linea['monto_descuento']??0);
                $descuento=round(max($bruto*$pct/100,$fijo),2);
                if($descuento>$bruto+0.001) throw ValidationException::withMessages(['detalles'=>'Descuento supera importe de la linea']);
                $subtotal=round($bruto-$descuento,2);
                $esExento=(bool)($linea['exento_impuestos']??false);
                if($esExento)$exento+=$subtotal; else $neto+=$subtotal;
                $descuentoTotal+=$descuento;
                $lineas[]=[
                    'numero_linea'=>$i+1,'producto_id'=>$producto->id,
                    'cantidad'=>$linea['cantidad'],'precio_unitario'=>$precio,
                    'porcentaje_descuento'=>$pct,'monto_descuento'=>$descuento,
                    'exento_impuestos'=>$esExento,'monto_linea'=>$subtotal,
                    'datos_adicionales_json'=>$linea['datos_adicionales_json']??null
                ];
            }
            $impuestos=round($neto*0.19,2);
            $venta=$this->ventas->crear([
                'nota_venta_id'=>$datos['nota_venta_id']??null,
                'cliente_id'=>$datos['cliente_id'],
                'numero_venta'=>$numero,
                'estado'=>'borrador','fecha_emision'=>$datos['fecha_emision'],
                'tipo_dte'=>$datos['tipo_dte'],
                'dte_id_externo'=>(string)Str::uuid(),
                'dte_estado'=>'no_solicitado',
                'documento_receptor'=>$datos['documento_receptor'],
                'nombre_receptor'=>$datos['nombre_receptor'],
                'giro_receptor'=>$datos['giro_receptor']??null,
                'direccion_receptor'=>$datos['direccion_receptor']??null,
                'ciudad_receptor_id'=>$datos['ciudad_receptor_id']??null,
                'correo_receptor'=>$datos['correo_receptor']??null,
                'datos_encabezado_json'=>$datos['datos_encabezado_json']??[],
                'datos_referencias_json'=>$datos['datos_referencias_json']??[],
                'monto_neto'=>round($neto,2),'monto_exento'=>round($exento,2),
                'monto_impuestos'=>$impuestos,'monto_descuento'=>round($descuentoTotal,2),
                'monto_total'=>round($neto+$exento+$impuestos,2),
                'observaciones'=>$datos['observaciones']??null,'creado_por'=>$usuarioId
            ]);
            foreach($lineas as $linea) $this->detalles->crear(['venta_id'=>$venta->id]+$linea);
            return $venta;
        },3);
    }
}
