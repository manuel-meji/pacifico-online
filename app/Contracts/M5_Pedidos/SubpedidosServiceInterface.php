<?php

namespace App\Contracts\M5_Pedidos;

/**
 * Contrato de operaciones sobre Subpedidos por comercio (M5).
 * Basado en la especificación OpenAPI de M5 (v1.1.0) - Tag "Subpedidos" y Solicitud de Cambio SC-02.
 * Propietario de entidades: pedidos_subpedidos, pedidos_lineas.
 */
interface SubpedidosServiceInterface
{
    /**
     * Consultar detalle completo de un subpedido específico con sus líneas.
     * Equivale a: GET /api/m5/v1/subpedidos/{id_subpedido}
     * Consumidores: Frontend del portal, M2 Comercios, M4 Inventario, M6 Pagos, M7 ERP Contable.
     *
     * @param  int  $idSubpedido  Identificador único del subpedido en pedidos_subpedidos.
     * @return array{id_subpedido: int, id_pedido: int, id_comercio: int, subtotal: float, costo_envio: float, impuesto: float, estado_subpedido: string, created_at: ?string, updated_at: ?string, lineas: list<array<string, mixed>>} Estructura del esquema PedidoSubpedido.
     */
    public function obtenerSubpedido(int $idSubpedido): array;

    /**
     * Registrar el despacho de un subpedido por parte del comercio y emitir el evento canónico SubpedidoDespachado.
     * Notifica a M1 (seguimiento envíos), M2 (comisiones), M4 (salida kardex) y M7 (costo de ventas).
     * Equivale a: POST /api/m5/v1/subpedidos/{id_subpedido}/despachar
     * Consumidores: Frontend del portal, M2 Comercios.
     *
     * @param  int  $subpedidoId  Identificador único del subpedido.
     * @param  string|null  $guiaTransporte  Información o número de guía de despacho.
     * @return array{id_subpedido: int, id_pedido: int, estado_subpedido: string, guia_transporte: ?string, evento: string, consumidores_notificados: list<string>, comision_registrada_m2: bool} Resultado del despacho.
     */
    public function despacharSubpedido(int $subpedidoId, ?string $guiaTransporte = null): array;

    /**
     * Confirmar la entrega final del subpedido al cliente y emitir el evento canónico SubpedidoEntregado.
     * Notifica a M1 (seguimiento del cliente) y M7 (asiento de entrega / cierre de ciclo).
     * Equivale a: POST /api/m5/v1/subpedidos/{id_subpedido}/entregar
     * Consumidores: Frontend del portal, M2 Comercios.
     *
     * @param  int  $idSubpedido  Identificador único del subpedido.
     * @param  array<string, mixed>  $datosEntrega  Metadatos adicionales de la entrega (fecha_entrega, receptor, etc.).
     * @return array<string, mixed> Subpedido actualizado con estado "entregado".
     */
    public function confirmarEntregaSubpedido(int $idSubpedido, array $datosEntrega = []): array;

    /**
     * Cancelar parcialmente un subpedido individual (Solicitud de Cambio SC-02 / INC-13).
     * Registra en cancelaciones_pedido y emite el evento canónico SubpedidoCancelado hacia M4 (liberación) y M6 (reembolso parcial).
     * Equivale a: POST /api/m5/v1/subpedidos/{id_subpedido}/cancelar
     * Consumidores: Frontend del portal, M2 Comercios, M6 Pagos y Facturación.
     *
     * @param  int  $idSubpedido  Identificador único del subpedido.
     * @param  array{motivo: string, requiere_reembolso?: bool}  $datosCancelacion  Motivo y requerimiento de reembolso.
     * @return array<string, mixed> Subpedido actualizado con estado "cancelado".
     */
    public function cancelarSubpedidoParcial(int $idSubpedido, array $datosCancelacion = []): array;
}
