<?php

namespace App\Services\M2_Comercios;

use App\Contracts\M2_Comercios\ComerciosServiceInterface;

class ComerciosService implements ComerciosServiceInterface
{
    public function obtenerMicrositio(int $comercioId): array
    {
        return [
            'comercio_id' => $comercioId,
            'nombre' => 'Comercio de Prueba',
            'descripcion' => 'Descripción del comercio',
            'estado' => 'ACTIVO',
        ];
    }

    public function registrarComision(int $pedidoId, float $subtotalPedido): bool
    {
        // Paso 9 del recorrido transversal: calcular porcentaje de comisión
        $porcentajeComision = 0.05; // 5%
        $montoComision = $subtotalPedido * $porcentajeComision;

        // Registrar comisión en la base de datos de M2...
        return true;
    }
}
