<?php

namespace App\Services\M4_Inventario\Repositorios;

use App\Models\M4_Inventario\LineaKardex;
use App\Models\M4_Inventario\MovimientoInventario;

/**
 * Implementación Eloquent (figura 4.6a del PC3). Solo inserta: movimientos y
 * kardex son inmutables.
 */
class EloquentMovimientoRepository implements MovimientoRepositoryInterface
{
    public function registrar(MovimientoInventario $movimiento, LineaKardex $linea): void
    {
        $movimiento->save();
        $linea->id_movimiento = $movimiento->id_movimiento;
        $linea->id_comercio = $movimiento->id_comercio;
        $linea->save();
        $movimiento->setRelation('lineaKardex', $linea);
        $linea->setRelation('movimiento', $movimiento);
    }

    public function ultimaLinea(int $idComercio, int $idVariacion, int $idUbicacion): ?LineaKardex
    {
        return LineaKardex::query()
            ->with('movimiento')
            ->whereHas('movimiento', fn ($q) => $q
                ->where('id_comercio', $idComercio)
                ->where('id_variacion', $idVariacion)
                ->where('id_ubicacion', $idUbicacion))
            ->orderByDesc('id_kardex')
            ->first();
    }

    public function porReferencia(int $idComercio, string $tipo, string $referencia): ?MovimientoInventario
    {
        return MovimientoInventario::query()
            ->with('lineaKardex')
            ->where('id_comercio', $idComercio)
            ->where('tipo', $tipo)
            ->where('referencia', $referencia)
            ->first();
    }
}
