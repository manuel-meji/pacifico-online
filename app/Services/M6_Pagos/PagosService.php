<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Models\M6_Pagos\EstadoPago;

/**
 * Fachada pública de M6 (contrato PHP que consume M5).
 *
 * La firma de PagosServiceInterface NO se cambia: M5 y su doble PagosStub ya la
 * implementan/consumen (regla de versionado de contratos, ADR-004). Internamente
 * delega en ProcesamientoPagosService.
 */
class PagosService implements PagosServiceInterface
{
    public function __construct(private readonly ProcesamientoPagosService $procesamiento) {}

    /**
     * @param  array<string, mixed>  $detalles  idempotency_key (recomendada) y token_medio_pago.
     *
     * @throws ErrorContratoException PAGO_RECHAZADO (ERR-M6-001, 402) igual que PagosStub de M5.
     */
    public function procesarPago(int $pedidoId, float $monto, string $metodoPago, array $detalles): array
    {
        $clave = (string) ($detalles['idempotency_key'] ?? $detalles['clave_idempotencia'] ?? "M5-PEDIDO-{$pedidoId}");

        $pago = $this->procesamiento->procesar($pedidoId, strtolower($metodoPago), $monto, $clave, $detalles)['pago'];
        $estado = $pago->codigoEstado();

        if ($estado === EstadoPago::RECHAZADO) {
            throw ErrorContratoException::de(CodigoErrorM6::PagoRechazado, null, ['estado_pago' => 'rechazado', 'id_pago' => $pago->id_pago]);
        }

        return [
            'transaccion_id' => $pago->referencia_proveedor ?? 'M6-PAGO-'.$pago->id_pago,
            'id_pago' => $pago->id_pago,
            'id_pedido' => $pago->id_pedido,
            'monto' => (float) $pago->monto,
            'metodo_pago' => $pago->medio->nombre_medio,
            'estado' => $estado,
            'urlPasarelaPago' => url("/api/m6/v1/pagos/{$pago->id_pago}"),
            'fecha_pago' => ($pago->fecha_confirmacion ?? $pago->fecha_creacion)?->toIso8601String(),
        ];
    }

    /**
     * Facturación electrónica (CU-004): fuera del alcance de esta fase.
     * Se conserva la respuesta provisional para no romper a los consumidores.
     */
    public function generarFactura(int $pedidoId): array
    {
        return [
            'numero_factura' => 'FAC-'.$pedidoId,
            'clave_numerica' => '5060000000000000000',
            'estado' => 'GENERADA',
        ];
    }
}
