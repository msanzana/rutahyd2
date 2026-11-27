<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\LectorRecuperacionPerdida;
use App\Services\PlanRecuperacionPerdida;
use Illuminate\Support\Facades\DB;
use Brick\Math\BigDecimal;

/** V109F5C: solicitud y confirmación de recuperación, sin tocar el documento original. */
final class RecuperacionesPerdidaControlador extends AjustesGeneralesControlador
{
    public static function habilitado(): bool {
        return config('perdidas.recuperacion_verificada')===true
            &&config('perdidas.recuperacion_persistencia_verificada')===true
            &&config('perdidas.clase_operacion')==='AJUSTE'
            &&config('perdidas.tipo_kardex_entrada')===1
            &&config('perdidas.aislamiento_verificado')===true;
    }
    protected function codigos(): array {
        abort_unless(self::habilitado(),409,'Recuperaciones todavía no habilitadas.');
        return [410];
    }
    protected function prefijo(): string {return 'REC-';}
    protected function reglas(): array {
        return parent::reglas()+['kardex_id'=>'required|integer|min:1'];
    }
    protected function evaluar(array $v,int $sid,?int $propia=null): array {
        $this->codigos();
        $m=DB::table('inventario_motivos as m')->join('inventario_grupos_motivos as g','g.id','=','m.grupo_id')
            ->join('inventario_tipos_movimiento as t','t.id','=','m.tipo_movimiento_id')->where('m.codigo',410)
            ->where('m.activo',1)->where('g.activo',1)->where('t.activo',1)->where('t.codigo','ENTRADA')
            ->select('m.*','g.codigo as grupo_codigo','t.naturaleza')->lockForUpdate()->first();
        abort_unless($m,409,'Motivo de recuperación no disponible.');
        $x=(new LectorRecuperacionPerdida())->leer((int)$v['kardex_id'],$sid,$propia);
        abort_unless($x['producto_id']===(int)$v['producto_id']&&$x['bodega_id']===(int)$v['bodega_id'],409,'Producto o bodega no corresponden a la pérdida.');
        $p=DB::table('productos')->where('id',$x['producto_id'])->lockForUpdate()->first();
        $b=DB::table('bodegas')->where('id',$x['bodega_id'])->first();
        $plan=(new PlanRecuperacionPerdida())->calcular($m,$v['cantidad'],$x['stock'],$x['cpp'],$x['base_valorizada'],$x['costo_historico'],$x['cantidad_perdida'],$x['cantidad_recuperada']);
        $plan['referencia_kardex_id']=$x['referencia_kardex_id'];
        $plan['operacion_origen_id']=$x['operacion_origen_id'];
        return [$m,$b,$p,$plan,$x['stock']];
    }
    protected function cabeceraReferencia(array $plan): array {return ['referencia_kardex_id'=>$plan['referencia_kardex_id']];}
    protected function detalleReferencia(array $plan): array {return ['kardex_referencia_id'=>$plan['referencia_kardex_id']];}
    protected function validarReintentoReferencia(object $o,object $l,array $v): void {
        abort_unless((int)$o->referencia_kardex_id===(int)$v['kardex_id']&&(int)$l->kardex_referencia_id===(int)$v['kardex_id'],409,'Token de otra referencia.');
    }
    protected function solicitudGuardada(object $o,object $l): array {
        abort_unless((int)$o->referencia_kardex_id>0&&(int)$o->referencia_kardex_id===(int)$l->kardex_referencia_id
            &&$o->referencia_tipo_documento_id===null&&$o->referencia_folio===null,409,'Referencia guardada inconsistente.');
        return ['motivo_codigo'=>410,'producto_id'=>$l->producto_id,'bodega_id'=>$l->bodega_id,'cantidad'=>(string)$l->cantidad,'kardex_id'=>$o->referencia_kardex_id];
    }
    protected function verificarTributario(object $o,array $plan): void {
        abort_unless($o->revision_tributaria==='PENDIENTE'&&$o->evaluacion_aviso_sii==='NO_APLICA',409,'Estado tributario inconsistente.');
    }
    protected function verificarConfirmador(object $o,int $uid): void {
        abort_unless((int)$o->aprobado_por===$uid&&(int)$o->creado_por!==$uid,409,'Confirma el aprobador independiente.');
    }
    protected function tipoKardex(object $o): int {return config('perdidas.tipo_kardex_entrada');}
    protected function aplicarCpp(object $p,array $plan): void {
        abort_unless(BigDecimal::of((string)$p->costo_promedio_ponderado)->isEqualTo($plan['cpp_anterior']),409,'CPP previo inconsistente.');
        DB::table('productos')->where('id',$p->id)->update(['costo_promedio_ponderado'=>$plan['cpp_actual']]);
    }
    protected function mensajeConfirmacion(): string {return 'Recuperación física confirmada a costo histórico. CPP ponderado actualizado; revisión tributaria pendiente.';}
}
