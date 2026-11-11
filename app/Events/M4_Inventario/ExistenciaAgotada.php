<?php

namespace App\Events\M4_Inventario;

/**
 * ExistenciaAgotada v1.0.0. Consumidores: M1 y M3. Se publica cuando la
 * disponibilidad total de la variación en el comercio (todas las ubicaciones)
 * llega a 0; por eso id_ubicacion siempre es nulo.
 */
class ExistenciaAgotada extends EventoInventario
{
    public function __construct(
        public readonly int $idComercio,
        public readonly int $idProducto,
        public readonly int $idVariacion,
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
            'cantidad_disponible' => 0,
        ];
    }
}
