<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Models\M6_Pagos\EstadoFacturaHacienda;
use App\Models\M6_Pagos\EstadoPago;
use App\Models\M6_Pagos\FacturaElectronica;

/**
 * Fachada pública de M6 (contrato PHP que consume M5).
 *
 * La firma de PagosServiceInterface NO se cambia: M5 y su doble PagosStub ya la
 * implementan/consumen (regla de versionado de contratos, ADR-004). Internamente
 * delega en ProcesamientoPagosService y FacturacionService.
 */
class PagosService implements PagosServiceInterface
{
    public function __construct(
        private readonly ProcesamientoPagosService $procesamiento,
        private readonly FacturacionService $facturacion,
    ) {}

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
     * Facturación electrónica (CU-004, RN-003). Una factura por subpedido.
     *
     * Es idempotente: si las facturas ya existen se devuelven las mismas. Sirve
     * también de reintento cuando la factura automática quedó pendiente.
     * Conserva las claves de la respuesta provisional (numero_factura,
     * clave_numerica, estado) con los datos de la primera factura, y agrega
     * el detalle de todas en "facturas".
     *
     * @throws ErrorContratoException PAGO_NO_CONFIRMADO (409) | DATOS_FISCALES_INCOMPLETOS (422)
     */
    public function generarFactura(int $pedidoId): array
    {
        $facturas = $this->facturacion->facturarPedido($pedidoId)
            ->map(fn (FacturaElectronica $factura): array => [
                'id_factura' => $factura->id_factura_electronica,
                'id_subpedido' => $factura->id_subpedido,
                'id_comercio' => $factura->id_comercio,
                'numero_factura' => $factura->consecutivo,
                'clave_numerica' => $factura->claveNumerica(),
                'estado' => EstadoFacturaHacienda::valorContrato($factura->codigoEstado()),
                'monto_total' => (float) $factura->monto_total,
                'fecha_emision' => $factura->fecha_emision?->toIso8601String(),
            ])
            ->values()
            ->all();

        $primera = $facturas[0] ?? [];

        return [
            'numero_factura' => $primera['numero_factura'] ?? null,
            'clave_numerica' => $primera['clave_numerica'] ?? null,
            'estado' => $primera['estado'] ?? null,
            'id_pedido' => $pedidoId,
            'facturas' => $facturas,
        ];
    }
}
