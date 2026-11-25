<?php
declare(strict_types=1);

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;

/**
 * Calculadora pura. El servicio transaccional debe obtener los saldos bajo
 * bloqueo y verificar la procedencia del valor anterior; nunca desde el cliente.
 * No efectúa escrituras ni reconoce dos veces el tránsito.
 */
final class CostoPromedioPonderado
{
    public function calcular(
        string $stockAnterior,
        string $cppAnterior,
        string $cantidadEntrada,
        string $valorEntrada,
        ?string $valorAnteriorVerificado = null
    ): array {
        $s = $this->decimal($stockAnterior);
        $c = $this->decimal($cppAnterior);
        $q = $this->decimal($cantidadEntrada);
        $v = $this->decimal($valorEntrada, true);
        $historico = $valorAnteriorVerificado === null
            ? null : $this->decimal($valorAnteriorVerificado, true);
        if ($q->isZero()) {
            throw new DomainException('La entrada valorizada debe tener cantidad positiva.');
        }
        if ($s->isZero()) {
            if ($historico !== null && !$historico->isZero()) {
                throw new DomainException('Existe valor anterior sin existencias.');
            }
            $anterior = BigDecimal::zero();
        } else {
            // Cero explícitamente verificado es distinto de valor desconocido.
            // El saldo monetario exacto prevalece sobre el CPP redondeado.
            if ($historico === null) {
                throw new DomainException('Se requiere saldo monetario anterior verificado.');
            }
            $anterior = $historico;
            if (!$c->isZero() && !$anterior->dividedBy($s, 8, RoundingMode::HALF_UP)->isEqualTo($c)) {
                throw new DomainException('CPP y saldo histórico no coinciden; regularizar antes de confirmar.');
            }
        }
        $saldo = $s->plus($q);
        $total = $anterior->plus($v);
        $cpp = $total->dividedBy($saldo, 8, RoundingMode::HALF_UP);
        if ($cpp->isGreaterThan('99999999999999999999.99999999')) {
            throw new DomainException('CPP excede DECIMAL(28,8).');
        }
        return [
            'stock_anterior' => (string)$s,
            'cpp_anterior' => (string)$c,
            'valor_anterior' => (string)$anterior,
            'cantidad_entrada' => (string)$q,
            'valor_entrada' => (string)$v,
            'stock_posterior' => (string)$saldo,
            'valor_posterior' => (string)$total,
            'cpp_posterior' => (string)$cpp,
        ];
    }

    private function decimal(string $valor, bool $monetario=false): BigDecimal
    {
        if (!preg_match($monetario ? '/\A[0-9]{1,40}(?:\.[0-9]{1,16})?\z/' : '/\A[0-9]{1,20}(?:\.[0-9]{1,8})?\z/', $valor)) {
            throw new DomainException('Valor decimal no válido.');
        }
        return BigDecimal::of($valor);
    }
}
