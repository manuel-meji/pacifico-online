<?php

declare(strict_types=1);

namespace Tests\Unit\M7_Contabilidad;

use App\Events\M6_Pagos\FacturaGenerada;
use App\Events\M6_Pagos\PagoConfirmado;
use App\Services\M7_Contabilidad\CuadrePagoM6Service;
use App\Services\M7_Contabilidad\ValidadorPartidaDoble;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CuadrePagoM6Test extends TestCase
{
    public function test_pago_sin_facturas_conserva_todo_el_saldo_pendiente(): void
    {
        $resultado = $this->servicio()->evaluar($this->pago(), []);

        $this->assertSame([
            'estado' => 'PENDIENTE_FACTURAS',
            'cobro_centimos' => 10000,
            'facturado_centimos' => 0,
            'saldo_pendiente_centimos' => 10000,
            'exceso_centimos' => 0,
            'requiere_revision' => false,
        ], $resultado);
    }

    public function test_facturacion_parcial_no_se_marca_como_diferencia_definitiva(): void
    {
        $resultado = $this->servicio()->evaluar($this->pago(), [$this->factura(1, 60)]);

        $this->assertSame('PENDIENTE_FACTURAS', $resultado['estado']);
        $this->assertSame(4000, $resultado['saldo_pendiente_centimos']);
        $this->assertSame(false, $resultado['requiere_revision']);
    }

    public function test_facturas_multicomercio_coincidentes_completan_el_cuadre_por_importes(): void
    {
        $resultado = $this->servicio()->evaluar($this->pago(0.30), [
            $this->factura(1, 0.10),
            $this->factura(2, 0.20),
        ]);

        $this->assertSame('COMPLETO_POR_IMPORTES', $resultado['estado']);
        $this->assertSame(30, $resultado['facturado_centimos']);
        $this->assertSame(0, $resultado['saldo_pendiente_centimos']);
        $this->assertSame(0, $resultado['exceso_centimos']);
        $this->assertSame(false, $resultado['requiere_revision']);
    }

    public function test_exceso_se_marca_para_revision_sin_reducir_el_monto_facturado(): void
    {
        $resultado = $this->servicio()->evaluar($this->pago(), [
            $this->factura(1, 60),
            $this->factura(2, 50),
        ]);

        $this->assertSame('DIFERENCIA', $resultado['estado']);
        $this->assertSame(11000, $resultado['facturado_centimos']);
        $this->assertSame(1000, $resultado['exceso_centimos']);
        $this->assertSame(0, $resultado['saldo_pendiente_centimos']);
        $this->assertSame(true, $resultado['requiere_revision']);
    }

    public function test_factura_repetida_no_incrementa_el_total_del_cuadre(): void
    {
        $factura = $this->factura(1, 100);

        $resultado = $this->servicio()->evaluar($this->pago(), [$factura, $factura]);

        $this->assertSame(10000, $resultado['facturado_centimos']);
        $this->assertSame('COMPLETO_POR_IMPORTES', $resultado['estado']);
    }

    public function test_rechaza_factura_repetida_con_importes_distintos(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('datos financieros contradictorios');

        $this->servicio()->evaluar($this->pago(), [$this->factura(1, 60), $this->factura(1, 70)]);
    }

    public function test_rechaza_factura_de_otro_pago(): void
    {
        $factura = new FacturaGenerada(
            '22222222-2222-4222-8222-222222222222', 1, 10, 101, 1, 100, '', '2026-11-13T12:00:00Z',
            999, 100, 0, 0,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mismo pago y pedido');

        $this->servicio()->evaluar($this->pago(), [$factura]);
    }

    public function test_rechaza_dos_facturas_distintas_para_el_mismo_subpedido(): void
    {
        $otra = new FacturaGenerada(
            '33333333-3333-4333-8333-333333333333', 2, 10, 101, 1, 50, '', '2026-11-13T12:00:00Z',
            7, 50, 0, 0,
        );
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('más de una factura');

        $this->servicio()->evaluar($this->pago(), [$this->factura(1, 50), $otra]);
    }

    public function test_rechaza_desglose_que_no_coincide_con_el_monto(): void
    {
        $factura = new FacturaGenerada(
            '22222222-2222-4222-8222-222222222222', 1, 10, 101, 1, 100, '', '2026-11-13T12:00:00Z',
            7, 80, 10, 5,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('desglose de la factura');

        $this->servicio()->evaluar($this->pago(), [$factura]);
    }

    #[DataProvider('montosInvalidos')]
    public function test_rechaza_montos_de_cobro_invalidos(float $monto): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servicio()->evaluar($this->pago($monto), []);
    }

    /** @return array<string, array{float}> */
    public static function montosInvalidos(): array
    {
        return [
            'cero' => [0.0],
            'negativo' => [-1.0],
            'fraccion de centimo' => [1.001],
            'infinito' => [INF],
        ];
    }

    private function servicio(): CuadrePagoM6Service
    {
        return new CuadrePagoM6Service(new ValidadorPartidaDoble);
    }

    private function pago(float $monto = 100): PagoConfirmado
    {
        return new PagoConfirmado('11111111-1111-4111-8111-111111111111', 10, 7, $monto, '2026-11-13T12:00:00Z');
    }

    private function factura(int $idFactura, float $monto): FacturaGenerada
    {
        return new FacturaGenerada(
            sprintf('22222222-2222-4222-8222-%012d', $idFactura),
            $idFactura, 10, 100 + $idFactura, $idFactura, $monto, '', '2026-11-13T12:00:00Z',
            7, $monto, 0, 0,
        );
    }
}
