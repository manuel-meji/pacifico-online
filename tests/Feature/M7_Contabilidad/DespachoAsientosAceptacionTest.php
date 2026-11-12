<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use App\Events\M5_Pedidos\PedidoConfirmado;
use App\Events\M5_Pedidos\SubpedidoDespachado;
use App\Listeners\M7_Contabilidad\ProcesarPedidoConfirmadoListener;
use App\Listeners\M7_Contabilidad\ProcesarSubpedidoDespachadoListener;
use App\Models\Nucleo\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * Aceptación de M7: PedidoConfirmado + SubpedidoDespachado generan el asiento de
 * venta y el de costo de ventas, cuadrados, sin duplicarse, y consultables por
 * el libro diario. M4 se simula con un doble; M5 solo aporta las clases de evento.
 */
class DespachoAsientosAceptacionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const EVENTO_PEDIDO = '11111111-1111-4111-8111-aaaaaaaaaaaa';
    private const EVENTO_DESPACHO = '33333333-3333-4333-8333-bbbbbbbbbbbb';

    protected function migrateFreshUsing(): array
    {
        return [
            '--path' => database_path('migrations/Nucleo'),
            '--realpath' => true,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('comercios', function (Blueprint $table): void {
            $table->id('id_comercio');
        });
        DB::table('comercios')->insert([['id_comercio' => 1], ['id_comercio' => 2]]);

        Schema::create('cuentas_contables', function (Blueprint $table): void {
            $table->id('id_cuenta_contable');
            $table->unsignedBigInteger('id_comercio');
            $table->string('codigo', 30);
            $table->string('nombre', 150);
            $table->string('tipo', 20);
            $table->unsignedInteger('nivel')->default(1);
            $table->unsignedBigInteger('id_cuenta_padre')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('eventos_procesados', function (Blueprint $table): void {
            $table->id('id_evento_procesado');
            $table->uuid('evento_id')->unique();
            $table->string('tipo_evento', 80);
            $table->string('estado', 20)->default('PENDIENTE');
            $table->timestamp('fecha_recepcion')->useCurrent();
            $table->timestamp('fecha_procesamiento')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->timestamps();
        });

        Schema::create('asientos_contables', function (Blueprint $table): void {
            $table->id('id_asiento');
            $table->unsignedBigInteger('id_comercio');
            $table->unsignedBigInteger('id_pedido')->nullable();
            $table->unsignedBigInteger('id_subpedido')->nullable();
            $table->unsignedBigInteger('id_pago')->nullable();
            $table->uuid('evento_id');
            $table->string('tipo_evento', 80);
            $table->string('tipo', 40);
            $table->timestamp('fecha')->useCurrent();
            $table->string('descripcion', 255)->nullable();
            $table->string('estado', 20)->default('REGISTRADO');
            $table->unsignedBigInteger('id_asiento_original')->nullable();
            $table->timestamps();
        });

        Schema::create('detalle_asientos', function (Blueprint $table): void {
            $table->id('id_detalle_asiento');
            $table->unsignedBigInteger('id_asiento');
            $table->unsignedBigInteger('id_cuenta_contable');
            $table->unsignedBigInteger('id_centro_costo')->nullable();
            $table->unsignedInteger('numero_linea');
            $table->decimal('debe', 15, 2)->default(0);
            $table->decimal('haber', 15, 2)->default(0);
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        // Doble de M4: costo conocido de 40.50 para el comercio 1.
        $this->app->instance(
            CostosProductosVendidosGatewayInterface::class,
            new class implements CostosProductosVendidosGatewayInterface
            {
                public function obtenerCostosProductosVendidos(int $subpedidoId): array
                {
                    return [
                        'id_subpedido' => $subpedidoId,
                        'id_comercio' => 1,
                        'variaciones' => [[
                            'id_variacion' => 100,
                            'cantidad' => 2,
                            'costo_unitario' => 20.25,
                            'costo_total' => 40.50,
                        ]],
                        'costo_total' => 40.50,
                    ];
                }
            }
        );
    }

    private function crearPlanDeCuentas(int $idComercio): void
    {
        $cuentas = [
            ['1.1.02', 'Cuentas por cobrar', 'ACTIVO'],
            ['1.1.03', 'Inventario', 'ACTIVO'],
            ['4.1.01', 'Ventas', 'INGRESO'],
            ['5.1.01', 'Costo de ventas', 'GASTO'],
        ];

        foreach ($cuentas as [$codigo, $nombre, $tipo]) {
            DB::table('cuentas_contables')->insert([
                'id_comercio' => $idComercio,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'tipo' => $tipo,
                'nivel' => 1,
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function eventoPedidoConfirmado(): PedidoConfirmado
    {
        /** @var PedidoConfirmado $evento */
        $evento = (new ReflectionClass(PedidoConfirmado::class))->newInstanceWithoutConstructor();

        $evento->id_evento = self::EVENTO_PEDIDO;
        $evento->fecha_evento = '2026-11-10T12:00:00Z';
        $evento->id_pedido = 10;
        $evento->id_cliente = 20;
        $evento->id_comercio = 1;
        $evento->id_subpedido = 11;
        $evento->monto_total = 100.25;
        $evento->reservas = [];
        $evento->subpedidos = [[
            'id_subpedido' => 11,
            'id_comercio' => 1,
            'subtotal' => 100.25,
            'costo_envio' => 0.0,
            'impuesto' => 0.0,
            'reservas' => [],
        ]];

        return $evento;
    }

    private function eventoSubpedidoDespachado(): SubpedidoDespachado
    {
        /** @var SubpedidoDespachado $evento */
        $evento = (new ReflectionClass(SubpedidoDespachado::class))->newInstanceWithoutConstructor();

        $evento->id_evento = self::EVENTO_DESPACHO;
        $evento->fecha_evento = '2026-11-10T12:00:00Z';
        $evento->fecha = '2026-11-10T12:00:00Z';
        $evento->fecha_despacho = '2026-11-10T12:00:00Z';
        $evento->evento = 'SubpedidoDespachado';
        $evento->id_subpedido = 11;
        $evento->id_pedido = 10;
        $evento->id_comercio = 1;
        $evento->subtotal = 100.25;
        $evento->costo_envio = 0.0;
        $evento->estado_subpedido = 'despachado';
        $evento->guia_transporte = null;
        $evento->reservas = [];

        return $evento;
    }

    private function encargado(int $idComercio): Usuario
    {
        return UsuarioFactory::new()->create([
            'rol' => 'encargado_comercio',
            'id_comercio' => $idComercio,
            'activo' => true,
        ]);
    }

    private function recorrerPedidoYDespacho(): void
    {
        app(ProcesarPedidoConfirmadoListener::class)->handle($this->eventoPedidoConfirmado());
        app(ProcesarSubpedidoDespachadoListener::class)->handle($this->eventoSubpedidoDespachado());
    }

    public function test_confirmar_y_despachar_genera_asiento_de_venta_y_de_costo_cuadrados(): void
    {
        $this->crearPlanDeCuentas(1);

        $this->recorrerPedidoYDespacho();

        $this->assertDatabaseCount('asientos_contables', 2);
        $this->assertDatabaseHas('asientos_contables', ['tipo' => 'VENTA', 'id_subpedido' => 11, 'estado' => 'REGISTRADO']);
        $this->assertDatabaseHas('asientos_contables', ['tipo' => 'COSTO_VENTA', 'id_subpedido' => 11, 'estado' => 'REGISTRADO']);

        foreach (DB::table('asientos_contables')->pluck('id_asiento') as $idAsiento) {
            $debe = (float) DB::table('detalle_asientos')->where('id_asiento', $idAsiento)->sum('debe');
            $haber = (float) DB::table('detalle_asientos')->where('id_asiento', $idAsiento)->sum('haber');

            $this->assertEqualsWithDelta($debe, $haber, 0.001, "El asiento {$idAsiento} no cuadra.");
            $this->assertGreaterThan(0, $debe);
        }
    }

    public function test_el_libro_diario_muestra_los_dos_asientos_cuadrados_por_la_api(): void
    {
        $this->crearPlanDeCuentas(1);
        $this->recorrerPedidoYDespacho();

        $respuesta = $this->actingAs($this->encargado(1), 'web')
            ->getJson('/api/m7/v1/libro-diario')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cantidad', 2);

        $tipos = collect($respuesta->json('data.asientos'))->pluck('tipo')->sort()->values()->all();
        $this->assertSame(['Costo de ventas', 'Venta automática'], $tipos);

        foreach ($respuesta->json('data.asientos') as $asiento) {
            $this->assertEqualsWithDelta($asiento['totalDebe'], $asiento['totalHaber'], 0.001);
        }

        $this->assertEqualsWithDelta(
            $respuesta->json('data.totalDebe'),
            $respuesta->json('data.totalHaber'),
            0.001
        );
        //100.25 de la venta + 40.50 del costo.
        $this->assertEqualsWithDelta(140.75, $respuesta->json('data.totalDebe'), 0.001);
    }

    public function test_repetir_los_eventos_no_duplica_los_asientos(): void
    {
        $this->crearPlanDeCuentas(1);

        $this->recorrerPedidoYDespacho();
        $this->recorrerPedidoYDespacho();

        $this->assertDatabaseCount('asientos_contables', 2);
        $this->assertDatabaseCount('detalle_asientos', 4);
        $this->assertDatabaseCount('eventos_procesados', 2);
    }

    public function test_otro_comercio_no_ve_los_asientos_generados(): void
    {
        $this->crearPlanDeCuentas(1);
        $this->recorrerPedidoYDespacho();

        $this->actingAs($this->encargado(2), 'web')
            ->getJson('/api/m7/v1/libro-diario')
            ->assertOk()
            ->assertJsonPath('data.cantidad', 0);
    }

    public function test_sin_plan_de_cuentas_no_se_generan_asientos_ni_se_rompe_el_despacho(): void
    {
        //Escenario del reinicio de Supabase del 10/11: sin cuentas cargadas.
        $this->recorrerPedidoYDespacho();

        $this->assertDatabaseCount('asientos_contables', 0);
    }
}