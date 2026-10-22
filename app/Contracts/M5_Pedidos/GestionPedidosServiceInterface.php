<?php

namespace App\Contracts\M5_Pedidos;

/**
 * Contrato de operaciones sobre Pedidos Maestros (M5).
 * Basado en la especificación OpenAPI de M5 (v1.1.0) - Tag "Pedidos".
 * Propietario de entidades: pedidos_maestros, notas_cliente_pedido, recibos_cliente, cancelaciones_pedido.
 */
interface GestionPedidosServiceInterface
{
    /**
     * Listar pedidos pertenecientes al cliente con filtros opcionales.
     * Equivale a: GET /api/m5/v1/pedidos
     * Consumidores: Frontend del portal.
     *
     * @param  int|null  $idCliente  Identificador único del cliente.
     * @param  array{estado?: string, page?: int, per_page?: int}  $filtros  Filtros de consulta y paginación.
     * @return array{pagina: int, por_pagina: int, total_resultados: int, pedidos: list<array<string, mixed>>} Estructura ResultadoPedidos.
     */
    public function listarPedidos(?int $idCliente = null, array $filtros = []): array;

    /**
     * Crear y confirmar compra desde el carrito multi-comercio (Paso de Checkout).
     * Equivale a: POST /api/m5/v1/pedidos
     * Consumidores: Frontend del portal.
     *
     * @param  array{id_cliente?: int, id_direccion_cliente?: ?int, medio_pago?: string, cupon_descuento?: ?string, tarjeta_regalo?: ?string, nota?: ?string}  $datosCheckout
     * @return array<string, mixed> Estructura del esquema PedidoMaestro confirmado.
     */
    public function crearPedidoCheckout(array $datosCheckout): array;

    /**
     * Obtener el detalle completo del pedido maestro (con subpedidos, líneas, notas, recibos y cancelaciones).
     * Equivale a: GET /api/m5/v1/pedidos/{id_pedido}
     * Alias en M6: ObtenerDatosPedido, ConsultarEstadoPedido.
     * Consumidores: Frontend del portal, M4 Inventario, M6 Pagos, M7 ERP Contable.
     *
     * @param  int  $idPedido  Identificador único del pedido maestro.
     * @return array{id_pedido: int, id_cliente: int, id_direccion_cliente?: ?int, fecha_creacion: string, monto_total: float, estado_pedido: string, subpedidos: list<array<string, mixed>>, notas?: list<array<string, mixed>>, recibos?: list<array<string, mixed>>, cancelacion?: ?array<string, mixed>} Estructura del esquema PedidoMaestro.
     */
    public function obtenerPedido(int $idPedido): array;

    /**
     * Cancelar un pedido maestro completo antes de su despacho (INC-13).
     * Equivale a: POST /api/m5/v1/pedidos/{id_pedido}/cancelar
     * Consumidores: Frontend del portal, M6 Pagos y Facturación.
     *
     * @param  int  $idPedido  Identificador único del pedido maestro.
     * @param  array{motivo: string, requiere_reembolso?: bool}  $datosCancelacion  Motivo y requerimiento de reembolso a M6.
     * @return array<string, mixed> Estructura del pedido maestro actualizado con estado "cancelado".
     */
    public function cancelarPedidoTotal(int $idPedido, array $datosCancelacion = []): array;
}
