<?php

declare(strict_types=1);

namespace Tests\Unit\M7_Contabilidad;

use App\Services\M7_Contabilidad\CalculadoraBalanceGeneral;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CalculadoraBalanceGeneralTest extends TestCase
{
    private CalculadoraBalanceGeneral $calculadora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculadora = new CalculadoraBalanceGeneral();
    }

    public function test_calcula_balance_general_equilibrado(): void
    {
        $cuentas = [
            ['tipo' => 'ACTIVO', 'saldo_centimos' => 150000],
            ['tipo' => 'PASIVO', 'saldo_centimos' => 50000],
            ['tipo' => 'PATRIMONIO', 'saldo_centimos' => 70000],
            ['tipo' => 'INGRESO', 'saldo_centimos' => 40000],
            ['tipo' => 'GASTO', 'saldo_centimos' => 10000],
        ];

        $resultado = $this->calculadora->calcular($cuentas);

        $this->assertSame(150000, $resultado['activos_centimos']);
        $this->assertSame(50000, $resultado['pasivos_centimos']);
        $this->assertSame(100000, $resultado['patrimonio_centimos']);
        $this->assertSame(30000, $resultado['resultado_acumulado_centimos']);
        $this->assertTrue($resultado['cuadra']);
    }

    public function test_detecta_balance_no_equilibrado(): void
    {
        $cuentas = [
            ['tipo' => 'ACTIVO', 'saldo_centimos' => 100000],
            ['tipo' => 'PASIVO', 'saldo_centimos' => 30000],
            ['tipo' => 'PATRIMONIO', 'saldo_centimos' => 20000],
        ];

        $resultado = $this->calculadora->calcular($cuentas);

        $this->assertFalse($resultado['cuadra']);
    }

    public function test_balance_sin_movimientos(): void
    {
        $resultado = $this->calculadora->calcular([]);

        $this->assertSame(0, $resultado['activos_centimos']);
        $this->assertSame(0, $resultado['pasivos_centimos']);
        $this->assertSame(0, $resultado['patrimonio_centimos']);
        $this->assertSame(0, $resultado['resultado_acumulado_centimos']);
        $this->assertTrue($resultado['cuadra']);
    }

    public function test_rechaza_tipo_de_cuenta_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculadora->calcular([
            ['tipo' => 'OTRO', 'saldo_centimos' => 10000],
        ]);
    }

    public function test_suma_varias_cuentas_del_mismo_tipo(): void
    {
        $cuentas = [
            ['tipo' => 'ACTIVO', 'saldo_centimos' => 60000],
            ['tipo' => 'ACTIVO', 'saldo_centimos' => 40000],
            ['tipo' => 'PASIVO', 'saldo_centimos' => 25000],
            ['tipo' => 'PATRIMONIO', 'saldo_centimos' => 75000],
        ];

        $resultado = $this->calculadora->calcular($cuentas);

        $this->assertSame(100000, $resultado['activos_centimos']);
        $this->assertSame(25000, $resultado['pasivos_centimos']);
        $this->assertSame(75000, $resultado['patrimonio_centimos']);
        $this->assertTrue($resultado['cuadra']);
    }
}
