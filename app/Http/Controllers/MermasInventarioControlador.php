<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\PlanPerdidaInventario;
use App\Services\ContextoCompras;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MermasInventarioControlador extends AjustesGeneralesControlador
{
    public static function habilitado(): bool {
        return config('perdidas.mermas_verificadas')===true
            &&config('perdidas.clase_operacion')==='AJUSTE'
            &&config('perdidas.tipo_kardex_salida')===2
            &&config('perdidas.aislamiento_verificado')===true;
    }
    public function capacidades(Request $r){
        if(!self::habilitado())return response()->json(['habilitado'=>false,'motivos'=>[]])->header('Cache-Control','private, no-store');
        return response()->json(DB::transaction(function()use($r){
            app(ContextoCompras::class)->resolver($r,true);
            $motivos=DB::table('inventario_motivos as m')
                ->join('inventario_grupos_motivos as g','g.id','=','m.grupo_id')
                ->join('inventario_tipos_movimiento as t','t.id','=','m.tipo_movimiento_id')
                ->whereIn('m.codigo',PlanPerdidaInventario::MERMAS_SIMPLES)->where('g.codigo',300)
                ->where('m.activo',1)->where('g.activo',1)->where('t.activo',1)
                ->where('m.regla','MERMA')->where('t.naturaleza','SALIDA')->where('t.codigo','SALIDA')
                ->where('m.politica_cpp','VIGENTE')->where('m.ajuste_rapido',0)->where('m.requiere_referencia',0)
                ->where('m.requiere_aprobacion',1)->where('m.requiere_respaldo',1)->where('m.revision_tributaria',1)
                ->where('m.evaluar_aviso_sii',0)->orderBy('m.codigo')->get(['m.codigo','m.nombre']);
            return ['habilitado'=>true,'motivos'=>$motivos];
        },3))->header('Cache-Control','private, no-store');
    }
    protected function codigos(): array {
        abort_unless(self::habilitado(),409,'Mermas todavía no habilitadas.');
        return PlanPerdidaInventario::MERMAS_SIMPLES;
    }
    protected function prefijo(): string {return 'MER-';}
    protected function plan(object $m,string $cantidad,object $stock,string $cpp): array {
        return (new PlanPerdidaInventario())->calcular($m,$cantidad,$stock,$cpp);
    }
    protected function tipoKardex(object $o): int {
        abort_unless(in_array((int)$o->motivo_codigo_snapshot,$this->codigos(),true),409,'Motivo de merma incompatible.');
        return config('perdidas.tipo_kardex_salida');
    }
}
