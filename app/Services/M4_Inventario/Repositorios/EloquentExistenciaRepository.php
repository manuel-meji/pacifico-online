<?php

namespace App\Services\M4_Inventario\Repositorios;

use App\Models\M4_Inventario\Existencia;
use Illuminate\Support\Collection;

/**
 * Implementación Eloquent con bloqueo pesimista (figura 4.6a del PC3).
 */
class EloquentExistenciaRepository implements ExistenciaRepositoryInterface
{
    private const DISPONIBLE_SQL = 'cantidad_fisica - cantidad_reservada';

    public function bloquear(int $idComercio, int $idVariacion, int $idUbicacion): ?Existencia
    {
        return Existencia::query()
            ->where('id_comercio', $idComercio)
            ->where('id_variacion', $idVariacion)
            ->where('id_ubicacion', $idUbicacion)
            ->lockForUpdate()
            ->first();
    }

    public function bloquearPorId(int $idExistencia): ?Existencia
    {
        return Existencia::query()->whereKey($idExistencia)->lockForUpdate()->first();
    }

    public function bloquearOCrear(int $idComercio, int $idProducto, int $idVariacion, int $idUbicacion): Existencia
    {
        $existencia = $this->bloquear($idComercio, $idVariacion, $idUbicacion);

        if ($existencia !== null) {
            return $existencia;
        }

        Existencia::query()->create([
            'id_comercio' => $idComercio,
            'id_producto' => $idProducto,
            'id_variacion' => $idVariacion,
            'id_ubicacion' => $idUbicacion,
        ]);

        return $this->bloquear($idComercio, $idVariacion, $idUbicacion);
    }

    public function candidatasParaReserva(int $idComercio, int $idVariacion): Collection
    {
        return Existencia::query()
            ->where('id_comercio', $idComercio)
            ->where('id_variacion', $idVariacion)
            ->orderBy('id_existencia')
            ->lockForUpdate()
            ->get();
    }

    public function disponibleTotal(int $idComercio, int $idVariacion): int
    {
        return $this->resumenVariacion($idComercio, $idVariacion)['disponible'];
    }

    public function resumenVariacion(int $idComercio, int $idVariacion, ?int $idUbicacion = null): array
    {
        $fila = Existencia::query()
            ->where('id_comercio', $idComercio)
            ->where('id_variacion', $idVariacion)
            ->when($idUbicacion !== null, fn ($q) => $q->where('id_ubicacion', $idUbicacion))
            ->toBase()
            ->selectRaw('COALESCE(SUM('.self::DISPONIBLE_SQL.'), 0) AS disponible, COALESCE(SUM(stock_minimo), 0) AS minimo, COUNT(*) AS filas')
            ->first();

        return [
            'disponible' => (int) $fila->disponible,
            'minimo' => (int) $fila->minimo,
            'filas' => (int) $fila->filas,
        ];
    }

    public function disponibleProducto(int $idComercio, int $idProducto): int
    {
        return (int) Existencia::query()
            ->where('id_comercio', $idComercio)
            ->where('id_producto', $idProducto)
            ->toBase()
            ->selectRaw('COALESCE(SUM('.self::DISPONIBLE_SQL.'), 0) AS disponible')
            ->value('disponible');
    }

    public function guardar(Existencia $existencia): void
    {
        $existencia->save();
    }
}
