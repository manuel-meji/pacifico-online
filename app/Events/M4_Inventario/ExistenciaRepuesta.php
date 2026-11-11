<?php

namespace App\Events\M4_Inventario;

/**
 * ExistenciaRepuesta v1.0.0. Consumidores: M1, M3 y M5. Se publica cuando la
 * disponibilidad total de la variación en el comercio vuelve a ser mayor que 0.
 */
class ExistenciaRepuesta extends EventoInventario
{
    public function __construct(
        public readonly int $idComercio,
        public readonly int $idProducto,
        public readonly int $idVariacion,
        public readonly int $cantidadDisponible,
    ) {
        parent::__construct();
    }

    protected function datos(): array
    {
        return [
            'id_comercio' => $this->idComercio,
            'id_producto' => $this->idProducto,
            'id_variacion' => $this->idVariacion,
            'id_ubicacion' => null,
            'cantidad_disponible' => $this->cantidadDisponible,
        ];
    }
}
