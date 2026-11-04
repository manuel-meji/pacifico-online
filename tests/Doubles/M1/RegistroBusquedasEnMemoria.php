<?php

namespace Tests\Doubles\M1;

use App\Models\M1_Portal\BusquedaUsuario;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\RegistroBusquedas;

/**
 * Registro de búsquedas sin base de datos: las guarda en $registradas.
 */
class RegistroBusquedasEnMemoria extends RegistroBusquedas
{
    /**
     * @var list<array{termino: string, cantidad_resultados: int, filtros: array<string, string>}>
     */
    public array $registradas = [];

    public function __construct() {}

    public function registrar(CriterioBusquedaDTO $criterio, int $cantidadResultados, ?string $tipoCategoria): BusquedaUsuario
    {
        $this->registradas[] = [
            'termino' => $criterio->termino ?? '',
            'cantidad_resultados' => $cantidadResultados,
            'filtros' => RegistroBusquedas::filtros($criterio, $tipoCategoria),
        ];

        return new BusquedaUsuario([
            'termino_busqueda' => $criterio->termino ?? '',
            'cantidad_resultados' => $cantidadResultados,
            'fecha_busqueda' => now(),
        ]);
    }
}
