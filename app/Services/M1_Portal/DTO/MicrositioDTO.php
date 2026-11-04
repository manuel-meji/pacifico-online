<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Micrositio público de un comercio.
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class MicrositioDTO
{
    public function __construct(
        public ComercioDTO $comercio,
        public ResultadoBusquedaDTO $productos,
    ) {}
}
