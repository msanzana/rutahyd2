<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Motor de cálculo para la futura confirmación atómica de recepciones.
 * NO actualiza el costo, las existencias ni los movimientos.
 *
 * Usa únicamente DECIMAL de MariaDB/MySQL con parámetros enlazados:
 * nunca convierte cantidades ni costos a float. Acepta varias líneas
 * de un mismo producto y redondea una sola vez a cuatro decimales.
 */
final class CalculadoraCostoPromedioRecepcion
{
    /** @param list<array{cantidad:string,costo:string}> $lineas */
    public function calcular(string $cantidadAnterior, string $costoAnterior, array $lineas): string
    {
        $q0=$this->decimal($cantidadAnterior,6,18,'Cantidad de stock anterior');
        $c0=$this->decimal($costoAnterior,4,11,'Costo promedio anterior');
        if($lineas===[] || count($lineas)>100){
            throw new InvalidArgumentException('La recepción necesita entre 1 y 100 líneas');
        }
        if($this->mayorQueCero($q0) && !$this->mayorQueCero($c0)){
            // Con stock previo sin valor: exige regularización contable antes de recibir.
            throw new InvalidArgumentException('El stock anterior necesita valorización');
        }

        $parts=[];$params=[$q0,$c0,$q0];
        foreach($lineas as $linea){
            if(!is_array($linea) ||
                !array_key_exists('cantidad',$linea) || !array_key_exists('costo',$linea)){
                throw new InvalidArgumentException('Línea de recepción incompleta');
            }
            $cantidad=$this->decimal((string)$linea['cantidad'],6,18,'Cantidad recibida');
            $costo=$this->decimal((string)$linea['costo'],4,11,'Costo neto unitario');
            if(!$this->mayorQueCero($cantidad)){
                throw new InvalidArgumentException('La cantidad recibida debe ser positiva');
            }
            $parts[]='SELECT CAST(? AS DECIMAL(24,6)) AS q, CAST(? AS DECIMAL(15,4)) AS c';
            $params[]=$cantidad;$params[]=$costo;
        }

        $sql='SELECT CAST(ROUND(('
            .'CAST(? AS DECIMAL(24,6))*CAST(? AS DECIMAL(15,4))'
            .'+SUM(rec.q*rec.c))'
            .'/(CAST(? AS DECIMAL(24,6))+SUM(rec.q)),4) AS CHAR) AS promedio'
            .' FROM ('.implode(' UNION ALL ',$parts).') AS rec';
        $registro=DB::selectOne($sql,$params);
        $raw=$registro?->promedio;
        if(!is_string($raw) && !is_numeric($raw)){
            throw new RuntimeException('No fue posible calcular el costo ponderado');
        }
        $raw=(string)$raw;
        if(!preg_match('/^[0-9]+(?:\.[0-9]{1,4})?$/D',$raw)){
            throw new RuntimeException('Costo ponderado fuera de precisión');
        }
        [$entera,$fraccion]=array_pad(explode('.',$raw,2),2,'');
        if(strlen($entera)>11){
            throw new RuntimeException('Costo ponderado excede capacidad de productos');
        }
        return $entera.'.'.str_pad($fraccion,4,'0',STR_PAD_RIGHT);
    }

    private function decimal(string $valor,int $escala,int $digitosEnteros,string $etiqueta): string
    {
        if(!preg_match('/^(?:0|[1-9][0-9]{0,'.($digitosEnteros-1).'})'
            .'(?:\.[0-9]{1,'.$escala.'})?$/D',$valor)){
            throw new InvalidArgumentException($etiqueta.' debe ser decimal exacto');
        }
        return $valor;
    }

    private function mayorQueCero(string $valor): bool
    {
        return preg_match('/[1-9]/',$valor)===1;
    }
}
