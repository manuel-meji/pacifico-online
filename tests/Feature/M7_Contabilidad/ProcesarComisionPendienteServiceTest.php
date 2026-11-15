<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ComerciosM7GatewayInterface;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Services\M7_Contabilidad\ComisionPendienteRepository;
use App\Services\M7_Contabilidad\ProcesarComisionPendienteService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class ProcesarComisionPendienteServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(
            'comisiones_contables_pendientes',
            function (Blueprint $table): void {
                $table->id('id_comision_pendiente');
                $table->uuid('evento_id')->unique();
                $table->unsignedBigInteger('id_pedido');
                $table->unsignedBigInteger('id_subpedido')->unique();
                $table->unsignedBigInteger('id_comercio');
                $table->timestamp('fecha_evento');
                $table->string('estado', 20)->default('PENDIENTE');
                $table->unsignedInteger('intentos')->default(0);
                $table->decimal('monto_comision', 15, 2)->nullable();
                $table->text('ultimo_error')->nullable();
                $table->timestamp('fecha_procesamiento')->nullable();
                $table->timestamps();
            }
        );
    }

    public function test_comision_existente_pasa_a_disponible(): void
    {
        $gateway = Mockery::mock(
            ComerciosM7GatewayInterface::class
        );

        $gateway->shouldReceive('obtenerComisionSubpedido')
            ->once()
            ->with(11)
            ->andReturn(1500.50);

        $repositorio = new ComisionPendienteRepository;

        $repositorio->registrar(
            eventoId: '11111111-1111-4111-8111-111111111111',
            pedidoId: 10,
            subpedidoId: 11,
            comercioId: 1,
            fechaEvento: '2026-11-12 12:00:00',
        );

        $servicio = new ProcesarComisionPendienteService(
            $gateway,
            $repositorio
        );

        $resultado = $servicio->intentar(11);

        $this->assertTrue($resultado);

        $this->assertDatabaseHas(
            'comisiones_contables_pendientes',
            [
                'id_subpedido' => 11,
                'estado' => 'DISPONIBLE',
                'monto_comision' => 1500.50,
            ]
        );
    }

    public function test_err_004_deja_la_comision_pendiente(): void
    {
        $gateway = Mockery::mock(
            ComerciosM7GatewayInterface::class
        );

        $gateway->shouldReceive('obtenerComisionSubpedido')
            ->once()
            ->with(11)
            ->andThrow(
                ErrorNucleoException::recursoNoEncontrado(
                    'La comisión del subpedido aún no se ha calculado.'
                )
            );

        $repositorio = new ComisionPendienteRepository;

        $repositorio->registrar(
            eventoId: '11111111-1111-4111-8111-111111111111',
            pedidoId: 10,
            subpedidoId: 11,
            comercioId: 1,
            fechaEvento: '2026-11-12 12:00:00',
        );

        $servicio = new ProcesarComisionPendienteService(
            $gateway,
            $repositorio
        );

        $resultado = $servicio->intentar(11);

        $this->assertFalse($resultado);

        $this->assertDatabaseHas(
            'comisiones_contables_pendientes',
            [
                'id_subpedido' => 11,
                'estado' => 'PENDIENTE',
                'intentos' => 1,
            ]
        );

        $this->assertNotNull(
            DB::table('comisiones_contables_pendientes')
                ->where('id_subpedido', 11)
                ->value('ultimo_error')
        );
    }

    public function test_reintento_posterior_pasa_de_pendiente_a_disponible(): void
    {
        $gateway = Mockery::mock(
            ComerciosM7GatewayInterface::class
        );

        $gateway->shouldReceive('obtenerComisionSubpedido')
            ->once()
            ->with(11)
            ->andThrow(
                ErrorNucleoException::recursoNoEncontrado()
            );

        $gateway->shouldReceive('obtenerComisionSubpedido')
            ->once()
            ->with(11)
            ->andReturn(800.00);

        $repositorio = new ComisionPendienteRepository;

        $repositorio->registrar(
            eventoId: '11111111-1111-4111-8111-111111111111',
            pedidoId: 10,
            subpedidoId: 11,
            comercioId: 1,
            fechaEvento: '2026-11-12 12:00:00',
        );

        $servicio = new ProcesarComisionPendienteService(
            $gateway,
            $repositorio
        );

        $this->assertFalse(
            $servicio->intentar(11)
        );

        $this->assertTrue(
            $servicio->intentar(11)
        );

        $this->assertDatabaseHas(
            'comisiones_contables_pendientes',
            [
                'id_subpedido' => 11,
                'estado' => 'DISPONIBLE',
                'monto_comision' => 800.00,
                'intentos' => 1,
            ]
        );
    }

    public function test_error_distinto_de_err_004_se_propaga(): void
    {
        $gateway = Mockery::mock(
            ComerciosM7GatewayInterface::class
        );

        $gateway->shouldReceive('obtenerComisionSubpedido')
            ->once()
            ->with(11)
            ->andThrow(
                ErrorNucleoException::proveedorNoResponde(
                    'M2'
                )
            );

        $repositorio = new ComisionPendienteRepository;

        $repositorio->registrar(
            eventoId: '11111111-1111-4111-8111-111111111111',
            pedidoId: 10,
            subpedidoId: 11,
            comercioId: 1,
            fechaEvento: '2026-11-12 12:00:00',
        );

        $servicio = new ProcesarComisionPendienteService(
            $gateway,
            $repositorio
        );

        $this->expectException(
            ErrorNucleoException::class
        );

        $servicio->intentar(11);
    }

    public function test_registrar_dos_veces_no_duplica_la_comision(): void
    {
        $repositorio = new ComisionPendienteRepository;

        $repositorio->registrar(
            eventoId: '11111111-1111-4111-8111-111111111111',
            pedidoId: 10,
            subpedidoId: 11,
            comercioId: 1,
            fechaEvento: '2026-11-12 12:00:00',
        );

        $repositorio->registrar(
            eventoId: '11111111-1111-4111-8111-111111111111',
            pedidoId: 10,
            subpedidoId: 11,
            comercioId: 1,
            fechaEvento: '2026-11-12 12:00:00',
        );

        $this->assertDatabaseCount(
            'comisiones_contables_pendientes',
            1
        );
    }

    public function test_comision_disponible_no_aparece_como_pendiente(): void
    {
        $repositorio = app(
            ComisionPendienteRepository::class
        );

        $repositorio->registrar(
            eventoId: '11111111-1111-4111-8111-111111111111',
            pedidoId: 10,
            subpedidoId: 11,
            comercioId: 2,
            fechaEvento: '2026-11-13T10:00:00-06:00',
        );

        $repositorio->marcarDisponible(
            11,
            1500.50
        );

        $pendientes = $repositorio->pendientes();

        $this->assertCount(0, $pendientes);
    }
}
