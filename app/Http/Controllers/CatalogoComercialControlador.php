<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use App\Services\PermisosCatalogos;

/**
 * Catálogos comerciales v2. Requiere token y permiso productos.gestionar
 * en las rutas. Lista blanca: nunca se aceptan nombres de tabla del cliente.
 */
final class CatalogoComercialControlador extends Controller
{
    private const CATALOGOS = [
        'rubros' => ['parent' => null, 'product' => 'rubro_id'],
        'departamentos' => ['parent' => 'rubros', 'parent_column' => 'rubro_id', 'product' => 'departamento_id'],
        'familias' => ['parent' => 'departamentos', 'parent_column' => 'departamento_id', 'product' => 'familia_id'],
        'lineas' => ['parent' => 'familias', 'parent_column' => 'familia_id', 'product' => 'linea_id'],
        'marcas' => ['parent' => null, 'product' => 'marca_id'],
        'modelos' => ['parent' => 'marcas', 'parent_column' => 'marca_id', 'product' => 'modelo_id'],
        'tipos_unidad' => ['parent' => null, 'product' => 'tipo_unidad_id'],
        'gramajes' => ['parent' => null, 'product' => 'gramaje_id'],
        'tipos_envasado' => ['parent' => null, 'product' => 'tipo_envasado_id'],
        'condiciones_tributarias' => ['parent' => null, 'product' => 'condicion_tributaria_id'],
    ];

    private function esquema(string $catalogo): array
    {
        abort_unless(isset(self::CATALOGOS[$catalogo]), 404, 'Catálogo no disponible');
        return self::CATALOGOS[$catalogo];
    }

    private function respuesta(string $catalogo, object $fila): array
    {
        $esquema = self::CATALOGOS[$catalogo];
        return [
            'id' => $fila->id,
            'nombre' => $fila->nombre,
            'activo' => (bool) $fila->activo,
            'parentId' => $esquema['parent'] ? $fila->{$esquema['parent_column']} : null,
        ];
    }

    public function listar(Request $request, string $catalogo)
    {
        $this->esquema($catalogo);
        PermisosCatalogos::exigir($request->user(), $catalogo, 'ver');
        $filas = DB::table($catalogo)->orderBy('nombre')->get();
        return response()->json(['items' => $filas->map(
            fn (object $fila): array => $this->respuesta($catalogo, $fila)
        )->values()]);
    }

    public function crear(Request $request, string $catalogo)
    {
        $this->esquema($catalogo);
        PermisosCatalogos::exigir($request->user(), $catalogo, 'gestionar');
        $fila = DB::transaction(fn (): object => $this->guardar($request, $catalogo, null));
        return response()->json($this->respuesta($catalogo, $fila), 201);
    }

    public function actualizar(Request $request, string $catalogo, int $id)
    {
        $this->esquema($catalogo);
        PermisosCatalogos::exigir($request->user(), $catalogo, 'gestionar');
        $fila = DB::transaction(fn (): object => $this->guardar($request, $catalogo, $id));
        return response()->json($this->respuesta($catalogo, $fila));
    }

    private function dependientes(string $catalogo, int $id): bool
    {
        $columna = self::CATALOGOS[$catalogo]['product'];
        if (DB::table('productos')->where($columna, $id)->exists()) return true;
        foreach (self::CATALOGOS as $tabla => $esquema) {
            if ($esquema['parent'] === $catalogo &&
                DB::table($tabla)->where($esquema['parent_column'], $id)->exists()) return true;
        }
        return false;
    }

    private function guardar(Request $request, string $catalogo, ?int $id): object
    {
        $esquema = self::CATALOGOS[$catalogo];
        $actual = $id === null ? null : DB::table($catalogo)->where('id', $id)->lockForUpdate()->first();
        if ($id !== null) abort_unless($actual, 404, 'Registro inexistente');

        $datos = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:100'],
            'activo' => ['required', 'boolean'],
            'parentId' => [$esquema['parent'] ? 'required' : 'nullable', 'nullable', 'integer', 'min:1'],
        ])->validate();
        $nombre = trim($datos['nombre']);
        abort_if($nombre === '', 422, 'El nombre es obligatorio');
        $activo = (bool) $datos['activo'];
        $padre = $esquema['parent'] ? (int) $datos['parentId'] : null;

        if ($esquema['parent']) {
            $filaPadre = DB::table($esquema['parent'])->where('id', $padre)->first();
            abort_unless($filaPadre && (bool) $filaPadre->activo, 422, 'El padre debe existir y estar activo');
        }

        if ($catalogo === 'condiciones_tributarias') {
            // Catálogo reservado: sus dos opciones son invariantes del sistema.
            abort_unless($activo, 422, 'No se pueden desactivar las condiciones tributarias');
            // Mantener únicamente los dos códigos tributarios definidos por el proyecto.
            $codigo = match (mb_strtolower($nombre)) {
                'afecto' => 'AFECTO',
                'exento' => 'EXENTO',
                default => null
            };
            abort_unless($codigo, 422, 'Solo se admite Afecto o Exento');
            if ($actual) abort_unless($actual->codigo === $codigo, 422, 'No se puede cambiar el tipo tributario');
        }
        $gramos = null;
        if ($catalogo === 'gramajes') {
            abort_unless(preg_match('/^\\d{1,9}(?:\\.\\d{1,3})?$/D', $nombre) === 1,
                422, 'Gramos: número positivo con hasta tres decimales');
            $gramos = (float) $nombre;
            $nombre = number_format($gramos, 3, '.', '');
        }
        if ($actual && ((!$activo && (bool) $actual->activo) ||
            ($esquema['parent'] && (int) $actual->{$esquema['parent_column']} !== $padre))) {
            abort_if($this->dependientes($catalogo, $id), 409,
                'Registro relacionado: no puede desactivarse ni cambiar de padre');
        }

        $coincide = DB::table($catalogo);
        if ($catalogo === 'gramajes') {
            $coincide->where('gramos', $gramos);
        } elseif ($catalogo === 'condiciones_tributarias') {
            $coincide->where('codigo', $codigo);
        } else {
            $coincide->where('nombre', $nombre);
            if ($esquema['parent']) $coincide->where($esquema['parent_column'], $padre);
        }
        if ($id !== null) $coincide->where('id', '!=', $id);
        abort_if($coincide->exists(), 409, 'Ya existe este valor en el catálogo');

        $campos = ['nombre' => $nombre, 'activo' => $activo ? 1 : 0];
        if ($esquema['parent']) $campos[$esquema['parent_column']] = $padre;
        if ($catalogo === 'gramajes') $campos['gramos'] = $gramos;
        if ($catalogo === 'condiciones_tributarias') $campos['codigo'] = $codigo;

        if ($id === null) $id = (int) DB::table($catalogo)->insertGetId($campos);
        else DB::table($catalogo)->where('id', $id)->update($campos);
        return DB::table($catalogo)->where('id', $id)->first();
    }

    public function eliminar(Request $request, string $catalogo, int $id)
    {
        $this->esquema($catalogo);
        PermisosCatalogos::exigir($request->user(), $catalogo, 'gestionar');
        abort_if($catalogo === 'condiciones_tributarias', 409,
            'Las condiciones Afecto y Exento son registros reservados del sistema');
        try {
            DB::transaction(function () use ($catalogo, $id): void {
                $fila = DB::table($catalogo)->where('id', $id)->lockForUpdate()->first();
                abort_unless($fila, 404, 'Registro inexistente');
                abort_if($this->dependientes($catalogo, $id), 409, 'Registro en uso');
                DB::table($catalogo)->where('id', $id)->delete();
            });
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23000', '23503'], true)) {
                abort(409, 'El registro tiene relaciones vigentes');
            }
            throw $e;
        }
        return response()->noContent();
    }
}
