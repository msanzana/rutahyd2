<?php
declare(strict_types=1);
namespace App\Services;

use Illuminate\Support\Facades\DB;

/** V109F4C1: revisión interna posterior a confirmación.
 * REVISADA significa evaluación registrada, nunca envío de aviso o aceptación SII.
 */
final class ResolverEvaluacionPerdida
{
    public function resolver(int $sid,int $id,int $uid,int $version,string $motivo,string $decision): array {
        abort_unless(config('perdidas.revision_tributaria_verificada')===true&&DB::transactionLevel()>0&&$sid>0&&$uid>0&&$version>0&&$version<4294967295,409,'Revisión tributaria no habilitada.');
        abort_unless(in_array($decision,['NO_APLICA','NO_REQUIERE_AVISO','REQUIERE_AVISO'],true)&&mb_strlen(trim($motivo))>=10&&mb_strlen($motivo)<=1000,422,'Fundamente la evaluación.');
        $o=DB::table('inventario_operaciones as o')->join('estados as e','e.id','=','o.estado_id')
            ->where('o.id',$id)->where('o.sucursal_id',$sid)->select('o.*','e.codigo as estado_codigo')->lockForUpdate()->first();
        abort_unless($o&&$o->clase==='AJUSTE'&&in_array((int)$o->motivo_codigo_snapshot,array_merge(range(301,311),range(401,409)),true),404,'Pérdida no disponible.');
        abort_unless($o->estado_codigo==='INV_CONFIRMADA'&&$o->token_confirmacion!==null&&$o->anulado_por===null&&(int)$o->creado_por>0
            &&(int)$o->aprobado_por>0&&(int)$o->creado_por!==(int)$o->aprobado_por&&(int)$o->confirmado_por>0
            &&$uid!==(int)$o->creado_por&&$uid!==(int)$o->confirmado_por,409,'Revisión exige pérdida confirmada y revisor independiente.');
        $lines=DB::table('inventario_operaciones_detalle')->where('operacion_id',$id)->lockForUpdate()->get();
        abort_unless($lines->isNotEmpty(),409,'Detalle ausente.');
        foreach($lines as $l){
            $k=DB::table('kardex')->where('id',$l->kardex_id)->lockForUpdate()->first();
            abort_unless($k&&(int)$k->sucursal_id===$sid&&(int)$k->producto_id===(int)$l->producto_id&&(int)$k->bodega_id===(int)$l->bodega_id
                &&$k->origen_tipo==='AJUSTE_INVENTARIO'&&(int)$k->origen_detalle_id===(int)$l->id&&$k->operacion_codigo===$o->regla_snapshot
                &&(int)$k->tipo_movimiento===2,409,'Movimiento de pérdida no acreditado.');
        }
        $files=DB::table('inventario_operaciones_adjuntos')->where('operacion_id',$id)->orderBy('id')->lockForUpdate()->get();
        abort_unless($files->isNotEmpty()&&$files->count()<=3,409,'Respaldo obligatorio.');
        $hashes=[];$total=0;
        foreach($files as $f){
            $total+=strlen($f->contenido);
            abort_unless($total<=2097152&&strlen($f->contenido)>0&&hash_equals((string)$f->sha256,hash('sha256',$f->contenido))
                &&in_array($f->mime,['application/pdf','image/png','image/jpeg'],true)&&(new \finfo(FILEINFO_MIME_TYPE))->buffer($f->contenido)===$f->mime,409,'Respaldo alterado.');
            $hashes[]=$f->sha256;
        }
        $audit=DB::table('registros_auditoria')->where('tipo_entidad','inventario_operaciones')->where('entidad_id',$id)->where('accion','PERDIDA_REVISION_TRIBUTARIA')->lockForUpdate()->get();
        if($o->revision_tributaria==='REVISADA'){
            abort_unless($audit->count()===1&&(int)$audit->first()->usuario_id===$uid&&(int)$o->version===$version+1,409,'Revisión anterior incompatible.');
            $saved=json_decode($audit->first()->valores_nuevos,true,512,JSON_THROW_ON_ERROR);
            abort_unless(($saved['decision']??null)===$decision&&($saved['motivo']??null)===trim($motivo)&&($saved['respaldos']??null)===$hashes&&($saved['evaluacion_aviso_sii']??null)===$o->evaluacion_aviso_sii,409,'Revisión anterior diferente.');
            return ['id'=>$id,'version'=>(int)$o->version,'decision'=>$decision,'repetida'=>true,'aviso_emitido'=>false,'gestion_externa_pendiente'=>$decision==='REQUIERE_AVISO','movimiento_aplicado'=>false];
        }
        abort_unless($audit->isEmpty()&&$o->revision_tributaria==='PENDIENTE'&&(int)$o->version===$version&&in_array($o->evaluacion_aviso_sii,['PENDIENTE','NO_APLICA'],true),409,'Estado tributario o versión incompatible.');
        abort_unless(($o->evaluacion_aviso_sii==='NO_APLICA'&&$decision==='NO_APLICA')||($o->evaluacion_aviso_sii==='PENDIENTE'&&$decision!=='NO_APLICA'),409,'Decisión incompatible con la evaluación requerida.');
        $new=$o->evaluacion_aviso_sii==='PENDIENTE'?'REVISADA':'NO_APLICA';
        DB::table('inventario_operaciones')->where('id',$id)->update(['revision_tributaria'=>'REVISADA','evaluacion_aviso_sii'=>$new,'version'=>$version+1]);
        DB::table('registros_auditoria')->insert(['usuario_id'=>$uid,'accion'=>'PERDIDA_REVISION_TRIBUTARIA','tipo_entidad'=>'inventario_operaciones','entidad_id'=>$id,
            'valores_anteriores'=>json_encode(['revision_tributaria'=>$o->revision_tributaria,'evaluacion_aviso_sii'=>$o->evaluacion_aviso_sii,'version'=>$version],JSON_THROW_ON_ERROR),
            'valores_nuevos'=>json_encode(['revision_tributaria'=>'REVISADA','evaluacion_aviso_sii'=>$new,'decision'=>$decision,'motivo'=>trim($motivo),'respaldos'=>$hashes,'version'=>$version+1,'aviso_emitido'=>false,'gestion_externa_pendiente'=>$decision==='REQUIERE_AVISO'],JSON_THROW_ON_ERROR)]);
        return ['id'=>$id,'version'=>$version+1,'decision'=>$decision,'repetida'=>false,'aviso_emitido'=>false,'gestion_externa_pendiente'=>$decision==='REQUIERE_AVISO','movimiento_aplicado'=>false];
    }
}
