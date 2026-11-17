<?php
declare(strict_types=1);
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user=$request->user();
        if (!$user || !$user->active) abort(403, 'Cuenta inactiva o no autorizada');
        if ($user->role?->code === 'admin') return $next($request);
        $allowed=DB::table('role_permissions')
            ->join('permissions','role_permissions.permission_id','=','permissions.id')
            ->where('role_permissions.role_id',$user->role_id)
            ->where('permissions.code',$permission)->exists();
        abort_unless($allowed,403,'Permiso insuficiente');
        return $next($request);
    }
}