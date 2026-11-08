<?php

namespace App\Enums\M4_Inventario;

/**
 * Efecto del movimiento sobre el stock físico. Se guarda con el valor corto
 * (columna efecto varchar(10)); el contrato lo expone como efecto_stock, donde
 * Ninguno se llama sin_cambio_fisico.
 */
enum EfectoStock: string
{
    case Aumenta = 'aumenta';
    case Disminuye = 'disminuye';
    case Ninguno = 'ninguno';

    public function contrato(): string
    {
        return $this === self::Ninguno ? 'sin_cambio_fisico' : $this->value;
    }
}
