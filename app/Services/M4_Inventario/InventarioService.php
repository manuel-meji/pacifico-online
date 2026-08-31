<?php

namespace App\Services\M4_Inventario;

use App\Contracts\M4_Inventario\InventarioServiceInterface;

class InventarioService implements InventarioServiceInterface
{
    public function consultarExistencias(int $variacionId): int
    {
        // En implementación real: consultar tabla inventario_existencias con aislamiento
        return 10;
    }

    public function solicitarReserva(int $variacionId, int $cantidad, int $pedidoId): array
    {
        // Paso 4: Reserva atómica (Regla RN-04) con bloqueo pesimista
        return [
            'reserva_id' => rand(1000, 9999),
            'variacion_id' => $variacionId,
            'cantidad' => $cantidad,
            'pedido_id' => $pedidoId,
            'estado' => 'RESERVADO',
            'expira_en' => now()->addMinutes(15)->toIso8601String(),
        ];
    }

    public function confirmarSalidaPorDespacho(int $reservaId): bool
    {
        // Paso 7: Convertir reserva en salida definitiva de inventario
        return true;
    }
}
