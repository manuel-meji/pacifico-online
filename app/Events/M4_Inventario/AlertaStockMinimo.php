<?php

namespace App\Events\M4_Inventario;

use App\Models\M4_Inventario\Existencia;

/**
 * AlertaStockMinimo v1.0.0. Se publica cuando el disponible de una existencia queda
 * en o bajo su stock mínimo (RN-M4-004). Sin consumidores desde la SC-15: M2 retiró
 * su consumo y la alerta de reposición se muestra en «Existencias y mínimos» de M4.
 */
class AlertaStockMinimo extends EventoInventario
{
    public readonly int $idComercio;

    public readonly int $idVariacion;

    public readonly int $idUbicacion;

    public readonly int $cantidadDisponible;

    public readonly int $stockMinimo;

    public function __construct(Existencia $existencia)
    {
        parent::__construct();
        $this->idComercio = $existencia->id_comercio;
        $this->idVariacion = $existencia->id_variacion;
        $this->idUbicacion = $existencia->id_ubicacion;
        $this->cantidadDisponible = $existencia->disponible();
        $this->stockMinimo = $existencia->stock_minimo;
    }

    protected function datos(): array
    {
        return [
            'id_comercio' => $this->idComercio,
            'id_variacion' => $this->idVariacion,
            'id_ubicacion' => $this->idUbicacion,
            'cantidad_disponible' => $this->cantidadDisponible,
            'stock_minimo' => $this->stockMinimo,
        ];
    }
}
