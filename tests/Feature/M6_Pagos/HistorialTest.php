<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M6_Pagos\HistorialService;
use App\Services\Nucleo\ContextoComercio;
use Tests\Feature\M6_Pagos\Concerns\PreparaM6;
use Tests\TestCase;

/**
 * CU-008 Consultar historial de pagos y facturas (RF-008, RN-008).
 * GET /api/m6/v1/historial?id_comercio=&id_cliente=
 *
 * Pedido 8942 (doble de M5): cliente 77; subpedido 1001 → comercio 11, subpedido 1002 → comercio 12.
 */
class HistorialTest extends TestCase
{
    use PreparaM6;

    /** CA-M6-H01 · CU-008: historial del cliente con su pago y sus dos facturas. */
    public function test_historial_por_cliente(): void
    {
        $this->procesarPago()->assertCreated();

        $this->getJson('/api/m6/v1/historial?id_cliente=77')
            ->assertOk()
            ->assertJsonCount(1, 'pagos')
            ->assertJsonCount(2, 'facturas')
            ->assertJsonPath('pagos.0.id_pedido', self::PEDIDO_CONFIRMADO);
    }

    /** CA-M6-H02 · CU-008: historial del comercio 11, solo con su factura. */
    public function test_historial_por_comercio(): void
    {
        $this->procesarPago()->assertCreated();

        $this->getJson('/api/m6/v1/historial?id_comercio=11')
            ->assertOk()
            ->assertJsonCount(1, 'pagos')
            ->assertJsonCount(1, 'facturas')
            ->assertJsonPath('facturas.0.id_comercio', 11);
    }

    /** CP-M6-H03 · RN-008 / RN-12: un comercio no ve el historial de otro (404). */
    public function test_un_comercio_no_ve_el_historial_de_otro(): void
    {
        $this->procesarPago()->assertCreated();
        app(ContextoComercio::class)->establecer(11);

        $this->expectException(ErrorNucleoException::class);

        app(HistorialService::class)->consultar(null, 12);
    }

    /** CP-M6-H04 · Sin pagos ni facturas → 404 ERR-M6-020. */
    public function test_sin_resultados_responde_err_020(): void
    {
        $this->getJson('/api/m6/v1/historial?id_cliente=77')
            ->assertNotFound()
            ->assertJsonPath('error.codigo', 'ERR-M6-020')
            ->assertJsonPath('error.detalles.motivo', 'SIN_RESULTADOS');
    }

    /** CP-M6-H05 · Sin ningún filtro (y sin sesión) → 422. */
    public function test_sin_filtros_responde_422(): void
    {
        $this->getJson('/api/m6/v1/historial')->assertStatus(422);
    }
}
