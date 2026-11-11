<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos;

use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M5_Pedidos\GestionPedidosServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;
use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM1;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM2;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM5;
use App\Services\M6_Pagos\Adaptadores\AdaptadorM7;
use App\Services\M6_Pagos\Clients\ClienteClient;
use App\Services\M6_Pagos\Clients\ComercioClient;
use App\Services\M6_Pagos\Clients\ContabilidadClient;
use App\Services\M6_Pagos\Clients\PedidoClient;
use Tests\Doubles\M1\FakeClienteClient;
use Tests\Doubles\M1\PortalPublicoFake;
use Tests\Doubles\M2\ComerciosFiscalFake;
use Tests\Doubles\M2\FakeComercioClient;
use Tests\Doubles\M5\FakePedidoClient;
use Tests\Doubles\M5\PedidosContratoFake;
use Tests\Doubles\M7\FakeContabilidadClient;
use Tests\TestCase;

/** AppServiceProvider::registrarPuertosM6(): qué recibe M6 según M6_USAR_DOBLES. */
class PuertosM6BindingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['m6.doble_por_modulo' => []]);
    }

    public function test_por_defecto_los_puertos_resuelven_a_los_dobles(): void
    {
        $this->assertInstanceOf(FakePedidoClient::class, $this->app->make(PedidoClient::class));
        $this->assertInstanceOf(FakeClienteClient::class, $this->app->make(ClienteClient::class));
        $this->assertInstanceOf(FakeComercioClient::class, $this->app->make(ComercioClient::class));
        $this->assertInstanceOf(FakeContabilidadClient::class, $this->app->make(ContabilidadClient::class));
    }

    public function test_el_doble_es_la_misma_instancia_durante_la_peticion(): void
    {
        $this->assertSame($this->app->make(PedidoClient::class), $this->app->make(PedidoClient::class));
    }

    public function test_en_modo_real_los_puertos_resuelven_a_los_adaptadores_sobre_el_contrato(): void
    {
        config(['m6.usar_dobles' => false]);

        $m5 = new PedidosContratoFake;
        $this->app->instance(PortalPublicoContract::class, new PortalPublicoFake);
        $this->app->instance(ComerciosServiceInterface::class, new ComerciosFiscalFake);
        $this->app->instance(GestionPedidosServiceInterface::class, $m5);
        $this->app->instance(PedidosServiceInterface::class, $m5);

        $this->assertInstanceOf(AdaptadorM1::class, $this->app->make(ClienteClient::class));
        $this->assertInstanceOf(AdaptadorM2::class, $this->app->make(ComercioClient::class));
        $this->assertInstanceOf(AdaptadorM5::class, $this->app->make(PedidoClient::class));

        $this->assertSame(8942, $this->app->make(PedidoClient::class)->obtenerDatosPedido(8942)->idPedido);
    }

    public function test_en_modo_real_m7_resuelve_al_adaptador_y_consulta_el_registro_contable(): void
    {
        config(['m6.usar_dobles' => false]);

        $this->app->instance(
            ContabilidadServiceInterface::class,
            new class implements ContabilidadServiceInterface
            {
                public function registrarAsientoDespacho(
                    int $pedidoId,
                    float $totalVenta,
                    float $costoVentas
                ): bool {
                    return true;
                }

                public function consultarRegistroContablePago(int $idPago): array
                {
                    return [
                        'id_pago' => $idPago,
                        'registrado' => false,
                        'asiento_id' => null,
                        'fecha_registro' => null,
                    ];
                }
            }
        );

        $m7 = $this->app->make(ContabilidadClient::class);

        $this->assertInstanceOf(AdaptadorM7::class, $m7);

        $resultado = $m7->consultarRegistroContablePago(1);

        $this->assertFalse($resultado);
    }
}
