<?php

declare(strict_types=1);

namespace App\Services\M2_Comercios\Comision;

class CalculoPorcentaje implements CalculoComision
{
    public function calcular(string $subtotal, string $valor): string
    {
        return number_format(
            round((float) $subtotal * (float) $valor / 100, 2),
            2,
            '.',
            ''
        );
    }
}