<?php

namespace App\Services\M6_Pagos;

use App\Contracts\M6_Pagos\PagosServiceInterface;

class PagosService implements PagosServiceInterface
{
    public function procesarPago(int $pedidoId, float $monto, string $metodoPago, array $detalles): array
    {
        // Paso 5: Confirmación de pago simulada e idempotente
        return [
            'transaccion_id' => 'TXN-' . uniqid(),
            'pedido_id' => $pedidoId,
            'monto' => $monto,
            'metodo' => $metodoPago,
            'estado' => 'APROBADO',
            'fecha' => now()->toIso8601String(),
        ];
    }

    public function generarFactura(int $pedidoId): array
    {
        return [
            'numero_factura' => 'FAC-' . $pedidoId,
            'clave_numerica' => '5060000000000000000',
            'estado' => 'GENERADA',
        ];
    }
}
