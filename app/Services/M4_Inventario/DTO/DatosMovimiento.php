<?php

namespace App\Services\M4_Inventario\DTO;

use App\Enums\M4_Inventario\EfectoStock;
use App\Enums\M4_Inventario\TipoMovimiento;
use DateTimeInterface;

/**
 * Datos de un movimiento que KardexService asienta junto con su línea de kardex.
 */
final readonly class DatosMovimiento
{
    public function __construct(
        public TipoMovimiento $tipo,
        public EfectoStock $efecto,
        public int $cantidad,
        public float $costoUnitario,
        public int $idUsuario,
        public string $motivo,
        public ?string $referencia = null,
        public ?int $idAutorizador = null,
        public ?DateTimeInterface $fechaAutorizacion = null,
    ) {}
}
