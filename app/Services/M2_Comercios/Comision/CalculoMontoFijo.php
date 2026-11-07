<?php

declare(strict_types=1);

namespace App\Services\M2_Comercios\Comision;

class CalculoMontoFijo implements CalculoComision
{
    public function calcular(string $subtotal, string $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}