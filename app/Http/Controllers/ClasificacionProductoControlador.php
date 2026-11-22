<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Actualiza SOLO clasificación de productos existentes; no toca precio,
 * stock, IVA de presentaciones, ventas ni categoría heredada V1.
 */
final class ClasificacionProductoControlador extends Controller
{
    private const CAMPOS = [
        'rubroId' => ['rubros', 'rubro_id'],
        'departamentoId' => ['departamentos', 'departamento_id'],
        'familiaId' => ['familias', 'familia_id'],
        'lineaId' => ['lineas', 'linea_id'],
        'marcaId' => ['marcas', 'marca_id'],
        'modeloId' => ['modelos', 'modelo_id'],
        'tipoUnidadId' => ['tipos_unidad', 'tipo_unidad_id'],
        'tipoEnvasadoId' => ['tipos_envasado', 'tipo_envasado_id'],
        'condicionTributariaId' => ['condiciones_tributarias', 'condicion_tributaria_id'],
    ];

    public function ver(int $id)
    {
        $producto = DB::table('productos')->where('id', $id)->first();
        abort_unless($producto, 404, 'Producto inexistente');
        $clasificacion = [];
        foreach (self::CAMPOS as $campo => [, $columna]) {
            $clasificacion[$campo] = $producto->{$columna} === null
                ? null : (int) $producto->{$columna};
        }
        return response()->json([
            'id' => (int) $producto->id,
            'sku' => $producto->sku,
            'nombre' => $producto->nombre,
            'clasificacion' => $clasificacion,
        ]);
    }

    public function guardar(Request $request, int $id)
    {
        $reglas = [];
        foreach (self::CAMPOS as $campo => $_) $reglas[$campo] = ['required', 'integer', 'min:1'];
        $datos = Validator::make($request->all(), $reglas)->validate();

        return DB::transaction(function () use ($id, $datos) {
            $producto = DB::table('productos')->where('id', $id)->lockForUpdate()->first();
            abort_unless($producto, 404, 'Producto inexistente');
            $cambios = [];
            $objetos = [];
            foreach (self::CAMPOS as $campo => [$tabla, $columna]) {
                $ref = DB::table($tabla)->where('id', (int) $datos[$campo])->first();
                abort_unless($ref && (bool) $ref->activo, 422,
                    'Clasificación inexistente o inactiva: ' . $campo);
                $objetos[$campo] = $ref;
                $cambios[$columna] = (int) $datos[$campo];
            }
            foreach ([
                ['departamentoId', 'rubroId', 'rubro_id'],
                ['familiaId', 'departamentoId', 'departamento_id'],
                ['lineaId', 'familiaId', 'familia_id'],
                ['modeloId', 'marcaId', 'marca_id'],
            ] as [$hijo, $padre, $columna]) {
                abort_unless((int) $objetos[$hijo]->{$columna} === (int) $datos[$padre], 422,
                    'Jerarquía inconsistente: ' . $hijo);
            }
            DB::table('productos')->where('id', $id)->update($cambios);
            return response()->json(['id' => $id, 'clasificacion' => $datos]);
        });
    }
}
