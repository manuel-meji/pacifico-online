<?php

namespace App\Services\M4_Inventario\Repositorios;

use App\Models\M4_Inventario\LineaKardex;
use App\Models\M4_Inventario\MovimientoInventario;

/**
 * Registro inmutable de movimientos y su línea de kardex (figura 4.4a del PC3).
 */
interface MovimientoRepositoryInterface
{
    /** Inserta el movimiento y su línea de kardex (1 a 1) en la transacción en curso. */
    public function registrar(MovimientoInventario $movimiento, LineaKardex $linea): void;

    public function ultimaLinea(int $idComercio, int $idVariacion, int $idUbicacion): ?LineaKardex;

    /** Movimiento de un tipo para una referencia (id_reserva), si ya existe. */
    public function porReferencia(int $idComercio, string $tipo, string $referencia): ?MovimientoInventario;
}
