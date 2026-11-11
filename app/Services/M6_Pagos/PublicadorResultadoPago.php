<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Events\M6_Pagos\PagoConfirmado;
use App\Events\M6_Pagos\PagoRechazado;
use App\Models\M6_Pagos\EstadoPago;
use App\Models\M6_Pagos\Pago;
use App\Services\M6_Pagos\Clients\ContabilidadClient;
use App\Services\M6_Pagos\Clients\PedidoClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Efectos posteriores a resolver un pago (se ejecuta SOLO una vez por pago,
 * después del commit): eventos, aviso a M5, aviso a M7, facturación
 * electrónica (CU-004) y bitácora del Núcleo.
 *
 * Un fallo al avisar a otro módulo no deshace el cobro ya registrado: se
 * deja en el log diario para reintentarlo (consistencia eventual, ADR-005).
 */
class PublicadorResultadoPago
{
    public function __construct(
        private readonly PedidoClient $pedidos,
        private readonly ContabilidadClient $contabilidad,
        private readonly NucleoServiceInterface $nucleo,
        private readonly FacturacionService $facturacion,
    ) {}

    public function publicar(Pago $pago, ?string $motivo = null): void
    {
        $estado = $pago->codigoEstado();
        $fecha = ($pago->fecha_confirmacion ?? now())->toIso8601String();

        if ($estado === EstadoPago::CONFIRMADO) {
            event(new PagoConfirmado((string) Str::uuid(), $pago->id_pedido, $pago->id_pago, (float) $pago->monto, $fecha));
            $this->intentar('M7', fn () => $this->contabilidad->enviarEventoFinanciero($pago->id_pago, 'PagoConfirmado'));
        } else {
            event(new PagoRechazado((string) Str::uuid(), $pago->id_pedido, $pago->id_pago, $motivo, $fecha));
        }

        $this->intentar('M5', fn () => $this->pedidos->notificarResultadoPago($pago->id_pedido, $pago->id_pago, $estado, $motivo));

        // CU-004: una factura por subpedido. Nunca lanza ni deshace el cobro.
        if ($estado === EstadoPago::CONFIRMADO) {
            $this->facturacion->facturarPagoConfirmado($pago);
        }

        $this->intentar('Nucleo', fn () => $this->nucleo->registrarAuditoria(
            $estado === EstadoPago::CONFIRMADO ? 'confirmar_pago' : 'rechazar_pago',
            'pagos',
            $pago->id_pago,
            ['modulo' => 'M6_Pagos', 'resultado' => $estado === EstadoPago::CONFIRMADO ? 'exitoso' : 'fallido', 'motivo' => $motivo],
        ));
    }

    private function intentar(string $destino, callable $accion): void
    {
        try {
            $accion();
        } catch (Throwable $e) {
            Log::warning("[M6_Pagos] No se pudo notificar a {$destino}: {$e->getMessage()}");
        }
    }
}
