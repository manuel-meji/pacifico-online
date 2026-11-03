<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Estado del comercio según M2. M1 solo muestra comercios Activo (RN-M1-01).
 */
enum EstadoComercio: string
{
    case Pendiente = 'Pendiente';
    case Activo = 'Activo';
    case Rechazado = 'Rechazado';
    case Suspendido = 'Suspendido';
}
