<?php

namespace App\Services\M4_Inventario\DTO;

use App\Services\M4_Inventario\Excepciones\ErrorInventarioException;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Filtro de consultarKardex y obtenerKardexValorizado: comercio, variación o tipo,
 * periodo inclusive y paginación (por defecto 50, máximo 200).
 */
final readonly class FiltroKardex
{
    public const POR_PAGINA = 50;

    public const MAXIMO_POR_PAGINA = 200;

    public function __construct(
        public int $idComercio,
        public ?int $idVariacion = null,
        public ?int $idUbicacion = null,
        public ?CarbonImmutable $desde = null,
        public ?CarbonImmutable $hasta = null,
        public ?string $tipoMovimiento = null,
        public int $pagina = 1,
        public int $porPagina = self::POR_PAGINA,
    ) {}

    /**
     * Página y tamaño de página válidos (ERR-005).
     *
     * @param  array<string, mixed>  $filtros
     * @return array{0: int, 1: int}
     */
    public static function paginacion(array $filtros): array
    {
        $pagina = $filtros['pagina'] ?? 1;
        $porPagina = $filtros['por_pagina'] ?? self::POR_PAGINA;
        $errores = [];

        if (! is_numeric($pagina) || (int) $pagina < 1) {
            $errores['pagina'] = 'Debe ser un entero mayor o igual a 1.';
        }

        if (! is_numeric($porPagina) || (int) $porPagina < 1 || (int) $porPagina > self::MAXIMO_POR_PAGINA) {
            $errores['por_pagina'] = 'Debe estar entre 1 y '.self::MAXIMO_POR_PAGINA.'.';
        }

        if ($errores !== []) {
            throw ErrorInventarioException::datosInvalidos($errores);
        }

        return [(int) $pagina, (int) $porPagina];
    }

    /**
     * Rango de fechas inclusive (formato Y-m-d). Fechas inválidas o fin antes del
     * inicio responden ERR-M4-012.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    public static function periodo(?string $inicio, ?string $fin, bool $obligatorio = false): array
    {
        if (($inicio === null || $inicio === '') && ($fin === null || $fin === '')) {
            if ($obligatorio) {
                throw ErrorInventarioException::rangoFechasInvalido();
            }

            return [null, null];
        }

        $desde = $inicio ? self::fecha($inicio)->startOfDay() : null;
        $hasta = $fin ? self::fecha($fin)->endOfDay() : null;

        if (($obligatorio && ($desde === null || $hasta === null)) || ($desde && $hasta && $hasta->lt($desde))) {
            throw ErrorInventarioException::rangoFechasInvalido();
        }

        return [$desde, $hasta];
    }

    private static function fecha(string $texto): CarbonImmutable
    {
        try {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $texto);
        } catch (Throwable) {
            $fecha = false;
        }

        // Rechaza fechas que PHP «corrige» (2026-02-30 → 2026-03-02).
        if (! $fecha instanceof CarbonImmutable || $fecha->format('Y-m-d') !== $texto) {
            throw ErrorInventarioException::rangoFechasInvalido();
        }

        return $fecha;
    }
}
