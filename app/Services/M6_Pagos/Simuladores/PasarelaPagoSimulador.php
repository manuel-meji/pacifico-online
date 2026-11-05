<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos\Simuladores;

use App\Contracts\M6_Pagos\PasarelaPagoInterface;

/**
 * Simulador de pasarela (Tarjeta / SINPE / PayPal). No guarda ni recibe datos
 * sensibles: solo un token de medio de pago (requisito de seguridad del PC2).
 *
 * Para forzar un rechazo en pruebas o demos: token_medio_pago = "tok_rechazado".
 */
class PasarelaPagoSimulador implements PasarelaPagoInterface
{
    public const TOKEN_RECHAZO = 'tok_rechazado';

    public function procesarCobro(string $referencia, float $monto, array $datosPago): array
    {
        if ($monto <= 0) {
            return [
                'exitoso' => false,
                'codigo_error' => 'ERR_MONTO_INVALIDO',
                'mensaje' => 'El monto del cobro debe ser mayor a cero.',
                'referencia_proveedor' => null,
            ];
        }

        if (($datosPago['token_medio_pago'] ?? null) === self::TOKEN_RECHAZO) {
            return [
                'exitoso' => false,
                'codigo_error' => 'ERR_FONDOS_INSUFICIENTES',
                'mensaje' => 'Transacción rechazada por el simulador de pasarela.',
                'referencia_proveedor' => 'PASARELA-SIM-R-'.strtoupper(substr(md5($referencia), 0, 10)),
            ];
        }

        return [
            'exitoso' => true,
            'referencia_proveedor' => 'PASARELA-SIM-'.strtoupper(substr(md5($referencia), 0, 10)),
            'codigo_respuesta' => '00',
            'mensaje' => 'Transacción aprobada por el simulador de pasarela',
            'fecha_transaccion' => now()->toIso8601String(),
        ];
    }
}
