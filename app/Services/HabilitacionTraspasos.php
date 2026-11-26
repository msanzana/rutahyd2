<?php
declare(strict_types=1);
namespace App\Services;

/** Control adicional a autorización, contexto y validaciones del motor. */
final class HabilitacionTraspasos
{
    public function pares(int $sid): array {
        if(config('traspasos.escrituras')!==true||$sid<=0)return [];
        $raw=config('traspasos.pares_recinto_verificados',[]);
        if(!is_array($raw))return [];
        $out=[];
        foreach($raw as $pair){
            if(!is_array($pair)||count($pair)!==3||!isset($pair['sucursal'],$pair['origen'],$pair['destino']))continue;
            if(!is_int($pair['sucursal'])||!is_int($pair['origen'])||!is_int($pair['destino']))continue;
            if($pair['sucursal']!==$sid||$pair['origen']<=0||$pair['destino']<=0||$pair['origen']===$pair['destino'])continue;
            $out[]=$pair;
        }
        return $out;
    }
    public function permite(int $sid,int $from,int $to): bool {
        foreach($this->pares($sid) as $p)if($p['origen']===$from&&$p['destino']===$to)return true;
        return false;
    }
}
