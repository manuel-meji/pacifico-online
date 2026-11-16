<?php

declare(strict_types=1);

namespace App\Services\M2_Comercios;

use App\Models\M2_Comercios\ComisionReversion;
use App\Models\M2_Comercios\ComisionSubpedido;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reversión de la comisión de un subpedido por una devolución aprobada (RF-021).
 *
 * La comisión original (comisiones_subpedido) no se modifica: cada devolución agrega un
 * registro en comisiones_reversion con la parte proporcional al subtotal devuelto:
 *
 *   monto_revertido = comisión × (subtotal devuelto / subtotal base de la comisión)
 *
 * El acumulado revertido nunca supera la comisión original.
 */
class ReversionComisionService
{
    /**
     * @return ComisionReversion|null null si el subpedido no tiene comisión (no se despachó
     *                                con comisión vigente) o si la devolución ya estaba revertida.
     */
    public function revertirPorDevolucion(int $idSubpedido, int $idDevolucion, string $idEvento, float $subtotalDevuelto): ?ComisionReversion
    {
        $comision = ComisionSubpedido::query()->where('id_subpedido', $idSubpedido)->first();

        if ($comision === null) {
            Log::warning('M2: no hay comisión para revertir por la devolución.', [
                'id_subpedido' => $idSubpedido,
                'id_devolucion' => $idDevolucion,
            ]);

            return null;
        }

        if (ComisionReversion::query()->where('id_devolucion', $idDevolucion)->exists()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($comision, $idSubpedido, $idDevolucion, $idEvento, $subtotalDevuelto): ?ComisionReversion {
                // Bloquea la comisión para que dos devoluciones simultáneas no superen el total.
                $bloqueada = ComisionSubpedido::query()->lockForUpdate()->find($comision->id_comision_subpedido);

                $base = (float) $bloqueada->subtotal_base;
                $montoComision = (float) $bloqueada->monto;

                if ($base <= 0 || $montoComision <= 0) {
                    return null;
                }

                $proporcion = min(1.0, max(0.0, $subtotalDevuelto / $base));
                $yaRevertido = (float) ComisionReversion::query()
                    ->where('id_comision_subpedido', $bloqueada->id_comision_subpedido)
                    ->sum('monto_revertido');

                $monto = round($montoComision * $proporcion, 2);
                $monto = min($monto, round($montoComision - $yaRevertido, 2));

                if ($monto <= 0) {
                    return null;
                }

                return ComisionReversion::create([
                    'id_comision_subpedido' => $bloqueada->id_comision_subpedido,
                    'id_subpedido' => $idSubpedido,
                    'id_comercio' => $bloqueada->id_comercio,
                    'id_devolucion' => $idDevolucion,
                    'id_evento' => $idEvento,
                    'subtotal_devuelto' => round($subtotalDevuelto, 2),
                    'monto_revertido' => $monto,
                    'moneda' => $bloqueada->moneda,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Otro proceso ya revirtió esta devolución.
            return null;
        }
    }
}
