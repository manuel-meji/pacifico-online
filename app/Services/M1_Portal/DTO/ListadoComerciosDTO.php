<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Página de comercios habilitados.
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class ListadoComerciosDTO
{
    public function __construct(
        /** @var list<ComercioDTO> */
        public array $comercios,
        public int $total,
        public int $pagina,
        public int $porPagina,
    ) {}
}
