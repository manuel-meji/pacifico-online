<?php

namespace Tests\Doubles\M1;

use App\Models\M1_Portal\BusquedaUsuario;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\RegistroBusquedas;
use RuntimeException;

/**
 * El registro de búsquedas falla siempre (por ejemplo, la base no responde).
 */
class RegistroBusquedasQueFalla extends RegistroBusquedas
{
    public function __construct() {}

    public function registrar(CriterioBusquedaDTO $criterio, int $cantidadResultados, ?string $tipoCategoria): BusquedaUsuario
    {
        throw new RuntimeException('No se pudo guardar la búsqueda.');
    }
}
