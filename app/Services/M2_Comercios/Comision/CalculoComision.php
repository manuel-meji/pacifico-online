<?php

declare(strict_types=1);

namespace App\Services\M2_Comercios\Comision;

interface CalculoComision
{
    public function calcular(string $subtotal, string $valor): string;
}