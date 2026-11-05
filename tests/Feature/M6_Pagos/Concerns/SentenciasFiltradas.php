<?php

declare(strict_types=1);

namespace Tests\Feature\M6_Pagos\Concerns;

use Illuminate\Database\DatabaseManager;

/** Reenvía todo a la base real, salvo las sentencias exclusivas de PostgreSQL en otros motores. */
final class SentenciasFiltradas
{
    public function __construct(private readonly DatabaseManager $db, private readonly string $patron) {}

    public function statement(string $query, array $bindings = []): bool
    {
        return $this->omitir($query) ? true : $this->db->statement($query, $bindings);
    }

    public function unprepared(string $query): bool
    {
        return $this->omitir($query) ? true : $this->db->unprepared($query);
    }

    /** @param array<int, mixed> $argumentos */
    public function __call(string $metodo, array $argumentos): mixed
    {
        return $this->db->{$metodo}(...$argumentos);
    }

    private function omitir(string $query): bool
    {
        return $this->db->getDriverName() !== 'pgsql' && preg_match($this->patron, $query) === 1;
    }
}
