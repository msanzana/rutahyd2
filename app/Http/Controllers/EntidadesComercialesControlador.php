<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use App\Services\PermisosSubmodulos;
use App\Services\GestionListasPrecios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Support\RutChileno;

/**
 * Escrituras comerciales acotadas: clientes, proveedores y bodegas.
 * Las tablas con saldos, pagos, ventas y órdenes de compra no pasan por este CRUD.
 * DELETE es baja lógica para conservar la trazabilidad e integridad referencial.
 */
final class EntidadesComercialesControlador extends Controller
{
    public function __construct(private GestionListasPrecios $listasPrecios) {}

    private const PERMISOS = [
        'clientes' => 'clientes.gestionar',
        'proveedores' => 'compras.gestionar',
        'bodegas' => 'inventario.gestionar',
    ];
    private const COLUMNAS = [
        'clientes' => [
            'codigo', 'nombre', 'rut', 'correo_electronico', 'telefono',
            'direccion', 'ciudad_id', 'sector', 'observaciones', 'activo'
        ],
        'proveedores' => [
            'nombre', 'razon_social', 'nombre_fantasia', 'rut', 'giro',
            'actividad_economica_codigo', 'tipo_contribuyente',
            'correo_electronico', 'telefono',
            'direccion', 'direccion_numero', 'ciudad_id', 'plazo_pago_dias', 'activo'
        ],
        'bodegas' => ['codigo', 'nombre', 'tipo', 'ciudad_id', 'activo'],
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
            'ciudad_id' => array_merge($opcional, ['integer','min:1',
                Rule::exists('ciudades','id')->where('activo',1)]),
            'activo' => ['sometimes', 'boolean'],
        ];
        if ($recurso === 'clientes') {
            return array_merge($comunes, [
                'codigo' => array_merge($opcional, ['string', 'max:50',
                    Rule::unique('clientes', 'codigo')->ignore($id)]),
                'sector' => array_merge($opcional, ['string', 'max:120']),
                'observaciones' => array_merge($opcional, ['string', 'max:10000']),
                'lista_precio_id' => ['sometimes','integer','min:1','exists:listas_precios,id'],
            ]);
        }
        if ($recurso === 'proveedores') return array_merge($comunes,[
            'razon_social'=>array_merge($opcional,['string','max:190']),
            'nombre_fantasia'=>array_merge($opcional,['string','max:190']),
            'giro'=>array_merge($opcional,['string','max:255']),
            'actividad_economica_codigo'=>array_merge($opcional,
                ['regex:/^[0-9]{6}$/D',Rule::exists('giros_sii','codigo')->where('activo',1)]),
            'tipo_contribuyente'=>array_merge($opcional,
                ['in:PERSONA_NATURAL,PERSONA_JURIDICA,EXTRANJERO']),
            'direccion_numero'=>array_merge($opcional,['string','max:30']),
            'plazo_pago_dias'=>array_merge($opcional,['integer','min:0','max:3650']),
        ]);
        return [
            'codigo' => array_merge($obligatorio, ['string', 'min:1', 'max:30',
                'regex:/^[A-Za-z0-9_.-]+$/D',
                Rule::unique('bodegas', 'codigo')->ignore($id)]),
            'nombre' => array_merge($obligatorio, ['string', 'min:2', 'max:130']),
            'tipo' => ['sometimes', 'required', 'string', 'min:2', 'max:40'],
            'ciudad_id' => array_merge($opcional,['integer','min:1',
                Rule::exists('ciudades','id')->where('activo',1)]),
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
                if ($valor === '' && in_array($clave, [
                    'codigo','rut','correo_electronico','telefono','direccion',
                    'sector','observaciones','razon_social','nombre_fantasia','giro',
                    'actividad_economica_codigo','direccion_numero','tipo_contribuyente'
                ],true)
                    && !($recurso === 'bodegas' && $clave === 'codigo')) {
                    $valor = null;
                }
                $datos[$clave] = $valor;
            }
        }
        // Validación real MOD 11 para todos los campos RUT comerciales.
        // En blanco siguen siendo opcionales (proveedores extranjeros).
        if(in_array($recurso,['clientes','proveedores'],true)&&isset($datos['rut'])){
            $canonico=RutChileno::normalizar((string)$datos['rut']);
            abort_unless($canonico!==null,422,
                'RUT inválido: verifica cuerpo y dígito verificador');
            $datos['rut']=$canonico;
        }
        if($recurso==='proveedores'&&!empty($datos['actividad_economica_codigo'])){
            // La glosa depende del código seleccionado; no aceptar un nombre de giro
            // incoherente con el código oficial SII por manipulación del navegador.
            $actividad=DB::table('giros_sii')
                ->where('codigo',$datos['actividad_economica_codigo'])
                ->where('activo',1)->first(['descripcion']);
            abort_unless($actividad,422,'El giro SII seleccionado no existe o está inactivo');
            $datos['giro']=$actividad->descripcion;
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
        $listaId=$recurso==='clientes' ? ($datos['lista_precio_id']??null) : null;
        unset($datos['lista_precio_id']);
        abort_if($nuevo && $listaId!==null,422,
            'Los clientes nuevos reciben la LISTA BASE; cambia la lista al editar');
        try {
            return DB::transaction(function () use ($recurso, $id, $datos, $nuevo, $listaId): array {
                if ($nuevo) {
                    $datos['activo'] = $datos['activo'] ?? true;
                    $id = (int)DB::table($recurso)->insertGetId($datos);
                    if ($recurso === 'clientes') {
                        $this->listasPrecios->asignarListaBaseCliente($id);
                    }
                } else {
                    $actual = DB::table($recurso)->where('id', $id)->lockForUpdate()->first();
                    abort_unless($actual, 404, 'Registro inexistente');
                    if($datos)DB::table($recurso)->where('id', $id)->update($datos);
                    if($recurso==='clientes' && $listaId!==null)
                        $this->listasPrecios->asignarListaCliente($id,(int)$listaId);
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
