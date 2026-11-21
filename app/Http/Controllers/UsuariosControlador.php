<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

final class UsuariosControlador extends Controller
{
    private function exigirAdministrador(Request $request): void
    {
        abort_unless($request->user()?->activo &&
            $request->user()?->rol?->codigo === 'administrador', 403,
            'Solo el administrador gestiona usuarios');
    }

    private function rolOperativo(int $id): Rol
    {
        $rol = Rol::query()->find($id);
        abort_unless($rol && $rol->codigo !== 'administrador', 422,
            'Los administradores se aprovisionan por consola privada');
        return $rol;
    }

    public function index(Request $request)
    {
        $this->exigirAdministrador($request);
        return response()->json([
            'usuarios' => DB::table('usuarios')
                ->join('roles','roles.id','=','usuarios.rol_id')
                ->orderBy('usuarios.nombre')
                ->get(['usuarios.id','usuarios.nombre','usuarios.correo_electronico',
                    'usuarios.activo','usuarios.rol_id','roles.codigo as rol_codigo',
                    'roles.nombre as perfil']),
            'roles' => Rol::where('codigo','!=','administrador')->orderBy('nombre')
                ->get(['id','codigo','nombre'])
        ])->header('Cache-Control', 'no-store');
    }

    public function crear(Request $request)
    {
        $this->exigirAdministrador($request);
        $datos = Validator::make($request->all(), [
            'nombre' => ['required','string','min:3','max:160'],
            'correo_electronico' => ['required','email','max:190','unique:usuarios,correo_electronico'],
            'rol_id' => ['required','integer','min:1'],
        ])->validate();
        $rol = $this->rolOperativo((int)$datos['rol_id']);
        abort_unless(config('mail.default') === 'smtp', 503,
            'Configura SMTP antes de invitar usuarios');
        $usuario = Usuario::query()->create([
            'nombre' => trim($datos['nombre']),
            'correo_electronico' => mb_strtolower(trim($datos['correo_electronico'])),
            'rol_id' => $rol->id,
            'hash_contrasena' => Hash::make(bin2hex(random_bytes(32))),
            'activo' => true,
        ]);
        try {
            RecuperacionContrasenaControlador::enviarEnlace($usuario);
        } catch (\Throwable $e) {
            // La cuenta creada sin correo no debe quedar con acceso ni contraseña utilizable.
            DB::table('restablecimientos_contrasena')->where('usuario_id',$usuario->id)->delete();
            $usuario->delete();
            throw $e;
        }
        return response()->json(['id'=>$usuario->id,'creado'=>true,
            'mensaje'=>'Usuario invitado. Se envió un enlace privado para definir su contraseña.'], 201)
            ->header('Cache-Control', 'no-store');
    }

    public function actualizar(Request $request, int $id)
    {
        $this->exigirAdministrador($request);
        $datos = Validator::make($request->all(), [
            'nombre' => ['required','string','min:3','max:160'],
            'correo_electronico' => ['required','email','max:190','unique:usuarios,correo_electronico,'.$id],
            'rol_id' => ['required','integer','min:1'],
            'activo' => ['required','boolean'],
        ])->validate();
        $rol = $this->rolOperativo((int)$datos['rol_id']);
        DB::transaction(function () use ($request,$id,$datos,$rol): void {
            $usuario = Usuario::query()->lockForUpdate()->findOrFail($id);
            abort_if($usuario->id === $request->user()->id ||
                $usuario->rol?->codigo === 'administrador', 403,
                'No se permite modificar cuentas administradoras desde este CRUD');
            $usuario->fill([
                'nombre' => trim($datos['nombre']),
                'correo_electronico' => mb_strtolower(trim($datos['correo_electronico'])),
                'rol_id' => $rol->id,
                'activo' => (bool)$datos['activo'],
            ]);
            $usuario->save();
            // Cambios de correo, perfil o estado invalidan TODAS las sesiones.
            DB::table('tokens_acceso')->where('usuario_id',$usuario->id)->delete();
            DB::table('restablecimientos_contrasena')->where('usuario_id',$usuario->id)->delete();
        });
        return response()->json(['actualizado'=>true])->header('Cache-Control','no-store');
    }

    public function eliminar(Request $request, int $id)
    {
        $this->exigirAdministrador($request);
        DB::transaction(function () use ($request,$id): void {
            $usuario = Usuario::query()->lockForUpdate()->findOrFail($id);
            abort_if($usuario->id === $request->user()->id ||
                $usuario->rol?->codigo === 'administrador', 403,
                'No se permite desactivar al administrador desde este CRUD');
            // Baja lógica para preservar referencias de ventas y auditoría.
            $usuario->activo = false;
            $usuario->save();
            DB::table('tokens_acceso')->where('usuario_id',$id)->delete();
            DB::table('restablecimientos_contrasena')->where('usuario_id',$id)->delete();
        });
        return response()->noContent();
    }
}
