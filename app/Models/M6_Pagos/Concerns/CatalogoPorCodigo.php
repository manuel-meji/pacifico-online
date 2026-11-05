<?php

declare(strict_types=1);

namespace App\Models\M6_Pagos\Concerns;

/**
 * Comportamiento común de las tablas catálogo de M6 (estados_pago, medios_pago, ...):
 * resolver el id a partir del código y cachearlo durante la petición.
 */
trait CatalogoPorCodigo
{
    /** @var array<string, array<string, int>> */
    private static array $idsPorCodigo = [];

    public static function idDe(string $codigo): ?int
    {
        $clase = static::class;

        if (! isset(self::$idsPorCodigo[$clase][$codigo])) {
            $id = static::query()->where(static::columnaCodigo(), $codigo)->value((new static)->getKeyName());

            if ($id === null) {
                return null;
            }

            self::$idsPorCodigo[$clase][$codigo] = (int) $id;
        }

        return self::$idsPorCodigo[$clase][$codigo];
    }

    public static function olvidarCache(): void
    {
        self::$idsPorCodigo = [];
    }

    protected static function columnaCodigo(): string
    {
        return 'codigo';
    }
}
