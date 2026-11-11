<?php

namespace App\Services\M1_Portal\DTO;

/**
 * Datos públicos de un comercio que entrega M2 (ComercioPublico).
 *
 * Objeto de transferencia (Vista 6 del PC3): solo atributos públicos, sin
 * métodos. No se persiste en M1.
 */
class ComercioDTO
{
    public function __construct(
        public int $idComercio,
        public string $nombreComercial,
        public ?string $logotipo,
        public ?string $descripcion,
        /** @var list<string> */
        public array $imagenes,
        /** @var list<array<string, mixed>> */
        public array $horarios,
        /** @var array<string, mixed> */
        public array $contacto,
        /** @var list<array<string, mixed>> */
        public array $redesSociales,
        /** @var list<array<string, mixed>> */
        public array $politicas,
        public EstadoComercio $estado,
        /**
         * Banners y galería tal como los entrega M2, para el micrositio. Las
         * mismas imágenes también están, solo con su URL, en $imagenes.
         *
         * @var list<array{imagen_url: string, texto_alternativo: ?string}>
         */
        public array $banners = [],
        /** @var list<array{imagen_url: string, texto_alternativo: ?string}> */
        public array $galeria = [],
    ) {}
}
