<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos\Concerns;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Solo para pruebas. Mientras corren las migraciones de M6 sobre SQLite (phpunit.xml),
 * las llamadas DB::statement() / DB::unprepared() que solo existen en PostgreSQL
 * (ALTER TABLE ... ADD CONSTRAINT y el trigger plpgsql de notas de crédito) se omiten.
 *
 * Así las migraciones de M6 se prueban tal cual están en el repo, sin reescribirlas
 * (Guía de Trabajo con Base de Datos, sección 4). En PostgreSQL no se omite nada.
 * Solo se sustituye lo que resuelve la fachada DB; el servicio 'db' del contenedor
 * (el que usa el migrador de Laravel) no cambia.
 */
final class BaseSinSentenciasPostgres extends DB
{
    private const SOLO_POSTGRES = '/\bADD\s+CONSTRAINT\b|\bplpgsql\b|\bTRIGGER\b|\bFUNCTION\b/i';

    public static function durante(Closure $operacion): mixed
    {
        $original = DB::getFacadeRoot();
        self::$resolvedInstance['db'] = new SentenciasFiltradas($original, self::SOLO_POSTGRES);

        try {
            return $operacion();
        } finally {
            self::$resolvedInstance['db'] = $original;
        }
    }
}
