<?php

namespace Database\Seeders;

use App\Models\M5_Pedidos\Carrito;
use App\Models\M5_Pedidos\ItemCarrito;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class M5PedidosSeeder extends Seeder
{
    /**
     * Poblar datos de demostración de M5_Pedidos para las capturas del PC4:
     * - Carrito multi-comercio activo para cliente 1.
     * - Pedido maestro multi-comercio (comercios 1 y 2) con subpedidos, líneas con id_reserva e historial en estados_pedido.
     * - Pedido cancelado con su registro de auditoría en cancelaciones_pedido.
     *
     * Ejecutar con: php artisan db:seed --class=M5PedidosSeeder
     */
    public function run(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pedidos_subpedidos DROP CONSTRAINT IF EXISTS chk_pedidos_subpedidos_estados');
            DB::statement("ALTER TABLE pedidos_subpedidos ADD CONSTRAINT chk_pedidos_subpedidos_estados CHECK (estado_subpedido IN ('pendiente', 'confirmado', 'pagado', 'en_preparacion', 'despachado', 'entregado', 'cancelado'))");
        }

        DB::transaction(function (): void {
            $idClienteDemo = 1;

            // 1. Carrito multi-comercio activo para demostración en interfaz
            $carrito = (new Carrito)->obtenerOCrearCarrito($idClienteDemo);
            ItemCarrito::updateOrCreate(
                [
                    'id_carrito' => $carrito->id_carrito,
                    'id_variacion' => 1,
                ],
                [
                    'cantidad' => 2,
                    'id_reserva' => '3f2b8a52-6c1e-4d0b-89a7-bbc5d8d53f01',
                ]
            );
            ItemCarrito::updateOrCreate(
                [
                    'id_carrito' => $carrito->id_carrito,
                    'id_variacion' => 2,
                ],
                [
                    'cantidad' => 1,
                    'id_reserva' => '3f2b8a52-6c1e-4d0b-89a7-bbc5d8d53f02',
                ]
            );

            // 2. Pedido Maestro Multi-Comercio (Comercio 1 y Comercio 2) con historial completo de estados
            $pedidoMulti = PedidoMaestro::withoutGlobalScopes()
                ->where('id_cliente', $idClienteDemo)
                ->where('cupon_descuento', 'DEMO-MULTI-PC4')
                ->first();

            if (! $pedidoMulti) {
                $pedidoMulti = new PedidoMaestro;
                // Comercio 1: subtotal 20000, IVA 2600, envío 1500 = 24100
                // Comercio 2: subtotal 15000, IVA 1950, envío 1000 = 17950
                // Maestro: subtotal 35000, IVA 4550, envío 2500, total = 42050
                $idPedidoMulti = $pedidoMulti->crearPedidoMaestro(
                    42050.00,
                    $idClienteDemo,
                    'DEMO-MULTI-PC4',
                    null,
                    1,
                    35000.00,
                    4550.00,
                    2500.00,
                    0.00,
                    'pendiente'
                );

                $subpedidos = (new Subpedido)->crearSubpedidos([
                    [
                        'id_comercio' => 1,
                        'subtotal' => 20000.00,
                        'costo_envio' => 1500.00,
                        'impuesto' => 2600.00,
                    ],
                    [
                        'id_comercio' => 2,
                        'subtotal' => 15000.00,
                        'costo_envio' => 1000.00,
                        'impuesto' => 1950.00,
                    ],
                ], $idPedidoMulti);

                $subComercio1 = $subpedidos[0];
                $subComercio2 = $subpedidos[1];

                (new PedidoLinea)->crearLinea(
                    $subComercio1->id_subpedido,
                    1,
                    2,
                    10000.00,
                    13.00,
                    '11111111-2222-3333-4444-555555555501'
                );

                (new PedidoLinea)->crearLinea(
                    $subComercio2->id_subpedido,
                    2,
                    1,
                    15000.00,
                    13.00,
                    '11111111-2222-3333-4444-555555555502'
                );

                // Transiciones de estado demostrables en estados_pedido
                $pedidoMulti->actualizarEstado('pagado', 'Pago confirmado por pasarela M6 (REF-DEMO-001)');
                $subComercio1->cambiarEstado('pagado', 'Pago confirmado por pasarela M6');
                $subComercio1->cambiarEstado('en_preparacion', 'Comercio 1 inició preparación del pedido');
                $subComercio1->despachar('GUIA-CR-1001');

                $subComercio2->cambiarEstado('pagado', 'Pago confirmado por pasarela M6');
                $subComercio2->cambiarEstado('en_preparacion', 'Comercio 2 inició preparación del pedido');
            }

            // 3. Pedido con Cancelación registrada en cancelaciones_pedido
            $pedidoCancelado = PedidoMaestro::withoutGlobalScopes()
                ->where('id_cliente', $idClienteDemo)
                ->where('cupon_descuento', 'DEMO-CANCEL-PC4')
                ->first();

            if (! $pedidoCancelado) {
                $pedidoCancelado = new PedidoMaestro;
                $idPedidoCancelado = $pedidoCancelado->crearPedidoMaestro(
                    16950.00,
                    $idClienteDemo,
                    'DEMO-CANCEL-PC4',
                    null,
                    1,
                    15000.00,
                    1950.00,
                    0.00,
                    0.00,
                    'pendiente'
                );

                $subCancelado = (new Subpedido)->crearSubpedidos([
                    [
                        'id_comercio' => 2,
                        'subtotal' => 15000.00,
                        'costo_envio' => 0.00,
                        'impuesto' => 1950.00,
                    ],
                ], $idPedidoCancelado)[0];

                (new PedidoLinea)->crearLinea(
                    $subCancelado->id_subpedido,
                    3,
                    1,
                    15000.00,
                    13.00,
                    '99999999-8888-7777-6666-555555555503'
                );

                // Pasa a pagado y luego se cancela con motivo y reembolso
                $pedidoCancelado->actualizarEstado('pagado', 'Pago confirmado antes de cancelación');
                $subCancelado->cambiarEstado('pagado', 'Pago confirmado antes de cancelación');

                $pedidoCancelado->cancelar('Cliente solicitó cancelación antes de entrar en preparación');
            }
        });
    }
}
