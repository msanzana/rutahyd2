<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\ContextoCompras;
use App\Services\ResolverEvaluacionPerdida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** V109F6A: evaluación tributaria interna, nunca emisión de aviso. */
final class RevisionTributariaPerdidasControlador extends Controller
{
    public const PERMISO='inventario.operaciones.revisar_tributaria';
    public static function habilitado(): bool {
        return config('perdidas.revision_tributaria_verificada')===true
            &&config('perdidas.permiso_revision_verificado')===true
            &&config('perdidas.aislamiento_verificado')===true;
    }
    private function acceso(Request $r): void {
        abort_unless(self::habilitado(),409,'Revisión tributaria no disponible.');
        // Incluso el administrador debe tener el permiso creado en el catálogo.
        $perm=DB::table('permisos')->where('codigo',self::PERMISO)->get(['id']);
        abort_unless($perm->count()===1,409,'Permiso tributario no configurado.');
        $user=$r->user();
        abort_unless($user&&$user->activo,403,'Acceso denegado.');
        abort_unless($user->rol?->codigo==='administrador'||DB::table('roles_permisos')->where('rol_id',$user->rol_id)->where('permiso_id',$perm->first()->id)->exists(),403,'No tiene permiso tributario.');
    }
    public function ver(Request $r,int $id){
        return response()->json(DB::transaction(function()use($r,$id){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$this->acceso($r);$uid=(int)$r->user()->id;
            $o=DB::table('inventario_operaciones as o')->join('estados as e','e.id','=','o.estado_id')->where('o.id',$id)->where('o.sucursal_id',$sid)->where('o.clase','AJUSTE')
                ->whereIn('o.motivo_codigo_snapshot',array_merge(range(301,311),range(401,409)))->where('e.codigo','INV_CONFIRMADA')
                ->select('o.id','o.numero_interno','o.version','o.revision_tributaria','o.evaluacion_aviso_sii','o.creado_por','o.confirmado_por')->first();
            abort_unless($o,404,'Pérdida no disponible.');
            $out=['id'=>(int)$o->id,'numero'=>$o->numero_interno,'version'=>(int)$o->version,'revision'=>$o->revision_tributaria,'evaluacion'=>$o->evaluacion_aviso_sii,
                'puede_revisar'=>$o->revision_tributaria==='PENDIENTE'&&in_array($o->evaluacion_aviso_sii,['NO_APLICA','PENDIENTE'],true)&&$uid!==(int)$o->creado_por&&$uid!==(int)$o->confirmado_por,
                'decisiones'=>$o->evaluacion_aviso_sii==='NO_APLICA'?['NO_APLICA']:['NO_REQUIERE_AVISO','REQUIERE_AVISO'],'aviso_emitido'=>false,'emision_automatica'=>false];
            if($o->revision_tributaria==='REVISADA'){
                $audit=DB::table('registros_auditoria')->where('tipo_entidad','inventario_operaciones')->where('entidad_id',$id)->where('accion','PERDIDA_REVISION_TRIBUTARIA')->get(['valores_nuevos']);
                abort_unless($audit->count()===1,409,'Revisión sin auditoría única.');
                $x=json_decode($audit->first()->valores_nuevos,true,512,JSON_THROW_ON_ERROR);
                abort_unless(($x['revision_tributaria']??null)==='REVISADA'&&($x['evaluacion_aviso_sii']??null)===$o->evaluacion_aviso_sii&&($x['aviso_emitido']??null)===false,409,'Auditoría incompatible.');
                $out['decision']=$x['decision'];$out['motivo']=$x['motivo'];$out['gestion_externa_pendiente']=$x['gestion_externa_pendiente'];
            }
            return $out;
        },3))->header('Cache-Control','private, no-store');
    }
    public function resolver(Request $r,int $id){
        $v=$r->validate(['version'=>'required|integer|min:1|max:4294967294','motivo'=>'required|string|min:10|max:1000','decision'=>'required|in:NO_APLICA,NO_REQUIERE_AVISO,REQUIERE_AVISO']);
        return response()->json(DB::transaction(function()use($r,$id,$v){
            $sid=(int)app(ContextoCompras::class)->resolver($r,true)->id;$this->acceso($r);
            return (new ResolverEvaluacionPerdida())->resolver($sid,$id,(int)$r->user()->id,(int)$v['version'],$v['motivo'],$v['decision']);
        },3))->header('Cache-Control','private, no-store');
    }
}
