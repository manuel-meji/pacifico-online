<?php

namespace App\Contracts\M5_Pedidos;

/**
 * Contrato principal del módulo M5 Carrito y pedidos para interoperabilidad entre módulos.
 * Satisface los métodos transversales (pasos 3, 6, 7, 8 y 9) y los métodos consumidos por M1.
 */
interface PedidosServiceInterface
{
    /**
     * Crear y confirmar compra desde el carrito multi-comercio.
     * Invocado en el paso 3 del recorrido transversal.
     *
     * @param  array<string, mixed>  $datosCompra
     * @return array<string, mixed>
     */
    public function crearPedido(array $datosCompra): array;

    /**
     * Actualizar estado del pedido a Pagado y disparar eventos subsiguientes.
     * Invocado en el paso 6 del recorrido transversal al confirmar cobro en M6.
     *
     * @param  int  $pedidoId  Identificador único del pedido maestro.
     * @param  string  $comprobantePago  Identificador o token de confirmación de pago.
     * @return bool Verdadero si la transición fue exitosa.
     */
    public function marcarComoPagado(int $pedidoId, string $comprobantePago): bool;

    /**
     * Registrar despacho de subpedido por el comercio y emitir el evento canónico SubpedidoDespachado.
     * Notifica a M1 (seguimiento envíos), M2 (comisiones), M4 (salida kardex) y M7 (costo de ventas).
     * Invocado en los pasos 7, 8 y 9 del recorrido transversal.
     *
     * @param  int  $subpedidoId  Identificador único del subpedido
     * @param  string|null  $guiaTransporte  Información opcional de guía de despacho
     * @return array{id_subpedido: int, id_pedido: int, estado_subpedido: string, guia_transporte: ?string, evento: string, consumidores_notificados: list<string>, comision_registrada_m2: bool}
     */
    public function despacharSubpedido(int $subpedidoId, ?string $guiaTransporte = null): array;

    /**
     * Obtener el carrito de compras activo de un cliente y sus ítems.
     * Equivale a: GET /api/m5/v1/carrito
     * Consumidores: Frontend del portal, M1 Portal público y búsqueda.
     *
     * @param  int  $idCliente  Identificador único del cliente (M1).
     * @return array<string, mixed> Estructura del esquema Carrito.
     */
    public function obtenerCarrito(int $idCliente): array;

    /**
     * Agregar una variación de producto al carrito de compras activo.
     * Equivale a: POST /api/m5/v1/carrito/items
     * Consumidores: Frontend del portal, M1 Portal público y búsqueda.
     *
     * @param  int  $idCliente  Identificador único del cliente (M1).
     * @param  array{id_variacion: int, cantidad?: int, id_reserva?: ?string}  $datos
     * @return array<string, mixed> Estructura del esquema Carrito actualizado.
     */
    public function agregarItemCarrito(int $idCliente, array $datos): array;
}
