<?php

declare(strict_types=1);

namespace Tests\Unit\M7_Contabilidad;

use App\Services\M7_Contabilidad\ValidadorPartidaDoble;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidadorPartidaDobleTest extends TestCase
{
    public function test_acepta_movimientos_balanceados_con_centimos_exactos(): void
    {
        $validador = new ValidadorPartidaDoble;

        $validador->validar([
            ['debe' => '0.10', 'haber' => '0'],
            ['debe' => '0.20', 'haber' => '0'],
            ['debe' => '0', 'haber' => '0.30'],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_acepta_importes_maximos_de_la_columna_decimal(): void
    {
        $validador = new ValidadorPartidaDoble;

        $validador->validar([
            ['debe' => '9999999999999.99', 'haber' => '0'],
            ['debe' => '0', 'haber' => '9999999999999.99'],
        ]);

        $this->addToAssertionCount(1);
    }

    /** @param list<array<string, mixed>> $movimientos */
    #[DataProvider('asientosInvalidos')]
    public function test_rechaza_asientos_invalidos(array $movimientos, string $mensaje): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($mensaje);

        (new ValidadorPartidaDoble)->validar($movimientos);
    }

    /** @return array<string, array{list<array<string, mixed>>, string}> */
    public static function asientosInvalidos(): array
    {
        return [
            'sin movimientos' => [[], 'al menos dos movimientos'],
            'un movimiento' => [[['debe' => '1', 'haber' => '0']], 'al menos dos movimientos'],
            'desbalance' => [[['debe' => '1.00', 'haber' => '0'], ['debe' => '0', 'haber' => '0.99']], 'igual al total del haber'],
            'ambos lados' => [[['debe' => '1', 'haber' => '1'], ['debe' => '0', 'haber' => '1']], 'pero no en ambos'],
            'ambos cero' => [[['debe' => '0', 'haber' => '0'], ['debe' => '0', 'haber' => '1']], 'pero no en ambos'],
            'importe negativo' => [[['debe' => '-1', 'haber' => '0'], ['debe' => '0', 'haber' => '1']], 'decimal no negativo'],
            'exceso de decimales' => [[['debe' => '1.001', 'haber' => '0'], ['debe' => '0', 'haber' => '1']], 'decimal no negativo'],
            'exceso de enteros' => [[['debe' => '10000000000000', 'haber' => '0'], ['debe' => '0', 'haber' => '1']], 'decimal no negativo'],
            'notacion exponencial' => [[['debe' => '1e2', 'haber' => '0'], ['debe' => '0', 'haber' => '100']], 'decimal no negativo'],
            'faltan campos' => [[['debe' => '1'], ['debe' => '0', 'haber' => '1']], 'debe y haber'],
            'flotante no exacto' => [[['debe' => 1.0, 'haber' => '0'], ['debe' => '0', 'haber' => '1']], 'debe y haber'],
        ];
    }
}
