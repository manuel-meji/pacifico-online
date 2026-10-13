<?php

namespace App\Contracts\M5_Pedidos;

interface PedidosServiceInterface
{
    /**
     * Crear y confirmar compra desde el carrito multi-comercio.
     * Invocado en el paso 3 del recorrido transversal.
     */
    public function crearPedido(array $datosCompra): array;

    /**
     * Actualizar estado del pedido a Pagado y disparar eventos subsiguientes.
     * Invocado en el paso 6 del recorrido transversal.
     */
    public function marcarComoPagado(int $pedidoId, string $comprobantePago): bool;

    /**
     * Registrar despacho de subpedido por el comercio y emitir el evento canónico SubpedidoDespachado.
     * Notifica a M2 (registro de comisión), M4 (salida kardex) y M7 (costo de ventas).
     * Invocado en los pasos 7, 8 y 9 del recorrido transversal.
     *
     * @param  int  $subpedidoId  Identificador único del subpedido
     * @param  string|null  $guiaTransporte  Información opcional de guía de despacho
     * @return array{id_subpedido: int, id_pedido: int, estado_subpedido: string, guia_transporte: ?string, evento: string, consumidores_notificados: list<string>, comision_registrada_m2: bool}
     */
    public function despacharSubpedido(int $subpedidoId, ?string $guiaTransporte = null): array;
}
