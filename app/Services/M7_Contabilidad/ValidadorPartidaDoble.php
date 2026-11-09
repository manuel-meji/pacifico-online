<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use InvalidArgumentException;

class ValidadorPartidaDoble
{
    /**
     * @param  list<array{debe: string, haber: string}>  $movimientos
     */
    public function validar(array $movimientos): void
    {
        if (count($movimientos) < 2) {
            throw new InvalidArgumentException('El asiento debe tener al menos dos movimientos.');
        }

        $totalDebe = 0;
        $totalHaber = 0;

        foreach ($movimientos as $movimiento) {
            if (! is_array($movimiento)
                || ! isset($movimiento['debe'], $movimiento['haber'])
                || ! is_string($movimiento['debe'])
                || ! is_string($movimiento['haber'])) {
                throw new InvalidArgumentException('Cada movimiento debe incluir debe y haber como importes decimales.');
            }

            $debe = $this->aCentimos($movimiento['debe']);
            $haber = $this->aCentimos($movimiento['haber']);

            if (($debe > 0) === ($haber > 0)) {
                throw new InvalidArgumentException('Cada movimiento debe tener importe positivo en el debe o el haber, pero no en ambos.');
            }

            if ($debe > PHP_INT_MAX - $totalDebe || $haber > PHP_INT_MAX - $totalHaber) {
                throw new InvalidArgumentException('El total del asiento excede el rango permitido.');
            }

            $totalDebe += $debe;
            $totalHaber += $haber;
        }

        if ($totalDebe !== $totalHaber) {
            throw new InvalidArgumentException('El total del debe debe ser igual al total del haber.');
        }
    }

    public function aCentimos(string $importe): int
    {
        if (! preg_match('/\A(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?\z/', $importe)) {
            throw new InvalidArgumentException('El importe debe ser un decimal no negativo con hasta 13 enteros y dos decimales.');
        }

        [$entero, $decimal] = array_pad(explode('.', $importe, 2), 2, '');

        return (int) $entero * 100 + (int) str_pad($decimal, 2, '0');
    }
}
