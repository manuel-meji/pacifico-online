<?php

namespace Tests\Doubles\M6;

use App\Contracts\M6_Pagos\PasarelaPagoInterface;

class MockPagosService implements PasarelaPagoInterface
{
    public function procesarCobro(string $referencia, float $monto, array $datosPago): array
    {
        return [
            'exitoso' => true,
            'referencia_proveedor' => 'MOCK-M6-PAGO-OK',
            'codigo_respuesta' => '00',
            'mensaje' => 'Cobro aprobado simulado para pruebas de integración',
            'fecha_transaccion' => now()->toIso8601String(),
        ];
    }
}
