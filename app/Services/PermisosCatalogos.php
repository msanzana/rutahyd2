<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Permisos por submódulo de Productos. Ningún permiso nuevo es concedido
 * implícitamente por productos.gestionar: aislamiento real por perfil.
 */
final class PermisosCatalogos
{
    public const MODULOS = [
        'rubros' => 'Rubros',
        'departamentos' => 'Departamentos',
        'familias' => 'Familias',
        'lineas' => 'Líneas',
        'marcas' => 'Marcas',
        'modelos' => 'Modelos',
        'tipos_unidad' => 'Tipos de unidad',
        'gramajes' => 'Gramajes',
        'tipos_envasado' => 'Tipos de envasado',
        'condiciones_tributarias' => 'Condiciones tributarias',
    ];

    public static function codigo(string $modulo, string $accion): string
    {
        abort_unless(isset(self::MODULOS[$modulo]), 404, 'Módulo inexistente');
        abort_unless(in_array($accion, ['ver', 'gestionar'], true), 422, 'Acción inválida');
        return 'productos.catalogos.' . $modulo . '.' . $accion;
    }

    public static function concedidos(Usuario $usuario): array
    {
        if ($usuario->rol?->codigo === 'administrador') {
            return array_merge(['productos.gestionar', 'usuarios.gestionar'], self::codigos());
        }
        return DB::table('roles_permisos')
            ->join('permisos', 'permisos.id', '=', 'roles_permisos.permiso_id')
            ->where('roles_permisos.rol_id', $usuario->rol_id)
            ->pluck('permisos.codigo')->all();
    }

    public static function codigos(): array
    {
        $out = [];
        foreach (array_keys(self::MODULOS) as $modulo) {
            $out[] = self::codigo($modulo, 'ver');
            $out[] = self::codigo($modulo, 'gestionar');
        }
        return $out;
    }

    public static function puede(Usuario $usuario, string $modulo, string $accion): bool
    {
        $codigo = self::codigo($modulo, $accion);
        if (!$usuario->activo) return false;
        if ($usuario->rol?->codigo === 'administrador') return true;

        $asignados = self::concedidos($usuario);
        // Gestionar presupone Ver dentro del mismo submódulo;
        // guardar solo permiso de lectura nunca habilita escrituras.
        return in_array($codigo, $asignados, true) ||
            ($accion === 'ver' &&
             in_array(self::codigo($modulo, 'gestionar'), $asignados, true));
    }

    public static function exigir(Usuario $usuario, string $modulo, string $accion): void
    {
        abort_unless(self::puede($usuario, $modulo, $accion), 403,
            'El perfil no tiene permiso para este submódulo');
    }
}
