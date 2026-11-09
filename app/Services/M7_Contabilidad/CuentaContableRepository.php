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
        $cuentas = CuentaContable::query()
            ->where('id_comercio', $comercioId)
            ->whereIn('codigo', [$debe, $haber])
            ->where('activa', true)
            ->orderBy('codigo')
            ->lockForUpdate()
            ->get()
            ->keyBy('codigo');

        if (! $cuentas->has($debe) || ! $cuentas->has($haber)) {
            throw new LogicException('El comercio no tiene las cuentas contables activas necesarias.');
        }

        return [$debe => (int) $cuentas[$debe]->getKey(), $haber => (int) $cuentas[$haber]->getKey()];
    }
}
