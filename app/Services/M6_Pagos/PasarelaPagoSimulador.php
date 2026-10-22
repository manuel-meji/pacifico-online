<?php

namespace App\Services\M6_Pagos\Simuladores;

use App\Contracts\M6_Pagos\PasarelaPagoInterface;

class PasarelaPagoSimulador implements PasarelaPagoInterface
{
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

        return [
            'exitoso' => true,
            'referencia_proveedor' => 'PASARELA-SIM-' . strtoupper(substr(md5($referencia), 0, 10)),
            'codigo_respuesta' => '00',
            'mensaje' => 'Transacción aprobada por el simulador de pasarela',
            'fecha_transaccion' => now()->toIso8601String(),
        ];
    }
}
