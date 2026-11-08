<?php

namespace App\Services\M4_Inventario\Dominio\Valoracion;

use App\Models\M4_Inventario\Existencia;

/**
 * Método de valoración del costo (patrón Strategy, figura 4.3b y sección 7.2 del
 * PC3). KardexService lo usa sin conocer la implementación; el método vigente lo
 * enlaza InventarioServiceProvider (SC-M4-07: promedio ponderado).
 */
interface MetodoValoracion
{
    /** Código que se publica en LineaKardex.metodo_valorizacion. */
    public function nombre(): string;

    /** Costo unitario con el que sale una cantidad de la existencia. */
    public function costoDeSalida(Existencia $existencia, int $cantidad): float;

    /** Costo unitario de la existencia después de una entrada con su costo. */
    public function costoTrasEntrada(Existencia $existencia, int $cantidad, float $costoUnitario): float;
}
