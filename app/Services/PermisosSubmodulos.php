<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Restricciones adicionales a los permisos base.
 * Sin fila explícita se preservan los derechos históricos; una fila limita
 * el submódulo sin conceder permisos generales que el perfil no posea.
 */
final class PermisosSubmodulos
{
    public const SUBMODULOS = [
        'productos_listado' => 'Listado de productos',
        'proveedores_listado' => 'Listado de proveedores',
        'proveedores_productos' => 'Proveedores productos',
    ];
    public const BASE = [
        'productos_listado'=>'productos.gestionar',
        'proveedores_listado'=>'compras.gestionar',
        'proveedores_productos'=>'compras.gestionar',
    ];

    public static function datosRol(int $rolId, bool $esAdmin, array $permisosBase): array
    {
        $filas=DB::table('roles_submodulos')->where('rol_id',$rolId)
            ->get(['submodulo','ver','gestionar'])->keyBy('submodulo');
        $resultado=[];
        foreach (self::SUBMODULOS as $codigo=>$titulo) {
            $base=$esAdmin||in_array(self::BASE[$codigo],$permisosBase,true);
            $fila=$filas[$codigo]??null;
            $ver=$base&&($esAdmin||!$fila||(bool)$fila->ver);
            $gestionar=$base&&($esAdmin||!$fila||(bool)$fila->gestionar);
            $resultado[$codigo]=[
                'ver'=>$ver,
                'gestionar'=>$gestionar,
                'configurable'=>!$esAdmin,
                'heredado'=>!$fila,
            ];
        }
        return $resultado;
    }

    public static function permitidos(Usuario $usuario): array
    {
        if (!$usuario->activo) return [];
        $admin=$usuario->rol?->codigo==='administrador';
        $perm=$admin?array_values(self::BASE):PermisosCatalogos::concedidos($usuario);
        return self::datosRol((int)$usuario->rol_id,$admin,$perm);
    }

    public static function exigir(Usuario $usuario, string $submodulo, string $accion): void
    {
        abort_unless(array_key_exists($submodulo,self::SUBMODULOS),404,'Submódulo desconocido');
        abort_unless(in_array($accion,['ver','gestionar'],true),422,'Acción inválida');
        abort_unless($usuario->activo,401);
        if ($usuario->rol?->codigo==='administrador')return;
        // Comprobar permiso base ANTES de aplicar restricción adicional.
        $permisos=PermisosCatalogos::concedidos($usuario);
        abort_unless(in_array(self::BASE[$submodulo],$permisos,true),403,'Permiso general ausente');
        $fila=DB::table('roles_submodulos')
            ->where('rol_id',$usuario->rol_id)->where('submodulo',$submodulo)->first();
        if($fila)abort_unless((bool)$fila->{$accion},403,
            'El perfil no autoriza este submódulo');
    }
}
