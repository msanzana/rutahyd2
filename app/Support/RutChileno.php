<?php
declare(strict_types=1);
namespace App\Support;

/** Validación del RUT chileno para altas de usuarios productivos. */
final class RutChileno
{
    public static function normalizar(string $rut): ?string
    {
        $valor=strtoupper(preg_replace('/[.\s-]+/u', '', trim($rut)) ?? '');
        if (!preg_match('/^([0-9]{7,8})([0-9K])$/D', $valor, $m)) return null;
        $suma=0;
        $factor=2;
        for($i=strlen($m[1])-1;$i>=0;$i--){
            $suma+=((int)$m[1][$i])*$factor;
            $factor=$factor===7?2:$factor+1;
        }
        $resto=11-($suma%11);
        $dv=$resto===11?'0':($resto===10?'K':(string)$resto);
        if ($m[2]!==$dv) return null;
        return $m[1].'-'.$dv;
    }
}
