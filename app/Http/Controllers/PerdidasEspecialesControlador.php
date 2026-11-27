<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\PlanPerdidaEspecial;
use App\Services\ContextoCompras;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** V109F4A. No admite pérdidas con referencias ni recuperación histórica. */
final class PerdidasEspecialesControlador extends AjustesGeneralesControlador
{
    public static function habilitado(): bool {
        $contracts=config('perdidas.contratos_especiales');
        return config('perdidas.especiales_verificadas')===true
            &&config('perdidas.clase_operacion')==='AJUSTE'
            &&config('perdidas.tipo_kardex_salida')===2
            &&config('perdidas.aislamiento_verificado')===true
            &&config('perdidas.estado_aviso_pendiente')==='PENDIENTE'
            &&is_array($contracts)&&$contracts!==[]&&array_diff(array_keys($contracts),PlanPerdidaEspecial::CODIGOS)===[];
    }
    protected function codigos(): array {
        abort_unless(self::habilitado(),409,'Pérdidas especiales todavía no habilitadas.');
        return array_keys(config('perdidas.contratos_especiales'));
    }
    protected function prefijo(): string {return 'PER-';}
    protected function plan(object $m,string $cantidad,object $stock,string $cpp): array {
        return (new PlanPerdidaEspecial())->calcular($m,$cantidad,$stock,$cpp,config('perdidas.contratos_especiales',[]));
    }
    protected function tipoKardex(object $o): int {
        abort_unless(in_array((int)$o->motivo_codigo_snapshot,$this->codigos(),true),409,'Motivo especial incompatible.');
        return config('perdidas.tipo_kardex_salida');
    }
    protected function evaluacionAviso(array $plan): string {
        $state=$plan['evaluacion_aviso_sii']??null;
        abort_unless(in_array($state,['NO_APLICA',config('perdidas.estado_aviso_pendiente')],true),409,'Estado de evaluación no acreditado.');
        return $state;
    }
    protected function verificarTributario(object $o,array $plan): void {
        abort_unless($o->revision_tributaria==='PENDIENTE'&&$o->evaluacion_aviso_sii===$this->evaluacionAviso($plan),409,'Evaluación tributaria modificada; requiere revisión.');
    }
    public function capacidades(Request $r){
        if(!self::habilitado())return response()->json(['habilitado'=>false,'motivos'=>[]])->header('Cache-Control','private, no-store');
        return response()->json(DB::transaction(function()use($r){
            app(ContextoCompras::class)->resolver($r,true);
            $rows=DB::table('inventario_motivos as m')->join('inventario_grupos_motivos as g','g.id','=','m.grupo_id')
                ->join('inventario_tipos_movimiento as t','t.id','=','m.tipo_movimiento_id')
                ->whereIn('m.codigo',$this->codigos())->where('g.activo',1)->where('t.activo',1)->where('t.codigo','SALIDA')
                ->select('m.*','g.codigo as grupo_codigo','t.naturaleza')->orderBy('m.codigo')->get();
            $plan=new PlanPerdidaEspecial();$motives=[];
            foreach($rows as $m){
                try{$c=$plan->contrato($m,config('perdidas.contratos_especiales'));}
                catch(HttpExceptionInterface $e){if($e->getStatusCode()!==409)throw $e;continue;}
                $motives[]=['codigo'=>$m->codigo,'nombre'=>$m->nombre,'evaluar_aviso_sii'=>$c['aviso']===1];
            }
            return ['habilitado'=>true,'motivos'=>$motives,'emision_automatica'=>false];
        },3))->header('Cache-Control','private, no-store');
    }
}
