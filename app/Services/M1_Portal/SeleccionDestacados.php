<?php

namespace App\Services\M1_Portal;

use App\Models\M1_Portal\ComercioDestacado;
use App\Models\M1_Portal\ProductoDestacado;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lee la selección de destacados que decide M1 (productos_destacados y
 * comercios_destacados, RN-M1-11). Solo devuelve identificadores: los datos
 * del producto y del comercio se piden a M3 y M2 por contrato.
 */
class SeleccionDestacados
{
    /**
     * id_producto vigentes, por posicion.
     *
     * @return list<int>
     */
    public function productosVigentes(DateTimeInterface $ahora): array
    {
        return $this->vigentes(ProductoDestacado::query(), 'id_producto', $ahora);
    }

    /**
     * id_comercio vigentes, por posicion.
     *
     * @return list<int>
     */
    public function comerciosVigentes(DateTimeInterface $ahora): array
    {
        return $this->vigentes(ComercioDestacado::query(), 'id_comercio', $ahora);
    }

    /**
     * Vigente: activo y fecha_inicio <= ahora <= fecha_fin (ambos extremos
     * incluidos, igual que estaVigente() de los modelos). Orden por posicion
     * y, a igual posicion, por el más antiguo. Un mismo id repetido se deja
     * en su primera posición.
     *
     * @param  Builder<covariant Model>  $consulta
     * @return list<int>
     */
    private function vigentes(Builder $consulta, string $campo, DateTimeInterface $ahora): array
    {
        $ids = $consulta
            ->where('activo', true)
            ->where('fecha_inicio', '<=', $ahora)
            ->where('fecha_fin', '>=', $ahora)
            ->orderBy('posicion')
            ->orderBy($consulta->getModel()->getKeyName())
            ->pluck($campo)
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return array_values(array_unique($ids));
    }
}
