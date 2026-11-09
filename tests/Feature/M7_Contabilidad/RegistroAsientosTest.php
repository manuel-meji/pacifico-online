<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Services\M7_Contabilidad\AsientoService;
use App\Services\M7_Contabilidad\DTOs\CostoVentaDTO;
use App\Services\M7_Contabilidad\DTOs\VentaDTO;
use App\Services\M7_Contabilidad\ProcesarPedidoConfirmadoService;
use App\Services\M7_Contabilidad\ProcesarSubpedidoDespachadoService;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class RegistroAsientosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new LogicException('Estas pruebas requieren SQLite en memoria.');
        }

        Schema::create('cuentas_contables', function (Blueprint $tabla): void {
            $tabla->id('id_cuenta_contable');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->string('codigo');
            $tabla->boolean('activa');
        });
        Schema::create('eventos_procesados', function (Blueprint $tabla): void {
            $tabla->id('id_evento_procesado');
            $tabla->uuid('evento_id')->unique();
            $tabla->string('tipo_evento');
            $tabla->string('estado');
            $tabla->timestamp('fecha_recepcion');
            $tabla->timestamp('fecha_procesamiento')->nullable();
            $tabla->text('ultimo_error')->nullable();
            $tabla->timestamps();
        });
        Schema::create('asientos_contables', function (Blueprint $tabla): void {
            $tabla->id('id_asiento');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->unsignedBigInteger('id_pedido')->nullable();
            $tabla->unsignedBigInteger('id_subpedido')->nullable();
            $tabla->uuid('evento_id');
            $tabla->string('tipo_evento');
            $tabla->string('tipo');
            $tabla->timestamp('fecha');
            $tabla->string('estado');
            $tabla->timestamps();
        });
        Schema::create('detalle_asientos', function (Blueprint $tabla): void {
            $tabla->id('id_detalle_asiento');
            $tabla->unsignedBigInteger('id_asiento');
            $tabla->unsignedBigInteger('id_cuenta_contable');
            $tabla->unsignedInteger('numero_linea');
            $tabla->decimal('debe', 15, 2);
            $tabla->decimal('haber', 15, 2);
            $tabla->timestamps();
        });
    }

    private function cuentas(int $comercio): void
    {
        foreach (['1.1.02', '4.1.01', '5.1.01', '1.1.03'] as $codigo) {
            DB::table('cuentas_contables')->insert(['id_comercio' => $comercio, 'codigo' => $codigo, 'activa' => true]);
        }
    }

    /** @return list<VentaDTO> */
    private function ventas(string $evento = '11111111-1111-4111-8111-111111111111'): array
    {
        return [
            new VentaDTO($evento, '2026-11-08T12:00:00Z', 10, 11, 1, '100.25'),
            new VentaDTO($evento, '2026-11-08T12:00:00Z', 10, 12, 2, '200.50'),
        ];
    }

    private function costo(): CostoVentaDTO
    {
        return new CostoVentaDTO('22222222-2222-4222-8222-222222222222', '2026-11-08T12:00:00Z', 10, 11, 1, '40.50');
    }

    public function test_registra_ventas_por_comercio_y_no_duplica_el_evento(): void
    {
        $this->cuentas(1);
        $this->cuentas(2);
        $ventas = $this->ventas();
        $procesador = app(ProcesarPedidoConfirmadoService::class);

        app(ContextoComercio::class)->ejecutarComoSistema(function () use ($procesador, $ventas): void {
            $procesador->procesar($ventas[0]->eventoId, $ventas);
            $procesador->procesar($ventas[0]->eventoId, $ventas);
        });

        $this->assertDatabaseCount('asientos_contables', 2);
        $this->assertDatabaseCount('detalle_asientos', 4);
        $this->assertDatabaseHas('asientos_contables', ['id_pedido' => 10, 'id_subpedido' => 11, 'id_comercio' => 1, 'tipo' => 'VENTA']);
        $this->assertDatabaseHas('asientos_contables', ['id_pedido' => 10, 'id_subpedido' => 12, 'id_comercio' => 2, 'tipo' => 'VENTA']);
        $this->assertDatabaseHas('eventos_procesados', ['evento_id' => $ventas[0]->eventoId, 'estado' => 'PROCESADO']);
        $this->assertTrue($procesador->yaProcesado($ventas[0]->eventoId));
        $this->assertSame(300.75, (float) DB::table('detalle_asientos')->sum('debe'));
        $this->assertSame(300.75, (float) DB::table('detalle_asientos')->sum('haber'));
    }

    public function test_un_evento_nuevo_no_duplica_una_venta_del_mismo_subpedido(): void
    {
        $this->cuentas(1);
        $this->cuentas(2);
        $ventas = $this->ventas();
        $nuevas = $this->ventas('33333333-3333-4333-8333-333333333333');
        $procesador = app(ProcesarPedidoConfirmadoService::class);

        app(ContextoComercio::class)->ejecutarComoSistema(function () use ($procesador, $ventas, $nuevas): void {
            $procesador->procesar($ventas[0]->eventoId, $ventas);
            $procesador->procesar($nuevas[0]->eventoId, $nuevas);
        });

        $this->assertDatabaseCount('asientos_contables', 2);
        $this->assertDatabaseHas('eventos_procesados', ['evento_id' => $nuevas[0]->eventoId, 'estado' => 'PROCESADO']);
    }

    public function test_si_faltan_cuentas_se_revierte_el_pedido_completo(): void
    {
        $this->cuentas(1);
        $ventas = $this->ventas();

        try {
            app(ContextoComercio::class)->ejecutarComoSistema(fn () => app(ProcesarPedidoConfirmadoService::class)->procesar($ventas[0]->eventoId, $ventas));
            $this->fail('Se esperaba una excepción por cuentas faltantes.');
        } catch (LogicException $excepcion) {
            $this->assertStringContainsString('cuentas contables', $excepcion->getMessage());
        }

        $this->assertDatabaseCount('asientos_contables', 0);
        $this->assertDatabaseCount('detalle_asientos', 0);
        $this->assertDatabaseCount('eventos_procesados', 0);
    }

    public function test_registra_costo_y_es_idempotente(): void
    {
        $this->cuentas(1);
        $costo = $this->costo();
        $procesador = app(ProcesarSubpedidoDespachadoService::class);

        app(ContextoComercio::class)->ejecutarComoSistema(function () use ($procesador, $costo): void {
            $procesador->procesar($costo);
            $procesador->procesar($costo);
        });

        $this->assertDatabaseCount('asientos_contables', 1);
        $this->assertDatabaseCount('detalle_asientos', 2);
        $this->assertDatabaseHas('asientos_contables', ['id_subpedido' => 11, 'id_comercio' => 1, 'tipo' => 'COSTO_VENTA']);
        $this->assertSame(40.50, (float) DB::table('detalle_asientos')->sum('debe'));
        $this->assertSame(40.50, (float) DB::table('detalle_asientos')->sum('haber'));
    }

    public function test_rechaza_costo_invalido_y_no_registra_el_evento(): void
    {
        $costo = new CostoVentaDTO('22222222-2222-4222-8222-222222222222', '2026-11-08', 10, 11, 1, '0');
        try {
            app(ContextoComercio::class)->ejecutarComoSistema(fn () => app(ProcesarSubpedidoDespachadoService::class)->procesar($costo));
            $this->fail('Se esperaba una excepción por importe inválido.');
        } catch (InvalidArgumentException $excepcion) {
            $this->assertStringContainsString('importe positivo', $excepcion->getMessage());
        }
        $this->assertDatabaseCount('asientos_contables', 0);
        $this->assertDatabaseCount('eventos_procesados', 0);
    }

    public function test_rechaza_comercio_distinto_al_contexto(): void
    {
        $this->cuentas(1);
        app(ContextoComercio::class)->establecer(2);

        try {
            app(AsientoService::class)->crearAsientoVenta($this->ventas()[0]);
            $this->fail('Se esperaba rechazo del comercio.');
        } catch (LogicException $excepcion) {
            $this->assertStringContainsString('contexto', $excepcion->getMessage());
        }
        $this->assertDatabaseCount('asientos_contables', 0);
    }

    public function test_asiento_devuelto_expone_totales_y_detalles_balanceados(): void
    {
        $this->cuentas(1);
        app(ContextoComercio::class)->establecer(1);

        $asiento = app(AsientoService::class)->crearAsientoVenta($this->ventas()[0]);

        $this->assertSame('100.25', $asiento->totalDebe());
        $this->assertSame('100.25', $asiento->totalHaber());
        $this->assertTrue($asiento->estaBalanceado());
        $this->assertTrue($asiento->detalles()[0]->esDebito());
        $this->assertTrue($asiento->detalles()[1]->esCredito());
    }

    public function test_un_evento_nuevo_con_importe_distinto_no_modifica_la_venta_existente(): void
    {
        $this->cuentas(1);
        $venta = $this->ventas()[0];
        $nueva = new VentaDTO('33333333-3333-4333-8333-333333333333', $venta->fecha, 10, 11, 1, '120.00');
        $procesador = app(ProcesarPedidoConfirmadoService::class);
        $contexto = app(ContextoComercio::class);

        $contexto->ejecutarComoSistema(fn () => $procesador->procesar($venta->eventoId, [$venta]));
        try {
            $contexto->ejecutarComoSistema(fn () => $procesador->procesar($nueva->eventoId, [$nueva]));
            $this->fail('Se esperaba rechazo por importe diferente.');
        } catch (LogicException $excepcion) {
            $this->assertStringContainsString('no coincide', $excepcion->getMessage());
        }

        $this->assertDatabaseCount('asientos_contables', 1);
        $this->assertDatabaseMissing('eventos_procesados', ['evento_id' => $nueva->eventoId]);
        $this->assertSame(100.25, (float) DB::table('detalle_asientos')->sum('debe'));
    }

    public function test_rechaza_una_repeticion_del_mismo_evento_con_importe_distinto(): void
    {
        $this->cuentas(1);
        $venta = $this->ventas()[0];
        $modificada = new VentaDTO($venta->eventoId, $venta->fecha, 10, 11, 1, '120.00');
        $procesador = app(ProcesarPedidoConfirmadoService::class);
        $contexto = app(ContextoComercio::class);
        $contexto->ejecutarComoSistema(fn () => $procesador->procesar($venta->eventoId, [$venta]));

        try {
            $contexto->ejecutarComoSistema(fn () => $procesador->procesar($venta->eventoId, [$modificada]));
            $this->fail('Se esperaba rechazo por importe diferente.');
        } catch (LogicException $excepcion) {
            $this->assertStringContainsString('no coincide', $excepcion->getMessage());
        }

        $this->assertDatabaseCount('asientos_contables', 1);
        $this->assertSame(100.25, (float) DB::table('detalle_asientos')->sum('debe'));
    }

    public function test_no_procesa_eventos_sin_contexto_de_sistema(): void
    {
        app(ContextoComercio::class)->establecer(1);
        $ventas = $this->ventas();
        try {
            app(ProcesarPedidoConfirmadoService::class)->procesar($ventas[0]->eventoId, $ventas);
            $this->fail('Se esperaba rechazo por falta de contexto de sistema.');
        } catch (LogicException $excepcion) {
            $this->assertStringContainsString('contexto de sistema', $excepcion->getMessage());
        }
        $this->assertDatabaseCount('eventos_procesados', 0);
    }

    public function test_no_acepta_el_mismo_identificador_para_eventos_de_distinto_tipo(): void
    {
        $this->cuentas(1);
        $venta = $this->ventas()[0];
        $costo = new CostoVentaDTO($venta->eventoId, $venta->fecha, 10, 11, 1, '40.50');
        $contexto = app(ContextoComercio::class);

        $contexto->ejecutarComoSistema(fn () => app(ProcesarPedidoConfirmadoService::class)->procesar($venta->eventoId, [$venta]));
        try {
            $contexto->ejecutarComoSistema(fn () => app(ProcesarSubpedidoDespachadoService::class)->procesar($costo));
            $this->fail('Se esperaba rechazo por tipo de evento distinto.');
        } catch (LogicException $excepcion) {
            $this->assertStringContainsString('otro tipo', $excepcion->getMessage());
        }
        $this->assertDatabaseCount('asientos_contables', 1);
    }
}
