<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos\Simuladores;

use App\Contracts\M6_Pagos\PasarelaPagoInterface;
use App\Models\M6_Pagos\EstadoPago;
use App\Models\M6_Pagos\Pago;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Simulador de pasarela (Tarjeta / SINPE / PayPal). No guarda ni recibe datos
 * sensibles: solo un token de medio de pago (requisito de seguridad del PC2).
 *
 * Matriz de tokens de la interfaz React:
 *   - tok_sim_aprobada / tok_sim_sinpe / tok_sim_paypal -> aprobada.
 *   - tok_sim_rechazada / tok_rechazado              -> rechazada.
 *   - tok_sim_timeout                                -> fallo temporal (timeout).
 *
 * tok_visa_ok y cualquier otro token no listado se mantienen aprobados para
 * no romper las pruebas heredadas ni las llamadas de M5 que no envían token.
 */
class PasarelaPagoSimulador implements PasarelaPagoInterface
{
    public const TOKEN_APROBADO_TARJETA = 'tok_sim_aprobada';

    public const TOKEN_APROBADO_SINPE = 'tok_sim_sinpe';

    public const TOKEN_APROBADO_PAYPAL = 'tok_sim_paypal';

    public const TOKEN_RECHAZO = 'tok_rechazado';

    public const TOKEN_RECHAZO_SIMULADO = 'tok_sim_rechazada';

    public const TOKEN_TIMEOUT = 'tok_sim_timeout';

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

        $token = (string) ($datosPago['token_medio_pago'] ?? '');

        if ($token === self::TOKEN_TIMEOUT) {
            throw new RuntimeException('La pasarela simulada no respondió (timeout).');
        }

        if (in_array($token, [self::TOKEN_RECHAZO, self::TOKEN_RECHAZO_SIMULADO], true)) {
            return [
                'exitoso' => false,
                'codigo_error' => 'ERR_FONDOS_INSUFICIENTES',
                'mensaje' => 'Transacción rechazada por el simulador de pasarela.',
                'referencia_proveedor' => 'PASARELA-SIM-R-'.strtoupper(substr(md5($referencia), 0, 10)),
            ];
        }

        // tok_sim_aprobada, tok_sim_sinpe, tok_sim_paypal y tok_visa_ok caen aquí.
        return [
            'exitoso' => true,
            'referencia_proveedor' => 'PASARELA-SIM-'.strtoupper(substr(md5($referencia), 0, 10)),
            'codigo_respuesta' => '00',
            'mensaje' => 'Transacción aprobada por el simulador de pasarela',
            'fecha_transaccion' => now()->toIso8601String(),
        ];
    }

    /**
     * Reporte de movimientos del período (CU-003). El simulador no guarda sus
     * propias transacciones, así que reporta los cobros aprobados que M6 registró
     * con su referencia. Decisión del subgrupo: por ahora sin opción de demostración,
     * así que normalmente no habrá diferencias.
     */
    public function reportarMovimientos(string $fechaInicio, string $fechaFin): array
    {
        return Pago::query()
            ->whereNotNull('referencia_proveedor')
            ->where('id_estado_pago', EstadoPago::idDe(EstadoPago::CONFIRMADO))
            ->whereBetween('fecha_confirmacion', [Carbon::parse($fechaInicio)->startOfDay(), Carbon::parse($fechaFin)->endOfDay()])
            ->get(['referencia_proveedor', 'monto'])
            ->map(fn (Pago $pago): array => ['referencia_proveedor' => $pago->referencia_proveedor, 'monto' => (float) $pago->monto])
            ->values()
            ->all();
    }
}
