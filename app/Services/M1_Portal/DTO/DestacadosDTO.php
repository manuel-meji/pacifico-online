<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Productos y comercios destacados de la página principal.
 * seccionesNoDisponibles nombra las secciones que no se pudieron cargar.
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class DestacadosDTO
{
    public function __construct(
        /** @var list<ProductoResumenDTO> */
        public array $productos,
        /** @var list<ComercioDTO> */
        public array $comercios,
        /** @var list<string> */
        public array $seccionesNoDisponibles = [],
    ) {}
}
