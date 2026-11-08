<?php

namespace App\Services\M4_Inventario\Repositorios;

use App\Models\M4_Inventario\Existencia;
use Illuminate\Support\Collection;

/**
 * Acceso a existencias (patrón Repository, figura 4.4a del PC3). Los métodos
 * «bloquear» toman SELECT … FOR UPDATE y deben llamarse dentro de una
 * transacción (ADR-002). Todas las consultas pasan por el Global Scope del núcleo.
 */
interface ExistenciaRepositoryInterface
{
    public function bloquear(int $idComercio, int $idVariacion, int $idUbicacion): ?Existencia;

    public function bloquearPorId(int $idExistencia): ?Existencia;

    public function bloquearOCrear(int $idComercio, int $idProducto, int $idVariacion, int $idUbicacion): Existencia;

    /**
     * Filas de la variación en el comercio, bloqueadas y en orden de id_existencia.
     *
     * @return Collection<int, Existencia>
     */
    public function candidatasParaReserva(int $idComercio, int $idVariacion): Collection;

    public function disponibleTotal(int $idComercio, int $idVariacion): int;

    /**
     * Suma de disponible y de stock mínimo de la variación (en una ubicación o en todas).
     *
     * @return array{disponible: int, minimo: int, filas: int}
     */
    public function resumenVariacion(int $idComercio, int $idVariacion, ?int $idUbicacion = null): array;

    public function disponibleProducto(int $idComercio, int $idProducto): int;

    public function guardar(Existencia $existencia): void;
}
