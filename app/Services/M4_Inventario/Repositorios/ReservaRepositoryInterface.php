<?php

namespace App\Services\M4_Inventario\Repositorios;

use App\Models\M4_Inventario\Reserva;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Acceso a reservas (patrón Repository, figura 4.4a del PC3).
 */
interface ReservaRepositoryInterface
{
    public function buscar(string $idReserva): ?Reserva;

    public function buscarPorClave(int $idComercio, string $clave): ?Reserva;

    /** SELECT … FOR UPDATE de la reserva; dentro de una transacción. */
    public function bloquear(string $idReserva): ?Reserva;

    /**
     * Reservas ACTIVA con el plazo cumplido, bloqueadas (las toma el vencimiento cada 60 s).
     *
     * @param  list<int>|null  $idsExistencia  Solo las de esas existencias (al reservar).
     * @return Collection<int, Reserva>
     */
    public function activasVencidas(DateTimeInterface $ahora, int $limite, ?array $idsExistencia = null): Collection;

    public function guardar(Reserva $reserva): void;
}
