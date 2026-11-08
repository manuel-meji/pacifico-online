<?php

declare(strict_types=1);

namespace Tests\Unit\M2_Comercios;

use App\Services\M2_Comercios\Comision\CalculoMontoFijo;
use App\Services\M2_Comercios\Comision\CalculoPorcentaje;
use Tests\TestCase;

class CalculoComisionTest extends TestCase
{
    public function test_soluciones_del_milenio_calcula_8_por_ciento(): void
    {
        $estrategia = new CalculoPorcentaje();

        $resultado = $estrategia->calcular('24500.00', '8.00');

        $this->assertSame('1960.00', $resultado);
    }

    public function test_temulandia_calcula_monto_fijo(): void
    {
        $estrategia = new CalculoMontoFijo();

        $resultado = $estrategia->calcular('25000.00', '1000.00');

        $this->assertSame('1000.00', $resultado);
    }

    public function test_elegancia_calcula_10_por_ciento(): void
    {
        $estrategia = new CalculoPorcentaje();

        $resultado = $estrategia->calcular('55000.00', '10.00');

        $this->assertSame('5500.00', $resultado);
    }

    public function test_deportes_pacifico_calcula_9_por_ciento(): void
    {
        $estrategia = new CalculoPorcentaje();

        $resultado = $estrategia->calcular('21458.00', '9.00');

        $this->assertSame('1931.22', $resultado);
    }

    public function test_cafe_volcan_calcula_7_por_ciento(): void
    {
        $estrategia = new CalculoPorcentaje();

        $resultado = $estrategia->calcular('21941.00', '7.00');

        $this->assertSame('1535.87', $resultado);
    }
}