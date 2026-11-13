<?php

namespace App\Services\M5_Pedidos\Dobles;

use App\Contracts\M6_Pagos\PagosServiceInterface;

/**
 * Doble de prueba formal (Stub) para M6 Pasarela de Pagos.
 * Implementa App\Contracts\M6_Pagos\PagosServiceInterface.
 */
class PagosStub implements PagosServiceInterface
{
    private ?bool $forzarRechazado = null;

    public function setPagoRechazado(bool $rechazado = true): self
    {
        $this->forzarRechazado = $rechazado;

        return $this;
    }

    /**
     * Procesar pago de un pedido mediante pasarela simulada (Tarjeta / SINPE / PayPal).
     * Garantiza idempotencia de confirmación (Paso 5 del recorrido transversal).
     *
     * @param  array<string, mixed>  $detalles
     * @return array<string, mixed>
     *
     * @throws ErrorProveedorException ERR-M6-001 si el pago es rechazado (402)
     */
    public function procesarPago(int $pedidoId, float $monto, string $metodoPago, array $detalles = []): array
    {
        $rechazado = $this->forzarRechazado ?? ($metodoPago === 'rechazado' || $monto <= 0);

        if ($rechazado) {
            throw ErrorProveedorException::pagoRechazado('Pago rechazado por la pasarela');
        }

        return [
            'transaccion_id' => 'TXN-12345',
            'id_pedido' => $pedidoId,
            'monto' => $monto,
            'metodo_pago' => $metodoPago,
            'estado' => 'aprobado',
            'urlPasarelaPago' => "https://pago.pacificos.com/checkout/{$pedidoId}",
            'fecha_pago' => now()->toIso8601String(),
        ];
    }

    /**
     * Generar documento electrónico de factura.
     *
     * @return array<string, mixed>
     */
    public function generarFactura(int $pedidoId): array
    {
        return [
            'id_factura' => 9000 + $pedidoId,
            'id_pedido' => $pedidoId,
            'clave_hacienda' => '50601012300010123456789012345678901234567890',
            'consecutivo' => 'FAC-'.str_pad((string) $pedidoId, 8, '0', STR_PAD_LEFT),
            'fecha_emision' => now()->toIso8601String(),
            'xml_firmado_url' => "https://pacifico.test/facturas/{$pedidoId}.xml",
        ];
    }

    /**
     * Solicitar reembolso total o parcial a la pasarela de pagos (RN-M5-10 / B5).
     *
     * @return array<string, mixed>
     */
    public function solicitarReembolso(int $pedidoId, float $monto, string $motivo = 'Cancelación de pedido', ?int $idSubpedido = null): array
    {
        return [
            'id_reembolso' => 'REEMB-'.$pedidoId.($idSubpedido !== null ? "-SUB-{$idSubpedido}" : ''),
            'id_pedido' => $pedidoId,
            'id_subpedido' => $idSubpedido,
            'monto_reembolsado' => $monto,
            'motivo' => $motivo,
            'estado' => 'reembolsado',
            'fecha_reembolso' => now()->toIso8601String(),
        ];
    }
}
