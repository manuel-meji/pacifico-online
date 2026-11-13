<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Models\M7_Contabilidad\CuentaContable;
use LogicException;

final class CuentaContableRepository
{
    /** @return array<string, int> */
    public function bloquearCuentas(int $comercioId, string $debe, string $haber): array
    {
        return $this->bloquearPorCodigos($comercioId, [$debe, $haber]);
    }

    /** @param list<string> $codigos
     * @return array<string, int>
     */
    public function bloquearPorCodigos(int $comercioId, array $codigos): array
    {
        $codigos = array_values(array_unique($codigos));
        $cuentas = CuentaContable::query()
            ->where('id_comercio', $comercioId)
            ->whereIn('codigo', $codigos)
            ->where('activa', true)
            ->orderBy('codigo')
            ->lockForUpdate()
            ->get()
            ->keyBy('codigo');

        if ($cuentas->count() !== count($codigos)) {
            throw new LogicException('El comercio no tiene las cuentas contables activas necesarias.');
        }

        return $cuentas->map(fn (CuentaContable $cuenta): int => (int) $cuenta->getKey())->all();
    }
}
