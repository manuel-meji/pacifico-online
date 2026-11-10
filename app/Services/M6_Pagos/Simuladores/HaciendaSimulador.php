<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos\Simuladores;

use App\Contracts\M6_Pagos\HaciendaInterface;
use RuntimeException;

/**
 * Simulador de la recepción de comprobantes de Hacienda.
 *
 * Revisa lo mínimo del XML (que sea XML válido, con clave de 50 dígitos,
 * consecutivo de 20 y emisor identificado) y responde de inmediato.
 * Con M6_HACIENDA_RESPUESTA se fuerza otro resultado para pruebas y demostraciones:
 *   - aceptada (por defecto) → acepta los comprobantes válidos.
 *   - rechazada              → rechaza todo.
 *   - enviada                → recibe, pero no responde todavía.
 *   - no_disponible          → Hacienda no responde (el comprobante queda pendiente de envío).
 */
class HaciendaSimulador implements HaciendaInterface
{
    public function enviarComprobante(string $clave, string $xml): string
    {
        $forzada = (string) config('m6.hacienda.respuesta', self::ACEPTADA);

        return match ($forzada) {
            'no_disponible' => throw new RuntimeException('Hacienda no está disponible (simulado).'),
            self::RECHAZADA => self::RECHAZADA,
            self::ENVIADA => self::ENVIADA,
            default => $this->esValido($clave, $xml) ? self::ACEPTADA : self::RECHAZADA,
        };
    }

    private function esValido(string $clave, string $xml): bool
    {
        $anterior = libxml_use_internal_errors(true);
        $documento = simplexml_load_string($xml);
        libxml_use_internal_errors($anterior);

        if ($documento === false) {
            return false;
        }

        return preg_match('/^\d{50}$/', (string) $documento->Clave) === 1
            && (string) $documento->Clave === $clave
            && strlen((string) $documento->NumeroConsecutivo) === 20
            && trim((string) $documento->Emisor->Identificacion->Numero) !== '';
    }
}
