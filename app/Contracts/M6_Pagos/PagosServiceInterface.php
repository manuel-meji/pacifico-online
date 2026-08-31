<?php

namespace App\Contracts\M6_Pagos;

interface PagosServiceInterface
{
    /**
     * Procesar pago de un pedido mediante pasarela simulada (Tarjeta / SINPE / PayPal).
     * Garantiza idempotencia de confirmación (Paso 5 del recorrido transversal).
     */
    public function procesarPago(int $pedidoId, float $monto, string $metodoPago, array $detalles): array;

    /**
     * Generar documento electrónico de factura.
     */
    public function generarFactura(int $pedidoId): array;
}
