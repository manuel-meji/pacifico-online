<?php

namespace App\Services\M4_Inventario\DTO;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Carbon\CarbonImmutable;

/**
 * Consulta de auditoría (RN-M4-015): comercios a consultar y, opcionalmente, el
 * periodo del kardex (ambas fechas o ninguna).
 */
final readonly class FiltroAuditoria
{
    public const MAXIMO_COMERCIOS = 50;

    /**
     * @param  list<int>  $idsComercio
     */
    public function __construct(
        public array $idsComercio,
        public ?CarbonImmutable $desde,
        public ?CarbonImmutable $hasta,
    ) {}

    /**
     * @param  array<mixed>  $idsComercio
     */
    public static function crear(array $idsComercio, ?string $fechaInicio, ?string $fechaFin): self
    {
        $ids = array_values(array_unique($idsComercio, SORT_REGULAR));

        if ($ids === [] || count($ids) > self::MAXIMO_COMERCIOS) {
            throw ErrorInventarioException::datosInvalidos(['ids_comercio' => 'Indique entre 1 y '.self::MAXIMO_COMERCIOS.' comercios.']);
        }

        foreach ($ids as $id) {
            if (! is_int($id) || $id < 1) {
                throw ErrorInventarioException::datosInvalidos(['ids_comercio' => 'Cada comercio debe ser un entero mayor o igual a 1.']);
            }
        }

        $conInicio = $fechaInicio !== null && $fechaInicio !== '';
        $conFin = $fechaFin !== null && $fechaFin !== '';

        if ($conInicio !== $conFin) {
            throw ErrorInventarioException::rangoFechasInvalido();
        }

        [$desde, $hasta] = FiltroKardex::periodo($fechaInicio, $fechaFin);

        return new self($ids, $desde, $hasta);
    }
}
