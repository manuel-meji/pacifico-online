<?php

declare(strict_types=1);

namespace App\Contracts\M6_Pagos;

/**
 * Puerto de M6 hacia el Ministerio de Hacienda (recepción de comprobantes).
 * Hoy lo implementa HaciendaSimulador; no hay integración real (decisión abierta 3).
 */
interface HaciendaInterface
{
    public const ACEPTADA = 'aceptada';

    public const RECHAZADA = 'rechazada';

    /** Hacienda recibió el comprobante pero todavía no responde. */
    public const ENVIADA = 'enviada';

    /**
     * Transmite el XML de un comprobante.
     *
     * @return string self::ACEPTADA | self::RECHAZADA | self::ENVIADA
     *
     * @throws \Throwable si Hacienda no está disponible (el comprobante queda pendiente de envío).
     */
    public function enviarComprobante(string $clave, string $xml): string;
}
