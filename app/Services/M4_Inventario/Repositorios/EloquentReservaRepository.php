<?php

namespace App\Services\M4_Inventario\Repositorios;

use App\Enums\M4_Inventario\EstadoReservaCodigo;
use App\Models\M4_Inventario\Reserva;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Implementación Eloquent con bloqueo pesimista (figura 4.6a del PC3).
 */
class EloquentReservaRepository implements ReservaRepositoryInterface
{
    public function buscar(string $idReserva): ?Reserva
    {
        if (! Str::isUuid($idReserva)) {
            return null;
        }

        return Reserva::query()->with('existencia')->find($idReserva);
    }

    public function buscarPorClave(int $idComercio, string $clave): ?Reserva
    {
        return Reserva::query()
            ->with('existencia')
            ->where('id_comercio', $idComercio)
            ->where('clave_idempotencia', $clave)
            ->first();
    }

    public function bloquear(string $idReserva): ?Reserva
    {
        if (! Str::isUuid($idReserva)) {
            return null;
        }

        return Reserva::query()->whereKey($idReserva)->lockForUpdate()->first();
    }

    public function activasVencidas(DateTimeInterface $ahora, int $limite, ?array $idsExistencia = null): Collection
    {
        return Reserva::query()
            ->where('id_estado_reserva', EstadoReservaCodigo::Activa->value)
            ->where('fecha_vencimiento', '<=', $ahora)
            ->when($idsExistencia !== null, fn ($q) => $q->whereIn('id_existencia', $idsExistencia))
            ->orderBy('fecha_vencimiento')
            ->limit($limite)
            ->lockForUpdate()
            ->get();
    }

    public function guardar(Reserva $reserva): void
    {
        $reserva->save();
    }
}
