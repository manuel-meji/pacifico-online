<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Contenido de la página principal.
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class PortadaDTO
{
    public function __construct(
        public ?DestacadosDTO $destacados,
        /** @var list<PromocionDTO> */
        public array $promociones,
        /** @var list<NoticiaDTO> */
        public array $noticias,
        /** @var list<string> */
        public array $avisos = [],
    ) {}
}
