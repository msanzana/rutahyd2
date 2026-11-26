<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Primera etapa de compras: contexto único explícito. No concede acceso global. */
final class ContextoCompras
{
    public function resolver(Request $r,bool $bloquear=false): object
    {
        $uid=(int)($r->user()?->id??0);
        $tid=(int)$r->attributes->get('id_token_acceso',0);
        abort_unless($uid>0&&$tid>0,401,'Sesión no disponible.');
        abort_if($bloquear&&DB::transactionLevel()===0,500,'Contexto sin transacción.');
        // En transición no se admite más de una sucursal, incluso histórica.
        $q=DB::table('sucursales')->orderBy('id');
        if($bloquear)$q->lockForUpdate();
        $all=$q->get();
        abort_unless($all->count()===1&&(int)$all[0]->activo===1,409,'Compras aún requiere una única sucursal activa hasta completar la integración.');
        $s=$all[0];
        $uq=DB::table('usuarios as u')->join('roles as r','r.id','=','u.rol_id')->where('u.id',$uid)->where('u.activo',1)->select('u.id','r.codigo as rol');
        if($bloquear)$uq->lockForUpdate();
        $u=$uq->first();
        abort_unless($u,401,'Usuario no disponible.');
        $tq=DB::table('tokens_acceso')->where('id',$tid)->where('usuario_id',$uid)->where('expira_en','>',now());
        if($bloquear)$tq->lockForUpdate();
        abort_unless($tq->first(),401,'Sesión revocada o vencida.');
        $cq=DB::table('sesiones_sucursales')->where('token_acceso_id',$tid);
        if($bloquear)$cq->lockForUpdate();
        $ctx=$cq->first();
        abort_unless($ctx&&(int)$ctx->sucursal_id===(int)$s->id,409,'Cierra sesión e ingresa seleccionando tu sucursal.');
        if($u->rol!=='administrador'){
            $aq=DB::table('usuarios_sucursales')->where('usuario_id',$uid)->where('sucursal_id',$s->id)->where('activo',1);
            if($bloquear)$aq->lockForUpdate();
            abort_unless($aq->first(),403,'No tienes acceso a esta sucursal.');
        }
        return $s;
    }

    /** Correlativo de OC interno por sucursal/año. No cambia documentos emitidos. */
    public function numeroOrden(object $s,int $periodo): string
    {
        abort_unless(DB::transactionLevel()>0,500,'Correlativo sin transacción.');
        abort_unless($periodo>=1900&&$periodo<=9999,422,'Año no válido.');
        $key=['sucursal_id'=>$s->id,'tipo_documento_interno'=>'OC','periodo'=>$periodo];
        DB::table('sucursales_correlativos')->insertOrIgnore($key+['ultimo_numero'=>0]);
        $q=DB::table('sucursales_correlativos')->where($key);
        $row=$q->lockForUpdate()->first();
        abort_unless($row,503,'Correlativo no disponible.');
        $n=(int)$row->ultimo_numero;
        // Si existe un número manual previo, saltarlo sin reutilizarlo.
        do {
            abort_if(++$n>999999,409,'Correlativo anual agotado.');
            $numero='OC-'.$s->codigo.'-'.$periodo.'-'.str_pad((string)$n,6,'0',STR_PAD_LEFT);
        } while(DB::table('ordenes_compra')->where('numero',$numero)->exists());
        $q->update(['ultimo_numero'=>$n]);
        return $numero;
    }
}
