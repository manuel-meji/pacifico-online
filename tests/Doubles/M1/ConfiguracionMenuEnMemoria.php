<?php

namespace Tests\Doubles\M1;

use App\Services\M1_Portal\ConfiguracionMenuCategorias;

/**
 * Filas de categorias_portal sin base de datos.
 */
class ConfiguracionMenuEnMemoria extends ConfiguracionMenuCategorias
{
    /**
     * @param  array<int, array{orden: int, visible: bool, icono_url: string|null}>  $filas
     */
    public function __construct(private array $filas = []) {}

    public function filas(): array
    {
        return $this->filas;
    }
}
