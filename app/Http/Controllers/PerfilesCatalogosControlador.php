<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Services\PermisosCatalogos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Administración de visibilidad y CRUD por perfil (rol).
 * Requiere usuario administrador; nunca permite modificar rol administrador.
 */
final class PerfilesCatalogosControlador extends Controller
{
    private function esAdministrador(Request $request): void
    {
        abort_unless($request->user()?->activo &&
            $request->user()?->rol?->codigo === 'administrador', 403,
            'Solo administración puede modificar perfiles');
    }

    public function listar(Request $request)
    {
        $this->esAdministrador($request);
        $roles = Rol::query()->orderBy('id')->get(['id','codigo','nombre']);
        $asignados = DB::table('roles_permisos')
            ->join('permisos','roles_permisos.permiso_id','=','permisos.id')
            ->whereIn('permisos.codigo', PermisosCatalogos::codigos())
            ->get(['roles_permisos.rol_id', 'permisos.codigo']);
        $porRol = [];
        foreach ($asignados as $permiso) $porRol[$permiso->rol_id][] = $permiso->codigo;
        $datos = $roles->map(function (Rol $rol) use ($porRol) {
            $admin = $rol->codigo === 'administrador';
            $codigos = $porRol[$rol->id] ?? [];
            $modulos = [];
            foreach (PermisosCatalogos::MODULOS as $key => $nombre) {
                $gestionar = $admin || in_array(PermisosCatalogos::codigo($key,'gestionar'),$codigos,true);
                $ver = $admin || $gestionar ||
                    in_array(PermisosCatalogos::codigo($key,'ver'),$codigos,true);
                $modulos[$key] = ['ver'=>$ver,'gestionar'=>$gestionar];
            }
            return [
                'id'=>$rol->id,'codigo'=>$rol->codigo,'nombre'=>$rol->nombre,
                'editable'=>!$admin,'modulos'=>$modulos,
            ];
        });
        return response()->json(['roles'=>$datos, 'catalogos'=>PermisosCatalogos::MODULOS]);
    }

    public function actualizar(Request $request, int $id)
    {
        $this->esAdministrador($request);
        $roles = ['modulos'=>['required','array','size:10']];
        foreach (array_keys(PermisosCatalogos::MODULOS) as $key) {
            $roles['modulos.'.$key] = ['required','array','size:2'];
            $roles['modulos.'.$key.'.ver'] = ['required','boolean'];
            $roles['modulos.'.$key.'.gestionar'] = ['required','boolean'];
        }
        $datos = Validator::make($request->all(), $roles)->validate();
        $otros = array_diff(array_keys($datos['modulos']),array_keys(PermisosCatalogos::MODULOS));
        abort_if(count($otros)>0,422,'Módulo no permitido');
        $escogidos = [];
        foreach (array_keys(PermisosCatalogos::MODULOS) as $key) {
            $ver = (bool)$datos['modulos'][$key]['ver'];
            $gestionar = (bool)$datos['modulos'][$key]['gestionar'];
            abort_if($gestionar && !$ver, 422,
                'Para gestionar un submódulo también debe estar visible: '.$key);
            if ($ver) $escogidos[] = PermisosCatalogos::codigo($key,'ver');
            if ($gestionar) $escogidos[] = PermisosCatalogos::codigo($key,'gestionar');
        }

        DB::transaction(function () use ($id,$escogidos) {
            $rol = DB::table('roles')->where('id',$id)->lockForUpdate()->first();
            abort_unless($rol,404,'Perfil no encontrado');
            abort_if($rol->codigo === 'administrador',403,'El perfil administrador es reservado');
            $todos = PermisosCatalogos::codigos();
            $ids = DB::table('permisos')->whereIn('codigo',$todos)->pluck('id','codigo')->all();
            abort_unless(count($ids)===count($todos), 409,
                'Ejecutar primero SQL 16 para instalar los 20 permisos');
            DB::table('roles_permisos')->where('rol_id',$id)
                ->whereIn('permiso_id',array_values($ids))->delete();
            if ($escogidos) {
                DB::table('roles_permisos')->insert(array_map(
                    fn($codigo)=>['rol_id'=>$id,'permiso_id'=>$ids[$codigo]],
                    $escogidos
                ));
            }
        });
        return response()->json(['guardado'=>true,'rol_id'=>$id]);
    }
}
