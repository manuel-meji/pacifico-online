<?php

declare(strict_types=1);

namespace Tests\Feature\M2_Comercios\Eventos;

use App\Contracts\M5_Pedidos\SubpedidosServiceInterface;
use App\Events\M2_Comercios\ComercioAprobado;
use App\Events\M2_Comercios\ComercioReactivado;
use App\Events\M2_Comercios\ComercioSuspendido;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Models\M2_Comercios\ComisionSubpedido;
use Illuminate\Support\Carbon;
use ReflectionClass;
use Tests\Feature\M2_Comercios\Concerns\PreparaM2;
use Tests\TestCase;
use App\Listeners\M2_Comercios\RegistrarComision;
use App\Services\M2_Comercios\ComerciosService;
use RuntimeException;

class M2_EventosTest extends TestCase
{
    use PreparaM2;

    public function test_subpedido_despachado_activa_listener_y_registra_comision(): void
    {
        $comercio = $this->crearComercio([
            'nombre_comercial' => 'Soluciones del Milenio',
        ]);

        $this->crearComision($comercio, 'porcentaje', 8);

        $this->mock(SubpedidosServiceInterface::class, function ($mock) use ($comercio): void {
            $mock->shouldReceive('obtenerSubpedido')
                ->once()
                ->with(8001)
                ->andReturn([
                    'id_subpedido' => 8001,
                    'id_comercio' => $comercio->id_comercio,
                    'subtotal' => 24500.00,
                    'estado_subpedido' => 'despachado',
                ]);
        });

        $evento = (new ReflectionClass(SubpedidoDespachado::class))
            ->newInstanceWithoutConstructor();

        $evento->id_evento = '77777777-7777-4777-8777-777777777777';
        $evento->id_subpedido = 8001;
        $evento->id_comercio = $comercio->id_comercio;
        $evento->subtotal = 24500.00;

        event($evento);

        $this->assertDatabaseHas('comisiones_subpedido', [
            'id_subpedido' => 8001,
            'id_comercio' => $comercio->id_comercio,
            'monto' => 1960.00,
            'moneda' => 'CRC',
        ]);

        $this->assertSame(1, ComisionSubpedido::query()->count());
    }

    public function test_comercio_aprobado_tiene_exactamente_el_payload_del_contrato(): void
    {
        $evento = new ComercioAprobado(
            '11111111-1111-4111-8111-111111111111',
            Carbon::parse('2026-11-07 12:00:00'),
            1,
            'Activo',
            'Soluciones del Milenio'
        );

        $this->assertEqualsCanonicalizing(
            [
                'id_evento',
                'fecha_hora',
                'id_comercio',
                'estado',
                'nombre_comercial',
            ],
            array_keys(get_object_vars($evento))
        );

        $this->assertSame('Activo', $evento->estado);
    }

    public function test_comercio_suspendido_tiene_exactamente_el_payload_del_contrato(): void
    {
        $evento = new ComercioSuspendido(
            '22222222-2222-4222-8222-222222222222',
            Carbon::parse('2026-11-07 12:00:00'),
            1,
            'Suspendido',
            'Documentos vencidos'
        );

        $this->assertEqualsCanonicalizing(
            [
                'id_evento',
                'fecha_hora',
                'id_comercio',
                'estado',
                'motivo',
            ],
            array_keys(get_object_vars($evento))
        );

        $this->assertSame('Suspendido', $evento->estado);
        $this->assertSame('Documentos vencidos', $evento->motivo);
    }

    public function test_comercio_reactivado_tiene_exactamente_el_payload_del_contrato(): void
    {
        $evento = new ComercioReactivado(
            '33333333-3333-4333-8333-333333333333',
            Carbon::parse('2026-11-07 12:00:00'),
            1,
            'Activo',
            'Soluciones del Milenio'
        );

        $this->assertEqualsCanonicalizing(
            [
                'id_evento',
                'fecha_hora',
                'id_comercio',
                'estado',
                'nombre_comercial',
            ],
            array_keys(get_object_vars($evento))
        );

        $this->assertSame('Activo', $evento->estado);
    }

    public function test_listener_no_propaga_error_si_falla_registro_de_comision(): void
    {
        $comercios = $this->mock(ComerciosService::class);

        $comercios
            ->shouldReceive('registrarComisionDespacho')
            ->once()
            ->with(7009, 'evento-error-001')
            ->andThrow(new RuntimeException('Fallo simulado al registrar comisión'));

        $evento = (new \ReflectionClass(SubpedidoDespachado::class))
            ->newInstanceWithoutConstructor();

        $evento->id_subpedido = 7009;
        $evento->id_evento = 'evento-error-001';

        $listener = new RegistrarComision($comercios);

        $listener->handle($evento);

        $this->assertTrue(true);
    }

}