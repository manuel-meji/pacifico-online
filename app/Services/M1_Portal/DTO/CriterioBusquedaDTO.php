<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Criterio de búsqueda con los filtros del contrato v1.1.0.
 * orden admite precio_asc, precio_desc o novedad.
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class CriterioBusquedaDTO
{
    public function __construct(
        public ?string $termino = null,
        public ?int $idCategoria = null,
        public ?int $idComercio = null,
        public ?int $idMarca = null,
        public ?float $precioMinimo = null,
        public ?float $precioMaximo = null,
        public ?string $orden = null,
        public int $pagina = 1,
        public int $porPagina = 20,
    ) {}
}
