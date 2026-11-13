<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Events\M6_Pagos\FacturaGenerada;
use App\Events\M6_Pagos\PagoConfirmado;
use App\Events\M6_Pagos\ReembolsoProcesado;
use App\Models\M6_Pagos\FacturaElectronica;
use App\Models\M6_Pagos\Pago;
use App\Models\M6_Pagos\Reembolso;
use App\Services\M6_Pagos\Facturacion\GeneradorXmlComprobante;

/**
 * Arma los eventos financieros de M6 desde sus registros (conexión M6–M7).
 *
 * La usan quienes publican el evento y ConsultaEventosPagosService, así el
 * evento publicado y el que se recupera después tienen el mismo payload y el
 * mismo id_evento.
 */
final class FabricaEventosM6
{
    public static function pagoConfirmado(Pago $pago): PagoConfirmado
    {
        return new PagoConfirmado(
            IdentificadorEventoM6::para('PagoConfirmado', $pago->id_pago),
            $pago->id_pedido,
            $pago->id_pago,
            (float) $pago->monto,
            ($pago->fecha_confirmacion ?? now())->toIso8601String(),
        );
    }

    /** Requiere un reembolso procesado (con nota de crédito). */
    public static function reembolsoProcesado(Reembolso $reembolso): ReembolsoProcesado
    {
        $reembolso->loadMissing(['pago', 'notaCredito.factura']);

        return new ReembolsoProcesado(
            IdentificadorEventoM6::para('ReembolsoProcesado', $reembolso->id_reembolso),
            $reembolso->pago->id_pedido,
            $reembolso->id_subpedido,
            $reembolso->notaCredito->factura->id_comercio,
            $reembolso->id_reembolso,
            (float) $reembolso->monto,
            $reembolso->id_nota_credito,
            ($reembolso->fecha_procesado ?? now())->toIso8601String(),
            $reembolso->id_pago,
        );
    }

    /**
     * Sin desglose (al recuperar), se lee del XML de la factura, que M6 generó
     * con esos mismos montos.
     *
     * @param  array{subtotal: float, impuesto: float, envio: float}|null  $desglose
     */
    public static function facturaGenerada(FacturaElectronica $factura, ?array $desglose = null): FacturaGenerada
    {
        $factura->loadMissing('pago');
        $desglose ??= (new GeneradorXmlComprobante)->totalesFactura((string) $factura->xml_documento);

        return new FacturaGenerada(
            IdentificadorEventoM6::para('FacturaGenerada', $factura->id_factura_electronica),
            $factura->id_factura_electronica,
            $factura->pago->id_pedido,
            $factura->id_subpedido,
            $factura->id_comercio,
            (float) $factura->monto_total,
            (string) $factura->xml_documento,
            ($factura->fecha_emision ?? now())->toIso8601String(),
            $factura->id_pago,
            $desglose['subtotal'] ?? null,
            $desglose['impuesto'] ?? null,
            $desglose['envio'] ?? null,
        );
    }
}
