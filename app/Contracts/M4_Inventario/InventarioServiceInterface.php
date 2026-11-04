<?php

namespace App\Contracts\M4_Inventario;

interface InventarioServiceInterface
{
    /**
     * Consultar existencias disponibles para una variación.
     */
    public function consultarExistencias(int $variacionId): int;

    /**
     * Solicitar reserva atómica de existencias con vencimiento.
     * Invocado en el paso 4 del recorrido transversal (Regla RN-04).
     */
    public function solicitarReserva(int $variacionId, int $cantidad, int $pedidoId): array;

    /**
     * Convertir reserva en salida de inventario al despachar el pedido.
     * Invocado en el paso 7 del recorrido transversal.
     */
    public function confirmarSalidaPorDespacho(int $reservaId): bool;
}
