<?php

namespace App\Services\M4_Inventario\Dominio\Valoracion;

use App\Models\M4_Inventario\Existencia;

/**
 * Costo promedio ponderado (Strategy concreta). Cada entrada recalcula
 * (física × promedio + cantidad × costo) ÷ (física + cantidad); las salidas
 * salen al promedio vigente. Precisión numeric(14,4) de la sección 6.5.
 */
class PromedioPonderado implements MetodoValoracion
{
    public function nombre(): string
    {
        return 'promedio_ponderado';
    }

    public function costoDeSalida(Existencia $existencia, int $cantidad): float
    {
        return round($existencia->costo_promedio, 4);
    }

    public function costoTrasEntrada(Existencia $existencia, int $cantidad, float $costoUnitario): float
    {
        $total = $existencia->cantidad_fisica + $cantidad;

        if ($total <= 0) {
            return round($costoUnitario, 4);
        }

        $valor = $existencia->cantidad_fisica * $existencia->costo_promedio + $cantidad * $costoUnitario;

        return round($valor / $total, 4);
    }
}
