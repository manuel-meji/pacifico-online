<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Estado de disponibilidad que M1 muestra (valores del contrato de M1).
 * M4 responde en minúscula ('disponible', 'agotado'); InventarioClient lo traduce.
 */
enum Disponibilidad: string
{
    case Disponible = 'Disponible';
    case Agotado = 'Agotado';
}
