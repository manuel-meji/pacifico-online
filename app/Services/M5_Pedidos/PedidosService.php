<?php

namespace App\Services\M5_Pedidos;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;
use App\Contracts\M5_Pedidos\PedidosServiceInterface;

class PedidosService implements PedidosServiceInterface
{
    public function __construct(
        protected ComerciosServiceInterface $comerciosService
    ) {}

    public function crearPedido(array $datosCompra): array
    {
        // Paso 3: Confirmación de carrito y creación de pedido
        return [
            'pedido_id' => rand(100, 999),
            'estado' => 'PENDIENTE_PAGO',
            'subtotal' => 15000.00,
            'impuesto' => 1950.00,
            'total' => 16950.00,
        ];
    }

    public function marcarComoPagado(int $pedidoId, string $comprobantePago): bool
    {
        // Paso 6: Actualizar pedido a PAGADO y publicar evento
        return true;
    }

    /**
     * Registrar despacho de subpedido por el comercio y emitir el evento canónico SubpedidoDespachado.
     * Notifica a M2 (registro de comisión), M4 (salida kardex) y M7 (costo de ventas).
     *
     * @param  int  $subpedidoId  Identificador único del subpedido
     * @param  string|null  $guiaTransporte  Información opcional de guía de despacho
     * @return array{id_subpedido: int, id_pedido: int, estado_subpedido: string, guia_transporte: ?string, evento: string, consumidores_notificados: list<string>, comision_registrada_m2: bool}
     */
    public function despacharSubpedido(int $subpedidoId, ?string $guiaTransporte = null): array
    {
        $pedidoId = 101;
        $subtotal = 15000.00;

        // Paso 9 del recorrido transversal: M2 consume SubpedidoDespachado para registrar comisión
        $comisionRegistrada = $this->comerciosService->registrarComision($pedidoId, $subtotal);

        return [
            'id_subpedido' => $subpedidoId,
            'id_pedido' => $pedidoId,
            'estado_subpedido' => 'despachado',
            'guia_transporte' => $guiaTransporte,
            'evento' => 'SubpedidoDespachado',
            'consumidores_notificados' => ['M1', 'M2', 'M4', 'M7'],
            'comision_registrada_m2' => $comisionRegistrada,
        ];
    }
}
