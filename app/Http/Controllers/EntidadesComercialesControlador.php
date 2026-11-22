<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use App\Services\PermisosSubmodulos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Escrituras comerciales acotadas: clientes, proveedores y bodegas.
 * Las tablas con saldos, pagos, ventas y órdenes de compra no pasan por este CRUD.
 * DELETE es baja lógica para conservar la trazabilidad e integridad referencial.
 */
final class EntidadesComercialesControlador extends Controller
{
    private const PERMISOS = [
        'clientes' => 'clientes.gestionar',
        'proveedores' => 'compras.gestionar',
        'bodegas' => 'inventario.gestionar',
    ];
    private const COLUMNAS = [
        'clientes' => [
            'codigo', 'nombre', 'rut', 'correo_electronico', 'telefono',
            'direccion', 'comuna', 'sector', 'observaciones', 'activo'
        ],
        'proveedores' => [
            'nombre', 'rut', 'correo_electronico', 'telefono', 'direccion', 'activo'
        ],
        'bodegas' => ['codigo', 'nombre', 'tipo', 'activo'],
    ];

    private function exigirPermiso(Request $request, string $recurso): void
    {
        abort_unless(isset(self::PERMISOS[$recurso]), 404, 'Entidad no administrable');
        $usuario = $request->user();
        abort_unless($usuario && $usuario->activo, 401);
        if($recurso==='proveedores') PermisosSubmodulos::exigir($usuario,'proveedores_listado','gestionar');
        if ($usuario->rol?->codigo === 'administrador') return;
        $puede = DB::table('roles_permisos')
            ->join('permisos', 'permisos.id', '=', 'roles_permisos.permiso_id')
            ->where('roles_permisos.rol_id', $usuario->rol_id)
            ->where('permisos.codigo', self::PERMISOS[$recurso])->exists();
        abort_unless($puede, 403, 'El perfil no permite gestionar esta entidad');
    }

    private function reglas(string $recurso, bool $nuevo, ?int $id): array
    {
        $obligatorio = $nuevo ? ['required'] : ['sometimes', 'required'];
        $opcional = ['sometimes', 'nullable'];
        $nombre = array_merge($obligatorio, ['string', 'min:2', 'max:190']);
        $comunes = [
            'nombre' => $nombre,
            'rut' => array_merge($opcional, ['string', 'max:25']),
            'correo_electronico' => array_merge($opcional, ['email', 'max:190']),
            'telefono' => array_merge($opcional, ['string', 'max:50']),
            'direccion' => array_merge($opcional, ['string', 'max:255']),
            'activo' => ['sometimes', 'boolean'],
        ];
        if ($recurso === 'clientes') {
            return array_merge($comunes, [
                'codigo' => array_merge($opcional, ['string', 'max:50',
                    Rule::unique('clientes', 'codigo')->ignore($id)]),
                'comuna' => array_merge($opcional, ['string', 'max:100']),
                'sector' => array_merge($opcional, ['string', 'max:120']),
                'observaciones' => array_merge($opcional, ['string', 'max:10000']),
            ]);
        }
        if ($recurso === 'proveedores') return $comunes;
        return [
            'codigo' => array_merge($obligatorio, ['string', 'min:1', 'max:30',
                'regex:/^[A-Za-z0-9_.-]+$/D',
                Rule::unique('bodegas', 'codigo')->ignore($id)]),
            'nombre' => array_merge($obligatorio, ['string', 'min:2', 'max:130']),
            'tipo' => ['sometimes', 'required', 'string', 'min:2', 'max:40'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    private function validar(Request $request, string $recurso, bool $nuevo, ?int $id): array
    {
        $reglas = $this->reglas($recurso, $nuevo, $id);
        abort_if(array_diff(array_keys($request->all()), array_keys($reglas)) !== [],
            422, 'La solicitud contiene campos no permitidos');
        $datos = Validator::make($request->all(), $reglas)->validate();
        abort_if(!$nuevo && $datos === [], 422, 'No hay campos para actualizar');
        foreach ($datos as $clave => $valor) {
            if (is_string($valor)) {
                $valor = trim($valor);
                // Los campos opcionales vacíos no se convierten en cadenas sin valor.
                if ($valor === '' && in_array($clave, ['codigo', 'rut', 'correo_electronico',
                    'telefono', 'direccion', 'comuna', 'sector', 'observaciones'], true)
                    && !($recurso === 'bodegas' && $clave === 'codigo')) {
                    $valor = null;
                }
                $datos[$clave] = $valor;
            }
        }
        abort_if(array_key_exists('nombre', $datos) && $datos['nombre'] === '',
            422, 'Nombre obligatorio');
        abort_if($recurso === 'bodegas' && array_key_exists('codigo', $datos)
            && $datos['codigo'] === '', 422, 'Código obligatorio');
        return $datos;
    }

    private function ejecutar(Request $request, string $recurso, ?int $id): array
    {
        $this->exigirPermiso($request, $recurso);
        $nuevo = $id === null;
        $datos = $this->validar($request, $recurso, $nuevo, $id);
        try {
            return DB::transaction(function () use ($recurso, $id, $datos, $nuevo): array {
                if ($nuevo) {
                    $datos['activo'] = $datos['activo'] ?? true;
                    $id = (int)DB::table($recurso)->insertGetId($datos);
                } else {
                    $actual = DB::table($recurso)->where('id', $id)->lockForUpdate()->first();
                    abort_unless($actual, 404, 'Registro inexistente');
                    DB::table($recurso)->where('id', $id)->update($datos);
                }
                $registro = DB::table($recurso)->where('id', $id)->first();
                return (array)$registro;
            });
        } catch (QueryException $e) {
            if ((string)$e->getCode() === '23000') abort(409, 'Código duplicado o restricción comercial');
            throw $e;
        }
    }

    public function crear(Request $request, string $recurso)
    {
        return response()->json($this->ejecutar($request, $recurso, null), 201)
            ->header('Cache-Control', 'no-store');
    }

    public function actualizar(Request $request, string $recurso, int $id)
    {
        return response()->json($this->ejecutar($request, $recurso, $id))
            ->header('Cache-Control', 'no-store');
    }

    public function desactivar(Request $request, string $recurso, int $id)
    {
        $this->exigirPermiso($request, $recurso);
        $actual = DB::transaction(function () use ($recurso, $id): object {
            $registro = DB::table($recurso)->where('id', $id)->lockForUpdate()->first();
            abort_unless($registro, 404, 'Registro inexistente');
            if ((int)$registro->activo !== 0) {
                DB::table($recurso)->where('id', $id)->update(['activo' => 0]);
            }
            return $registro;
        });
        return response()->json([
            'id' => $id, 'activo' => false, 'eliminacion' => 'logica',
            'recurso' => $recurso,
        ])->header('Cache-Control', 'no-store');
    }
}
