<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contexto de sucursal ligado al registro JWT, no a valores almacenados en el navegador.
 * V107 es una preparación: no habilitar contexto_obligatorio hasta que TODAS
 * las rutas operacionales y sus transacciones validen y utilicen el contexto.
 */
final class ContextoSucursalControlador extends Controller
{
    private function token(Request $r,bool $lock=false): object
    {
        $uid=(int)($r->user()?->id??0);
        $tid=(int)$r->attributes->get('id_token_acceso',0);
        abort_unless($uid>0&&$tid>0,401,'Sesión no disponible.');
        $q=DB::table('tokens_acceso')->where('id',$tid)->where('usuario_id',$uid)->where('expira_en','>',now());
        if($lock)$q->lockForUpdate();
        $row=$q->first();
        abort_unless($row,401,'Sesión vencida o revocada.');
        return $row;
    }
    private function usuario(int $id,bool $lock=false): object
    {
        $q=DB::table('usuarios as u')->join('roles as r','r.id','=','u.rol_id')->where('u.id',$id)->where('u.activo',1)->select('u.id','r.codigo as rol');
        if($lock)$q->lockForUpdate();
        $u=$q->first();
        abort_unless($u,401,'Usuario no disponible.');
        return $u;
    }
    private function autorizadas(object $u)
    {
        $q=DB::table('sucursales as s')->where('s.activo',1)->select('s.id','s.codigo','s.nombre','s.direccion','s.ciudad_id');
        if($u->rol!=='administrador'){
            $q->whereExists(fn($x)=>$x->selectRaw('1')->from('usuarios_sucursales as us')
                ->whereColumn('us.sucursal_id','s.id')->where('us.usuario_id',$u->id)->where('us.activo',1));
        }
        return $q;
    }
    // Barrera de versión: la configuración SQL por sí sola no habilita el aislamiento.
    private const AISLAMIENTO_COMPLETO=false;
    private function listo(): bool { return self::AISLAMIENTO_COMPLETO || app()->environment('testing'); }
    private function obligatorio(): bool
    {
        $row=DB::table('sucursales_configuracion')->where('id',1)->first();
        abort_unless($row,503,'Configuración de sucursales pendiente.');
        return $this->listo() && (bool)$row->contexto_obligatorio;
    }
    // Etapa transitoria: confirmar una sucursal única no equivale a aislar operaciones.
    // Si existe cualquier segunda sucursal (activa o histórica), no habilitar este modo.
    private function unica(): bool
    {
        return DB::table('sucursales')->count()===1 && DB::table('sucursales')->where('activo',1)->count()===1;
    }
    public function estado(Request $r)
    {
        $token=$this->token($r);$u=$this->usuario((int)$token->usuario_id);
        $required=$this->obligatorio();
        $single=!$required&&$this->unica();
        $context=DB::table('sesiones_sucursales')->where('token_acceso_id',$token->id)->first();
        $allowed=$this->autorizadas($u)->orderBy('s.nombre')->orderBy('s.id')->get();
        $current=($required||$single)&&$context?$allowed->first(fn($s)=>(int)$s->id===(int)$context->sucursal_id):null;
        return response()->json([
            'contexto_obligatorio'=>$required,
            'aislamiento_completo'=>$this->listo(),
            'seleccion_habilitada'=>$required||$single,
            'modo_sucursal_unica'=>$single,
            'sucursales'=>$allowed,
            'sucursal_actual'=>$current,
            'version_contexto'=>(int)($context->version_contexto??0),
        ])->header('Cache-Control','no-store');
    }
    public function seleccionar(Request $r)
    {
        $v=$r->validate(['sucursal_id'=>'required|integer|min:1','version_contexto'=>'required|integer|min:0|max:2147483646']);
        // El bloqueo de sucursal precede a usuario/token, igual que la administración de accesos.
        return response()->json(DB::transaction(function()use($r,$v){
            $required=$this->obligatorio();
            abort_unless($required||$this->unica(),409,'El contexto operativo multisucursal aún no está habilitado.');
            $s=DB::table('sucursales')->where('id',(int)$v['sucursal_id'])->where('activo',1)->lockForUpdate()->first();
            abort_unless($s,403,'Sucursal no autorizada o inactiva.');
            abort_unless($required||$this->unica(),409,'La configuración de sucursales cambió.');
            $u=$this->usuario((int)($r->user()?->id??0),true);
            abort_unless($this->autorizadas($u)->where('s.id',$s->id)->exists(),403,'Sucursal no autorizada.');
            $token=$this->token($r,true);
            $old=DB::table('sesiones_sucursales')->where('token_acceso_id',$token->id)->lockForUpdate()->first();
            $version=(int)($old->version_contexto??0);
            abort_unless($version===(int)$v['version_contexto'],409,'El contexto de la sesión cambió. Actualice la selección.');
            if($old&&(int)$old->sucursal_id===(int)$s->id){
                return ['sucursal_actual'=>$this->autorizadas($u)->where('s.id',$s->id)->first(),'version_contexto'=>$version];
            }
            abort_if($version>=2147483646,409,'Renueve la sesión para cambiar de sucursal.');
            $next=$version+1;$fields=['sucursal_id'=>$s->id,'version_contexto'=>$next,'seleccionada_en'=>now()];
            if($old)DB::table('sesiones_sucursales')->where('token_acceso_id',$token->id)->update($fields);
            else DB::table('sesiones_sucursales')->insert($fields+['token_acceso_id'=>$token->id]);
            DB::table('registros_auditoria')->insert([
                'usuario_id'=>$u->id,'accion'=>'SUCURSAL_SELECCIONAR','tipo_entidad'=>'sucursales','entidad_id'=>$s->id,
                'valores_anteriores'=>json_encode(['sucursal_id'=>$old->sucursal_id??null,'version_contexto'=>$version],JSON_THROW_ON_ERROR),
                'valores_nuevos'=>json_encode(['sucursal_id'=>$s->id,'version_contexto'=>$next],JSON_THROW_ON_ERROR),
            ]);
            return ['sucursal_actual'=>$this->autorizadas($u)->where('s.id',$s->id)->first(),'version_contexto'=>$next];
        },3))->header('Cache-Control','no-store');
    }
}
