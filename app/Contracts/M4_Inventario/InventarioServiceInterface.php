<?php

namespace App\Contracts\M4_Inventario;

/**
 * Interfaz anterior de M4, del PC2.
 *
 * La siguen usando el doble de inventario de M1 (InventarioStub) y el carrito
 * de M5 (ControladorCarrito). Se conserva para no romperlos mientras migran a
 * InventarioModuleInterface, que la extiende. No se agregan métodos nuevos aquí.
 *
 * @deprecated Usar InventarioModuleInterface (contrato openapi v1.3.0, x-interfaz-php).
 */
interface InventarioServiceInterface
{
    /**
     * Unidades disponibles de una variación, sumando todas sus ubicaciones.
     */
    public function consultarExistencias(int $variacionId): int;

    /**
     * Reserva atómica de existencias con vencimiento (RN-04), vinculada al pedido.
     */
    public function solicitarReserva(int $variacionId, int $cantidad, int $pedidoId): array;

    /**
     * Salida de inventario al despachar el pedido. Reemplazada por
     * confirmarSalidaPorReserva, que recibe el id_reserva (UUID).
     */
    public function confirmarSalidaPorDespacho(int $reservaId): bool;
}
