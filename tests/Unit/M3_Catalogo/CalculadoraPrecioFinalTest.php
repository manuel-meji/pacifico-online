<?php

declare(strict_types=1);

namespace Tests\Unit\M3_Catalogo;

use App\Services\M3_Catalogo\CalculadoraPrecioFinal;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalculadoraPrecioFinalTest extends TestCase
{
    private CalculadoraPrecioFinal $calculadora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculadora = new CalculadoraPrecioFinal();
    }

    /**
     * Los seis casos de los escenarios de demostración.
     *
     * @return array<string, array{string, string, string|null, string}>
     */
    public static function escenariosDeDemostracion(): array
    {
        return [
            'Escenario 1 · Mouse (porcentaje 5)'          => ['10000', 'porcentaje', '5', '9500.00'],
            'Escenario 2 · Camiseta S (porcentaje 15)'    => ['8990', 'porcentaje', '15', '7641.50'],
            'Escenario 2 · Camiseta XL (porcentaje 15)'   => ['9490', 'porcentaje', '15', '8066.50'],
            'Escenario 2 · Botella (monto fijo 1000)'     => ['6750', 'monto_fijo', '1000', '5750.00'],
            'Escenario 2 · Taza (monto fijo 500)'         => ['3333', 'monto_fijo', '500', '2833.00'],
            'Escenario 1 · Audífonos (sin descuento)'     => ['15000', 'ninguno', null, '15000.00'],
        ];
    }

    #[DataProvider('escenariosDeDemostracion')]
    public function test_calcula_el_precio_final_de_los_escenarios(
        string $precio,
        string $tipo,
        ?string $valor,
        string $esperado
    ): void {
        $this->assertSame($esperado, $this->calculadora->calcular($precio, $tipo, $valor));
    }

    public function test_sin_tipo_de_descuento_devuelve_el_precio(): void
    {
        $this->assertSame('15000.00', $this->calculadora->calcular('15000'));
        $this->assertSame('15000.00', $this->calculadora->calcular('15000', null));
    }

    public function test_el_precio_final_nunca_es_menor_que_cero(): void
    {
        $this->assertSame('0.00', $this->calculadora->calcular('100', 'monto_fijo', '500'));
        $this->assertSame('0.00', $this->calculadora->calcular('100', 'porcentaje', '100'));
    }

    public function test_redondea_a_dos_decimales_con_la_mitad_hacia_arriba(): void
    {
        $this->assertSame('1.01', $this->calculadora->calcular('1.005'));
        $this->assertSame('2.68', $this->calculadora->calcular('2.675'));
        $this->assertSame('0.00', $this->calculadora->calcular('0.004'));
        $this->assertSame('0.01', $this->calculadora->calcular('0.01', 'porcentaje', '50'));
    }

    public function test_acepta_numeros_float_sin_perder_precision(): void
    {
        $this->assertSame('7641.50', $this->calculadora->calcular(8990.0, 'porcentaje', 15.0));
    }

    public function test_rechaza_un_tipo_de_descuento_desconocido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculadora->calcular('1000', 'regalo', '10');
    }

    public function test_rechaza_un_precio_negativo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculadora->calcular('-1');
    }

    public function test_rechaza_un_porcentaje_fuera_de_rango(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculadora->calcular('1000', 'porcentaje', '101');
    }

    public function test_rechaza_un_porcentaje_negativo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculadora->calcular('1000', 'porcentaje', '-1');
    }

    public function test_rechaza_un_monto_fijo_negativo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculadora->calcular('1000', 'monto_fijo', '-5');
    }

    public function test_rechaza_un_valor_que_no_es_numerico(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculadora->calcular('1000', 'porcentaje', 'abc');
    }

    public function test_exige_valor_cuando_hay_descuento(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculadora->calcular('1000', 'porcentaje', null);
    }
}