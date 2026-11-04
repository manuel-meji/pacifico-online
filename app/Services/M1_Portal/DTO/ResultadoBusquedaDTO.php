<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Resultado paginado de la búsqueda (también los productos del micrositio).
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class ResultadoBusquedaDTO
{
    public function __construct(
        /** @var list<ProductoResumenDTO> */
        public array $productos,
        public int $totalResultados,
        public int $pagina,
        public int $porPagina,
    ) {}
}
