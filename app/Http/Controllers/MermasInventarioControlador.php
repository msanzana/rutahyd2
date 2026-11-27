<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Services\PlanPerdidaInventario;

final class MermasInventarioControlador extends AjustesGeneralesControlador
{
    public static function habilitado(): bool {
        return config('perdidas.mermas_verificadas')===true
            &&config('perdidas.clase_operacion')==='AJUSTE'
            &&config('perdidas.tipo_kardex_salida')===2
            &&config('perdidas.aislamiento_verificado')===true;
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
