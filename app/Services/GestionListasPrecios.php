<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reemplaza TODOS los triggers de listas de precios.
 *
 * Las escrituras se llaman desde los controladores dentro de la misma
 * transacción del alta/cambio de cliente o producto. No usa observers,
 * eventos Eloquent ni procedimientos SQL.
 *
 * No desplegar hasta haber importado y validado DDL24.
 */
final class GestionListasPrecios
{
    public function listaBaseId(): int
    {
        $lista = DB::table('listas_precios')
            ->where('es_base', 1)->where('activo', 1)
            ->lockForUpdate()->first(['id']);
        abort_unless($lista, 409, 'Debe existir una LISTA BASE activa');
        return (int)$lista->id;
    }

    /**
     * Sustituye AFTER INSERT clientes: asignación de Lista Base.
     * Una fila por cliente, sin sobrescribir una lista especial ya elegida.
     */
    public function asignarListaBaseCliente(int $clienteId): void
    {
        $cliente = DB::table('clientes')->where('id', $clienteId)
            ->lockForUpdate()->first(['id']);
        abort_unless($cliente, 404, 'Cliente inexistente');
        $listaId = $this->listaBaseId();
        DB::table('clientes_listas_precios')->insertOrIgnore([
            'cliente_id' => $clienteId,
            'lista_precio_id' => $listaId,
            'activo' => 1,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);
    }

    /**
     * Sustituye AFTER INSERT productos: incorpora precio general existente.
     * Sin costo conocido, margen queda NULL (nunca dividir entre cero).
     */
    public function asegurarPrecioBaseProducto(int $productoId, ?string $precioNeto = null): void
    {
        $producto = DB::table('productos')->where('id', $productoId)
            ->lockForUpdate()->first(['id', 'sku', 'costo_neto', 'activo']);
        abort_unless($producto, 404, 'Producto inexistente');
        $listaId = $this->listaBaseId();
        $precio = $precioNeto;
        if ($precio === null) {
            $precio = DB::table('presentaciones_productos')
                ->where('producto_id', $productoId)->orderBy('id')->value('precio_neto');
        }
        $precio = max(0, round((float)($precio ?? 0), 4));
        $costo = max(0, round((float)$producto->costo_neto, 4));
        $margen = $costo > 0 && $precio >= $costo
            ? round((($precio - $costo) / $costo) * 100, 4)
            : null;
        DB::table('listas_precios_detalle')->insertOrIgnore([
            'lista_precio_id' => $listaId,
            'producto_id' => $productoId,
            'sku' => $producto->sku,
            'costo' => $costo,
            'margen_porcentaje' => $margen,
            'precio_venta' => $precio,
            'activo' => (int)$producto->activo,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);
    }

    /**
     * Sustituye AFTER UPDATE productos: sincroniza el SKU en TODAS sus listas.
     * Debe ejecutarse dentro de la transacción que cambió productos.sku.
     */
    public function sincronizarSkuProducto(int $productoId, string $sku): void
    {
        DB::table('listas_precios_detalle')
            ->where('producto_id', $productoId)
            ->where(function ($q) use ($sku) {
                $q->whereNull('sku')->orWhere('sku', '!=', $sku);
            })->update(['sku' => $sku, 'actualizado_en' => now()]);
    }

    /**
     * Reemplaza lista asignada al cliente (BASE o una especial).
     * La PK cliente_id impide registrar simultáneamente dos especiales.
     * Nunca aplica fallback silencioso a BASE cuando faltan precios especiales.
     */
    public function asignarListaCliente(int $clienteId, int $listaId): void
    {
        DB::transaction(function () use ($clienteId, $listaId): void {
            abort_unless(DB::table('clientes')->where('id', $clienteId)
                ->lockForUpdate()->exists(), 404, 'Cliente inexistente');
            abort_unless(DB::table('listas_precios')->where('id', $listaId)
                ->where('activo', 1)->exists(), 422, 'Lista de precios inexistente o inactiva');
            DB::table('clientes_listas_precios')->updateOrInsert(
                ['cliente_id' => $clienteId],
                ['lista_precio_id' => $listaId, 'activo' => 1, 'actualizado_en' => now()]
            );
        }, 3);
    }

    /**
     * Reemplaza la lógica BEFORE INSERT/UPDATE de unicidad de Lista Base.
     * Además de este control, DDL24 usa UNIQUE de clave generada para
     * proteger concurrencia desde varias solicitudes.
     */
    public function crearLista(string $codigo, string $nombre, bool $esBase = false): int
    {
        return DB::transaction(function () use ($codigo, $nombre, $esBase): int {
            $codigo = trim($codigo);
            $nombre = trim($nombre);
            abort_if($codigo === '' || $nombre === '', 422, 'Código y nombre son obligatorios');
            abort_if(mb_strlen($codigo) > 40 || mb_strlen($nombre) > 120,
                422, 'Código o nombre demasiado largo');
            abort_if(!$esBase && strtoupper($codigo) === 'BASE', 422,
                'El código BASE está reservado');
            if ($esBase) {
                abort_if(strtoupper($codigo) !== 'BASE', 422,
                    'La lista base debe utilizar el código BASE');
                abort_if(DB::table('listas_precios')->where('es_base', 1)
                    ->lockForUpdate()->exists(), 409, 'Ya existe la lista base');
            }
            return (int)DB::table('listas_precios')->insertGetId([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'es_base' => $esBase ? 1 : 0,
                'activo' => 1,
                'creado_en' => now(),
                'actualizado_en' => now(),
            ]);
        }, 3);
    }

    /**
     * Sustituye BEFORE UPDATE listas_precios: protege la Lista Base y
     * evita cambiar una lista especial a base sin pasar por la validación.
     */
    public function actualizarLista(int $listaId, array $cambios): array
    {
        return DB::transaction(function () use ($listaId, $cambios): array {
            $permitidos=['codigo','nombre','observacion','activo','es_base'];
            abort_if(array_diff(array_keys($cambios), $permitidos)!==[],
                422, 'Campos de lista de precios no permitidos');
            abort_if($cambios===[],422,'No se proporcionaron cambios');
            $actual=DB::table('listas_precios')->where('id',$listaId)
                ->lockForUpdate()->first();
            abort_unless($actual,404,'Lista de precios inexistente');
            $base=(bool)$actual->es_base;
            if(array_key_exists('es_base',$cambios)){
                abort_unless((bool)$cambios['es_base']===$base,422,
                    'No se puede cambiar el tipo base/especial de una lista existente');
            }
            if(array_key_exists('activo',$cambios) && $base){
                abort_unless((bool)$cambios['activo'],422,
                    'La Lista Base no puede desactivarse');
            }
            if(array_key_exists('codigo',$cambios)){
                $codigo=trim((string)$cambios['codigo']);
                abort_if($codigo===''||mb_strlen($codigo)>40,422,'Código inválido');
                abort_if($base && strtoupper($codigo)!=='BASE',422,
                    'La Lista Base conserva el código BASE');
                abort_if(!$base && strtoupper($codigo)==='BASE',422,
                    'El código BASE está reservado');
                $cambios['codigo']=$codigo;
            }
            if(array_key_exists('nombre',$cambios)){
                $nombre=trim((string)$cambios['nombre']);
                abort_if($nombre===''||mb_strlen($nombre)>120,422,'Nombre inválido');
                $cambios['nombre']=$nombre;
            }
            if(array_key_exists('observacion',$cambios)){
                $nota=$cambios['observacion'];
                abort_if($nota!==null &&
                    (!is_string($nota)||mb_strlen($nota)>255),422,
                    'Observación demasiado larga');
            }
            unset($cambios['es_base']);
            if($cambios!==[]){
                $cambios['actualizado_en']=now();
                DB::table('listas_precios')->where('id',$listaId)->update($cambios);
            }
            return (array)DB::table('listas_precios')->where('id',$listaId)->first();
        }, 3);
    }

    /**
     * Calcula precio desde margen, O margen desde precio (porcentaje sobre costo).
     * No acepta valores contradictorios; exige mínimo de la línea del producto.
     */
    public function guardarPrecioLista(
        int $productoId,
        int $listaId,
        float $costo,
        ?float $precioVenta,
        ?float $margenPorcentaje,
        bool $activo = true
    ): array {
        return DB::transaction(function () use (
            $productoId, $listaId, $costo, $precioVenta, $margenPorcentaje, $activo
        ): array {
            $p = DB::table('productos')->where('id', $productoId)
                ->lockForUpdate()->first(['id','sku','linea_id']);
            abort_unless($p, 404, 'Producto inexistente');
            abort_unless(DB::table('listas_precios')->where('id',$listaId)
                ->where('activo',1)->exists(), 422, 'Lista inexistente o inactiva');

            $costo = round($costo, 4);
            abort_if(!is_finite($costo) || $costo < 0, 422, 'Costo inválido');
            abort_if($precioVenta !== null && (!is_finite($precioVenta) || $precioVenta < 0),
                422, 'Precio de venta inválido');
            abort_if($margenPorcentaje !== null &&
                (!is_finite($margenPorcentaje) || $margenPorcentaje < 0),
                422, 'Margen porcentual inválido');

            $minimo = (float)(DB::table('lineas')
                ->where('id',$p->linea_id)->value('minimo_margen_precio_venta') ?? 0);
            if ($costo == 0.0) {
                abort_if(($precioVenta !== null && $precioVenta > 0) ||
                    ($margenPorcentaje !== null && $margenPorcentaje > 0),
                    422, 'Ingresa un costo mayor que cero antes de calcular el margen');
                $precioVenta = 0.0;
                $margenPorcentaje = null;
            } elseif ($margenPorcentaje !== null && $precioVenta === null) {
                $precioVenta = round($costo * (1 + $margenPorcentaje / 100), 4);
            } elseif ($precioVenta !== null && $margenPorcentaje === null) {
                $margenPorcentaje = round((($precioVenta - $costo) / $costo) * 100, 4);
            } elseif ($margenPorcentaje !== null && $precioVenta !== null) {
                $calculado = round($costo * (1 + $margenPorcentaje / 100), 4);
                abort_if(abs($precioVenta - $calculado) > 0.01, 422,
                    'Precio y margen no coinciden con el costo');
            } else {
                throw ValidationException::withMessages([
                    'precio_venta' => 'Ingresa margen o precio de venta'
                ]);
            }
            if ($costo > 0) {
                abort_if($margenPorcentaje < $minimo, 422,
                    'El margen está por debajo del mínimo de la línea: '.$minimo.'%');
            }
            abort_if($precioVenta > 99999999999, 422, 'El precio supera el límite permitido');
            DB::table('listas_precios_detalle')->updateOrInsert(
                ['lista_precio_id'=>$listaId, 'producto_id'=>$productoId],
                ['sku'=>$p->sku, 'costo'=>$costo,
                 'margen_porcentaje'=>$margenPorcentaje,
                 'precio_venta'=>$precioVenta, 'activo'=>$activo ? 1 : 0,
                 'actualizado_en'=>now()]
            );
            return ['producto_id'=>$productoId,'lista_precio_id'=>$listaId,
                'costo'=>$costo,'margen_porcentaje'=>$margenPorcentaje,
                'precio_venta'=>$precioVenta,'activo'=>$activo];
        }, 3);
    }

    /**
     * Para ventas: la lista del cliente es EXCLUSIVA, sin fallback a base.
     * Si el producto falta en una especial, la venta debe informarlo.
     */
    public function precioEfectivoCliente(int $clienteId, int $productoId): object
    {
        $fila = DB::table('clientes_listas_precios as clp')
            ->join('listas_precios as lp','lp.id','=','clp.lista_precio_id')
            ->join('listas_precios_detalle as d','d.lista_precio_id','=','lp.id')
            ->where('clp.cliente_id',$clienteId)
            ->where('d.producto_id',$productoId)
            ->where('clp.activo',1)->where('lp.activo',1)->where('d.activo',1)
            ->first(['lp.id as lista_precio_id','lp.nombre as lista_precio_nombre',
                     'd.precio_venta','d.costo','d.margen_porcentaje']);
        abort_unless($fila, 422, 'El producto no tiene precio vigente en la lista del cliente');
        return $fila;
    }
}
