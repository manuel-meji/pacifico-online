<?php

declare(strict_types=1);

namespace Tests\Unit\M2_Comercios;

use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Models\M2_Comercios\ComisionSubpedido;
use App\Models\M2_Comercios\MembresiaComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Support\Carbon;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;

class RegistrarComisionTest extends TestCase
{
    use PreparaM2;

    private ComerciosService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-07 12:00:00');

        $this->servicio = app(ComerciosService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_despacho_guarda_comision_correcta_y_se_puede_consultar(): void
    {
        $comercio = $this->crearComercio([
            'nombre_comercial' => 'Soluciones del Milenio',
        ]);

        $this->crearComision($comercio, 'porcentaje', 8);

        $this->mock(SubpedidosServiceInterface::class, function ($mock) use ($comercio): void {
            $mock->shouldReceive('obtenerSubpedido')
                ->once()
                ->with(7001)
                ->andReturn([
                    'id_subpedido' => 7001,
                    'id_comercio' => $comercio->id_comercio,
                    'subtotal' => 24500.00,
                    'estado_subpedido' => 'despachado',
                ]);
        });

        $this->servicio->registrarComisionDespacho(
            7001,
            '11111111-1111-4111-8111-111111111111'
        );

        $comision = $this->servicio->obtenerComisionSubpedido(7001);

        $this->assertSame(1960.0, $comision['monto_comision']);
        $this->assertSame(24500.0, $comision['base_utilizada']);
        $this->assertSame('CRC', $comision['moneda']);
    }

    public function test_registrar_dos_veces_el_mismo_subpedido_deja_una_sola_comision(): void
    {
        $comercio = $this->crearComercio();

        $this->crearComision($comercio, 'porcentaje', 8);

        $this->mock(SubpedidosServiceInterface::class, function ($mock) use ($comercio): void {
            $mock->shouldReceive('obtenerSubpedido')
                ->once()
                ->with(7002)
                ->andReturn([
                    'id_subpedido' => 7002,
                    'id_comercio' => $comercio->id_comercio,
                    'subtotal' => 24500.00,
                    'estado_subpedido' => 'despachado',
                ]);
        });

        $this->servicio->registrarComisionDespacho(
            7002,
            '22222222-2222-4222-8222-222222222222'
        );

        $this->servicio->registrarComisionDespacho(
            7002,
            '33333333-3333-4333-8333-333333333333'
        );

        $this->assertSame(
            1,
            ComisionSubpedido::query()
                ->where('id_subpedido', 7002)
                ->count()
        );
    }

    public function test_membresia_vigente_usa_comision_preferencial(): void
    {
        $comercio = $this->crearComercio();

        $this->crearComision($comercio, 'porcentaje', 8);

        MembresiaComercio::query()->create([
            'id_comercio' => $comercio->id_comercio,
            'nombre' => 'Preferencial',
            'comision_preferencial' => 5,
            'inicio' => '2026-11-01',
            'fin' => '2026-11-30',
            'activa' => true,
        ]);

        $this->mock(SubpedidosServiceInterface::class, function ($mock) use ($comercio): void {
            $mock->shouldReceive('obtenerSubpedido')
                ->once()
                ->with(7003)
                ->andReturn([
                    'id_subpedido' => 7003,
                    'id_comercio' => $comercio->id_comercio,
                    'subtotal' => 20000.00,
                    'estado_subpedido' => 'despachado',
                ]);
        });

        $this->servicio->registrarComisionDespacho(
            7003,
            '44444444-4444-4444-8444-444444444444'
        );

        $this->assertSame(
            1000.0,
            $this->servicio
                ->obtenerComisionSubpedido(7003)['monto_comision']
        );
    }

    public function test_subpedido_no_despachado_no_registra_comision(): void
    {
        $comercio = $this->crearComercio();

        $this->crearComision($comercio, 'porcentaje', 8);

        $this->mock(SubpedidosServiceInterface::class, function ($mock) use ($comercio): void {
            $mock->shouldReceive('obtenerSubpedido')
                ->once()
                ->with(7004)
                ->andReturn([
                    'id_subpedido' => 7004,
                    'id_comercio' => $comercio->id_comercio,
                    'subtotal' => 24500.00,
                    'estado_subpedido' => 'pagado',
                ]);
        });

        $this->servicio->registrarComisionDespacho(
            7004,
            '55555555-5555-4555-8555-555555555555'
        );

        $this->assertSame(0, ComisionSubpedido::query()->count());
    }

    public function test_sin_configuracion_vigente_no_registra_comision(): void
    {
        $comercio = $this->crearComercio();

        $this->mock(SubpedidosServiceInterface::class, function ($mock) use ($comercio): void {
            $mock->shouldReceive('obtenerSubpedido')
                ->once()
                ->with(7005)
                ->andReturn([
                    'id_subpedido' => 7005,
                    'id_comercio' => $comercio->id_comercio,
                    'subtotal' => 24500.00,
                    'estado_subpedido' => 'despachado',
                ]);
        });

        $this->servicio->registrarComisionDespacho(
            7005,
            '66666666-6666-4666-8666-666666666666'
        );

        $this->assertSame(0, ComisionSubpedido::query()->count());
    }
}