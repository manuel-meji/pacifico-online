<?php

declare(strict_types=1);

namespace Tests\Feature\M5_Pedidos;

use App\Contracts\M3_Catalogo\CatalogoServiceInterface;
use App\Contracts\M4_Inventario\Dependencias\CatalogoDependencia;
use App\Contracts\M4_Inventario\Dependencias\ComerciosDependencia;
use App\Contracts\M4_Inventario\Dependencias\NucleoDependencia;
use App\Contracts\M4_Inventario\Dependencias\PedidosDependencia;
use App\Contracts\M4_Inventario\InventarioModuleInterface;
use App\Contracts\M4_Inventario\InventarioServiceInterface;
use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use App\Services\M4_Inventario\Dependencias\NucleoReal;
use App\Services\M4_Inventario\Dependencias\PedidosReal;
use App\Services\M4_Inventario\InventarioService;
use App\Services\M7_Contabilidad\Gateways\M4CostosProductosVendidosGateway;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\M4_Inventario\EsquemaInventarioPrueba;
use Tests\TestCase;

/**
 * Prueba de integración real entre M5 (Pedidos), M4 (Inventario) y M7 (Contabilidad).
 *
 * Verifica de extremo a extremo los dos flujos de compra requeridos:
 *  - Flujo 1 (Pruebas 1, 2 y 4): Consulta de stock por comercio dueño, reserva real en checkout
 *    para múltiples comercios, confirmación de reserva, asiento contable de VENTA en M7,
 *    despacho del subpedido, salida física y Kardex en M4, y asiento de COSTO_VENTA en M7.
 *  - Flujo 2 (Prueba 3): Reserva en checkout y cancelación antes de preparación liberando
 *    las unidades reservadas en M4 de vuelta al stock disponible.
 */
class IntegracionM4M7RealTest extends TestCase
{
    use EsquemaInventarioPrueba;

    protected string $prefix = '/api/m5/v1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaNucleo();
        $this->crearEsquemaInventario();
        $this->crearEsquemaContabilidad();

        $this->artisan('migrate', [
            '--path' => 'database/migrations/M5_Pedidos',
            '--realpath' => false,
        ]);

        // Activar integración real M5 <-> M4 <-> M7
        config([
            'integraciones.m5.m4.modo' => 'real',
            'integraciones.m4.nucleo.modo' => 'real',
            'integraciones.m4.m5.modo' => 'real',
        ]);

        $this->app->bind(NucleoDependencia::class, NucleoReal::class);
        $this->app->bind(PedidosDependencia::class, PedidosReal::class);

        // Configurar comercios 1 y 2 activos con sus sedes reales (sede 1 para comercio 1, sede 3 para comercio 2)
        $this->app->bind(ComerciosDependencia::class, fn () => new class implements ComerciosDependencia
        {
            public function obtenerComercioActivo(int $idComercio): array
            {
                return [
                    'id_comercio' => $idComercio,
                    'estado' => 'Activo',
                    'activo' => in_array($idComercio, [1, 2], true),
                    'motivo_estado' => null,
                ];
            }

            public function obtenerSedesComercio(int $idComercio): array
            {
                return [
                    'id_comercio' => $idComercio,
                    'sedes' => $idComercio === 2
                        ? [['id_sede' => 3, 'habilitada' => true]]
                        : [['id_sede' => 1, 'habilitada' => true]],
                ];
            }
        });

        // Catálogo con productos de Comercio 1 (variación 101) y Comercio 2 (variación 202)
        $catalogoMultiComercio = new class implements CatalogoDependencia, CatalogoServiceInterface
        {
            public function obtenerVariacion(int $idVariacion): ?array
            {
                return match ($idVariacion) {
                    101 => [
                        'id_variacion' => 101,
                        'id_producto' => 10,
                        'id_comercio' => 1,
                        'sku' => 'SKU-C1-101',
                        'nombre' => 'Café Chorotega 500g',
                        'precio' => 10000.00,
                        'tarifa_impuesto' => 13.00,
                        'disponible' => true,
                        'producto' => [
                            'id_producto' => 10,
                            'id_comercio' => 1,
                            'nombre' => 'Café Chorotega 500g',
                        ],
                    ],
                    202 => [
                        'id_variacion' => 202,
                        'id_producto' => 20,
                        'id_comercio' => 2,
                        'sku' => 'SKU-C2-202',
                        'nombre' => 'Vasija Guaitil Artesanal',
                        'precio' => 15000.00,
                        'tarifa_impuesto' => 13.00,
                        'disponible' => true,
                        'producto' => [
                            'id_producto' => 20,
                            'id_comercio' => 2,
                            'nombre' => 'Vasija Guaitil Artesanal',
                        ],
                    ],
                    default => null,
                };
            }

            public function listarProductosPublicos(array $filtros = [], int $pagina = 1, int $porPagina = 20): array
            {
                return ['datos' => [], 'paginacion' => ['total_resultados' => 0]];
            }

            public function obtenerDetalleProducto(int $idProducto): ?array
            {
                return null;
            }

            public function obtenerProductosDestacados(int $limite = 8): array
            {
                return [];
            }

            public function listarProductosPorComercio(int $idComercio, int $pagina = 1, int $porPagina = 20): array
            {
                return ['datos' => [], 'paginacion' => ['total_resultados' => 0]];
            }

            public function listarCategorias(): array
            {
                return [];
            }

            public function verificarVariacionActiva(int $idVariacion): bool
            {
                return in_array($idVariacion, [101, 202], true);
            }
        };

        $this->app->instance(CatalogoDependencia::class, $catalogoMultiComercio);
        $this->app->instance(CatalogoServiceInterface::class, $catalogoMultiComercio);

        // Enlazar M4 real e inyectarlo en el gateway real de M7
        $this->app->bind(InventarioServiceInterface::class, InventarioService::class);
        $this->app->bind(InventarioModuleInterface::class, InventarioService::class);
        $this->app->bind(CostosProductosVendidosGatewayInterface::class, M4CostosProductosVendidosGateway::class);

        // Sembrar existencias iniciales en M4:
        // Comercio 1 (sede 1): variación 101 con 10 unidades físicas, costo unitario 4000.00
        $this->existencia([
            'id_comercio' => 1,
            'id_producto' => 10,
            'id_variacion' => 101,
            'id_ubicacion' => 1,
            'cantidad_fisica' => 10,
            'cantidad_reservada' => 0,
            'costo_promedio' => 4000.00,
        ]);

        // Comercio 2 (sede 3): variación 202 con 8 unidades físicas, costo unitario 6500.00
        $this->existencia([
            'id_comercio' => 2,
            'id_producto' => 20,
            'id_variacion' => 202,
            'id_ubicacion' => 3,
            'cantidad_fisica' => 8,
            'cantidad_reservada' => 0,
            'costo_promedio' => 6500.00,
        ]);

        // Sembrar plan de cuentas activo en M7 para Comercio 1 y Comercio 2
        $this->crearPlanDeCuentas(1);
        $this->crearPlanDeCuentas(2);
    }

    private function crearEsquemaContabilidad(): void
    {
        if (! Schema::hasTable('comercios')) {
            Schema::create('comercios', function (Blueprint $table): void {
                $table->id('id_comercio');
            });
            DB::table('comercios')->insert([['id_comercio' => 1], ['id_comercio' => 2]]);
        }

        if (! Schema::hasTable('cuentas_contables')) {
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
        }

        if (! Schema::hasTable('eventos_procesados')) {
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
        }

        if (! Schema::hasTable('asientos_contables')) {
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
        }

        if (! Schema::hasTable('detalle_asientos')) {
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
        }
    }

    private function crearPlanDeCuentas(int $idComercio): void
    {
        $cuentas = [
            ['1.1.02', 'Cuentas por cobrar', 'ACTIVO'],
            ['1.1.03', 'Inventario', 'ACTIVO'],
            ['4.1.01', 'Ingresos por ventas', 'INGRESO'],
            ['5.1.01', 'Costo de ventas', 'GASTO'],
        ];

        foreach ($cuentas as [$codigo, $nombre, $tipo]) {
            DB::table('cuentas_contables')->insert([
                'id_comercio' => $idComercio,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'tipo' => $tipo,
                'nivel' => 3,
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Flujo 1 completo (Pruebas 1, 2 y 4):
     * - Agregar al carrito productos de Comercio 1 y Comercio 2 verificando stock real en M4.
     * - Checkout reserva existencias en el comercio dueño real de cada variación y las confirma.
     * - M7 registra el asiento contable de VENTA por cada subpedido con partida doble cuadrada.
     * - Al despachar cada subpedido, M4 consume la reserva, descuenta stock físico y registra kardex,
     *   y M7 consulta el costo real a M4 y crea el asiento contable de COSTO_VENTA.
     */
    public function test_flujo_1_compra_multicomercio_reserva_confirmacion_despacho_y_asientos_contables(): void
    {
        // 1. Agregar 2 unidades de variación 101 (Comercio 1) y 3 unidades de variación 202 (Comercio 2)
        $resItem1 = $this->postJson("{$this->prefix}/carrito/items", [
            'id_cliente' => 77,
            'id_variacion' => 101,
            'cantidad' => 2,
        ]);
        $resItem1->assertStatus(201);

        $resItem2 = $this->postJson("{$this->prefix}/carrito/items", [
            'id_cliente' => 77,
            'id_variacion' => 202,
            'cantidad' => 3,
        ]);
        $resItem2->assertStatus(201);

        // Verificar que si intenta pedir más del stock disponible en M4 (Comercio 2 solo tiene 8), M5 rechaza con 409
        $resExceso = $this->postJson("{$this->prefix}/carrito/items", [
            'id_cliente' => 77,
            'id_variacion' => 202,
            'cantidad' => 10,
        ]);
        $resExceso->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M5-001');

        // 2. Ejecutar Checkout
        $resCheckout = $this->postJson("{$this->prefix}/pedidos", [
            'id_cliente' => 77,
            'id_direccion_cliente' => 1,
            'metodoPago' => 'tarjeta',
        ]);
        $resCheckout->assertStatus(201);

        $idPedido = (int) $resCheckout->json('id_pedido');
        $pedido = PedidoMaestro::with('subpedidos.lineas')->findOrFail($idPedido);
        $this->assertCount(2, $pedido->subpedidos);

        $subComercio1 = $pedido->subpedidos->firstWhere('id_comercio', 1);
        $subComercio2 = $pedido->subpedidos->firstWhere('id_comercio', 2);
        $this->assertNotNull($subComercio1);
        $this->assertNotNull($subComercio2);

        $idReservaC1 = (string) $subComercio1->lineas->first()->id_reserva;
        $idReservaC2 = (string) $subComercio2->lineas->first()->id_reserva;
        $this->assertNotEmpty($idReservaC1);
        $this->assertNotEmpty($idReservaC2);
        $this->assertNotSame($idReservaC1, $idReservaC2);

        // 3. Prueba 1 (M4): Las reservas pertenecen a sus comercios reales (1 y 2) y están CONFIRMADAS
        $reservaM4C1 = app(InventarioModuleInterface::class)->obtenerReserva($idReservaC1);
        $reservaM4C2 = app(InventarioModuleInterface::class)->obtenerReserva($idReservaC2);

        $this->assertSame(1, $reservaM4C1['id_comercio']);
        $this->assertSame('confirmada', $reservaM4C1['estado_reserva']);
        $this->assertSame($idPedido, $reservaM4C1['id_pedido']);
        $this->assertSame((int) $subComercio1->id_subpedido, $reservaM4C1['id_subpedido']);

        $this->assertSame(2, $reservaM4C2['id_comercio']);
        $this->assertSame('confirmada', $reservaM4C2['estado_reserva']);
        $this->assertSame($idPedido, $reservaM4C2['id_pedido']);
        $this->assertSame((int) $subComercio2->id_subpedido, $reservaM4C2['id_subpedido']);

        // Stock reservado en M4 refleja 2 unidades en Comercio 1 y 3 unidades en Comercio 2
        $stockC1 = app(InventarioModuleInterface::class)->consultarStockDisponible(1, 101);
        $stockC2 = app(InventarioModuleInterface::class)->consultarStockDisponible(2, 202);
        $this->assertSame(8, $stockC1['cantidad_disponible']); // 10 - 2
        $this->assertSame(5, $stockC2['cantidad_disponible']); // 8 - 3

        // 4. Prueba 4a (M7): Asientos contables de VENTA registrados al confirmar el pedido
        $asientoVentaC1 = DB::table('asientos_contables')
            ->where('id_comercio', 1)
            ->where('id_subpedido', $subComercio1->id_subpedido)
            ->where('tipo', 'VENTA')
            ->first();
        $asientoVentaC2 = DB::table('asientos_contables')
            ->where('id_comercio', 2)
            ->where('id_subpedido', $subComercio2->id_subpedido)
            ->where('tipo', 'VENTA')
            ->first();

        $this->assertNotNull($asientoVentaC1, 'M7 debe registrar el asiento de VENTA para Comercio 1');
        $this->assertNotNull($asientoVentaC2, 'M7 debe registrar el asiento de VENTA para Comercio 2');

        // Verificar partida doble en M7 (Debe == Haber == Subtotal)
        $debeVentaC1 = (float) DB::table('detalle_asientos')->where('id_asiento', $asientoVentaC1->id_asiento)->sum('debe');
        $haberVentaC1 = (float) DB::table('detalle_asientos')->where('id_asiento', $asientoVentaC1->id_asiento)->sum('haber');
        $this->assertEqualsWithDelta(20000.00, $debeVentaC1, 0.001); // 2 * 10000.00
        $this->assertEqualsWithDelta($debeVentaC1, $haberVentaC1, 0.001);

        $debeVentaC2 = (float) DB::table('detalle_asientos')->where('id_asiento', $asientoVentaC2->id_asiento)->sum('debe');
        $haberVentaC2 = (float) DB::table('detalle_asientos')->where('id_asiento', $asientoVentaC2->id_asiento)->sum('haber');
        $this->assertEqualsWithDelta(45000.00, $debeVentaC2, 0.001); // 3 * 15000.00
        $this->assertEqualsWithDelta($debeVentaC2, $haberVentaC2, 0.001);

        // 5. Confirmar pago y pasar subpedidos a preparación y despacho (Prueba 2 y Prueba 4b)
        $this->postJson("{$this->prefix}/pedidos/{$idPedido}/pago-confirmado", [
            'referencia_pago' => 'REF-INT-001',
        ])->assertOk();

        $this->postJson("{$this->prefix}/subpedidos/{$subComercio1->id_subpedido}/preparar")->assertOk();
        $this->postJson("{$this->prefix}/subpedidos/{$subComercio1->id_subpedido}/despachar", [
            'guia_seguimiento' => 'GUIA-C1-001',
        ])->assertOk();

        $this->postJson("{$this->prefix}/subpedidos/{$subComercio2->id_subpedido}/preparar")->assertOk();
        $this->postJson("{$this->prefix}/subpedidos/{$subComercio2->id_subpedido}/despachar", [
            'guia_seguimiento' => 'GUIA-C2-001',
        ])->assertOk();

        // 6. Prueba 2 (M4): Reservas CONSUMIDAS, stock físico descontado y salida asentada en Kardex
        $this->assertSame('consumida', app(InventarioModuleInterface::class)->obtenerReserva($idReservaC1)['estado_reserva']);
        $this->assertSame('consumida', app(InventarioModuleInterface::class)->obtenerReserva($idReservaC2)['estado_reserva']);

        $existenciaC1 = DB::table('existencias')->where('id_comercio', 1)->where('id_variacion', 101)->first();
        $existenciaC2 = DB::table('existencias')->where('id_comercio', 2)->where('id_variacion', 202)->first();
        $this->assertSame(8, (int) $existenciaC1->cantidad_fisica);
        $this->assertSame(0, (int) $existenciaC1->cantidad_reservada);
        $this->assertSame(5, (int) $existenciaC2->cantidad_fisica);
        $this->assertSame(0, (int) $existenciaC2->cantidad_reservada);

        $this->assertSame(2, DB::table('movimientos_inventario')->where('tipo', 'salida')->count());

        // 7. Prueba 4b (M7): Asientos contables de COSTO_VENTA creados con el costo real del Kardex de M4
        $asientoCostoC1 = DB::table('asientos_contables')
            ->where('id_comercio', 1)
            ->where('id_subpedido', $subComercio1->id_subpedido)
            ->where('tipo', 'COSTO_VENTA')
            ->first();
        $asientoCostoC2 = DB::table('asientos_contables')
            ->where('id_comercio', 2)
            ->where('id_subpedido', $subComercio2->id_subpedido)
            ->where('tipo', 'COSTO_VENTA')
            ->first();

        $this->assertNotNull($asientoCostoC1, 'M7 debe registrar el asiento de COSTO_VENTA para Comercio 1');
        $this->assertNotNull($asientoCostoC2, 'M7 debe registrar el asiento de COSTO_VENTA para Comercio 2');

        $debeCostoC1 = (float) DB::table('detalle_asientos')->where('id_asiento', $asientoCostoC1->id_asiento)->sum('debe');
        $haberCostoC1 = (float) DB::table('detalle_asientos')->where('id_asiento', $asientoCostoC1->id_asiento)->sum('haber');
        $this->assertEqualsWithDelta(8000.00, $debeCostoC1, 0.001); // 2 unidades * 4000.00 costo promedio
        $this->assertEqualsWithDelta($debeCostoC1, $haberCostoC1, 0.001);

        $debeCostoC2 = (float) DB::table('detalle_asientos')->where('id_asiento', $asientoCostoC2->id_asiento)->sum('debe');
        $haberCostoC2 = (float) DB::table('detalle_asientos')->where('id_asiento', $asientoCostoC2->id_asiento)->sum('haber');
        $this->assertEqualsWithDelta(19500.00, $debeCostoC2, 0.001); // 3 unidades * 6500.00 costo promedio
        $this->assertEqualsWithDelta($debeCostoC2, $haberCostoC2, 0.001);
    }

    /**
     * Flujo 2 (Prueba 3):
     * - Compra con reserva en M4 y cancelación antes de entrar en preparación.
     * - Verifica que las unidades reservadas se liberan en M4 y vuelven a estar disponibles.
     */
    public function test_flujo_2_cancelacion_antes_de_preparacion_libera_reservas_en_m4(): void
    {
        // 1. Agregar 4 unidades de variación 202 (Comercio 2, stock inicial 8)
        $this->postJson("{$this->prefix}/carrito/items", [
            'id_cliente' => 88,
            'id_variacion' => 202,
            'cantidad' => 4,
        ])->assertStatus(201);

        // 2. Ejecutar Checkout
        $resCheckout = $this->postJson("{$this->prefix}/pedidos", [
            'id_cliente' => 88,
            'id_direccion_cliente' => 1,
            'metodoPago' => 'tarjeta',
        ]);
        $resCheckout->assertStatus(201);

        $idPedido = (int) $resCheckout->json('id_pedido');
        $linea = PedidoLinea::whereHas('subpedido', fn ($q) => $q->where('id_pedido', $idPedido))->firstOrFail();
        $idReserva = (string) $linea->id_reserva;

        // Verificar que las 4 unidades están reservadas (disponible bajó de 8 a 4)
        $stockDuranteReserva = app(InventarioModuleInterface::class)->consultarStockDisponible(2, 202);
        $this->assertSame(4, $stockDuranteReserva['cantidad_disponible']);
        $this->assertSame('confirmada', app(InventarioModuleInterface::class)->obtenerReserva($idReserva)['estado_reserva']);

        // 3. Cancelar el pedido antes de que entre en preparación
        $resCancelar = $this->postJson("{$this->prefix}/pedidos/{$idPedido}/cancelar", [
            'motivo' => 'Cliente decidió cancelar antes de preparación',
        ]);
        $resCancelar->assertOk();

        // 4. Prueba 3 (M4): La reserva pasa a LIBERADA y el stock disponible vuelve a 8
        $reservaLiberada = app(InventarioModuleInterface::class)->obtenerReserva($idReserva);
        $this->assertSame('liberada', $reservaLiberada['estado_reserva']);

        $stockTrasCancelar = app(InventarioModuleInterface::class)->consultarStockDisponible(2, 202);
        $this->assertSame(8, $stockTrasCancelar['cantidad_disponible']);

        $existenciaC2 = DB::table('existencias')->where('id_comercio', 2)->where('id_variacion', 202)->first();
        $this->assertSame(8, (int) $existenciaC2->cantidad_fisica);
        $this->assertSame(0, (int) $existenciaC2->cantidad_reservada);
    }

    /**
     * Flujo 3:
     * - Si M4 rechaza la salida física de inventario al despachar (p. ej. reserva liberada),
     *   M5 revierte la transición del subpedido, responde 409, no emite SubpedidoDespachado
     *   y M7 no genera asiento COSTO_VENTA.
     */
    public function test_flujo_3_fallo_de_salida_en_m4_real_revierte_despacho_y_evita_costo_venta_en_m7(): void
    {
        $this->postJson("{$this->prefix}/carrito/items", [
            'id_cliente' => 89,
            'id_variacion' => 202,
            'cantidad' => 2,
        ])->assertStatus(201);

        $resCheckout = $this->postJson("{$this->prefix}/pedidos", [
            'id_cliente' => 89,
            'id_direccion_cliente' => 1,
            'metodoPago' => 'tarjeta',
        ]);
        $resCheckout->assertStatus(201);

        $idPedido = (int) $resCheckout->json('id_pedido');
        $subpedido = Subpedido::where('id_pedido', $idPedido)->firstOrFail();
        $linea = PedidoLinea::where('id_subpedido', $subpedido->id_subpedido)->firstOrFail();

        $this->postJson("{$this->prefix}/pedidos/{$idPedido}/pago-confirmado", [
            'referencia_pago' => 'REF-INT-FALLO-01',
        ])->assertOk();

        $this->postJson("{$this->prefix}/subpedidos/{$subpedido->id_subpedido}/preparar")->assertOk();

        // Provocar inconsistencia controlada en M4 liberando la reserva antes del despacho
        app(InventarioModuleInterface::class)->liberarReserva((string) $linea->id_reserva, 'solicitud_m5');

        $resDespacho = $this->postJson("{$this->prefix}/subpedidos/{$subpedido->id_subpedido}/despachar", [
            'guia_seguimiento' => 'GUIA-NO-DEBE-GUARDARSE',
        ]);

        $resDespacho->assertStatus(409)
            ->assertJsonPath('error.codigo', 'ERR-M5-002');

        $subActualizado = $subpedido->fresh();
        $this->assertSame('en_preparacion', $subActualizado->estado_subpedido);
        $this->assertNull($subActualizado->guia_seguimiento);

        $this->assertSame(0, DB::table('movimientos_inventario')->where('tipo', 'salida')->count());
        $this->assertSame(
            0,
            DB::table('asientos_contables')
                ->where('id_subpedido', $subpedido->id_subpedido)
                ->where('tipo', 'COSTO_VENTA')
                ->count()
        );
    }
}
