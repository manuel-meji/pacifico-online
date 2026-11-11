<?php

namespace App\Services\M1_Portal;

use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use DateTimeInterface;

/**
 * Reglas de la portada para los productos destacados (contrato M1 1.4.0, SC-08):
 * - Cada comercio tiene como máximo 3 productos destacados activos, sin
 *   importar si los solicitó o si los puso la administración.
 * - La portada muestra hasta 12. Si hay más, se rotan por turnos de 15
 *   minutos: cada turno avanza tantas posiciones como el límite, así todos
 *   aparecen el mismo tiempo y todas las personas ven lo mismo en el turno.
 */
class ReglasDestacados
{
    public const MAXIMO_POR_COMERCIO = 3;

    public const MINUTOS_POR_TURNO = 15;

    /**
     * Número de turno de rotación al que pertenece $ahora.
     */
    public function turno(DateTimeInterface $ahora): int
    {
        return intdiv($ahora->getTimestamp(), self::MINUTOS_POR_TURNO * 60);
    }

    /**
     * Deja los primeros 3 de cada comercio, en el orden recibido (posicion).
     *
     * @param  list<ProductoResumenDTO>  $productos
     * @return list<ProductoResumenDTO>
     */
    public function limitarPorComercio(array $productos): array
    {
        $porComercio = [];
        $resultado = [];

        foreach ($productos as $producto) {
            $cantidad = $porComercio[$producto->idComercio] ?? 0;

            if ($cantidad < self::MAXIMO_POR_COMERCIO) {
                $porComercio[$producto->idComercio] = $cantidad + 1;
                $resultado[] = $producto;
            }
        }

        return $resultado;
    }

    /**
     * Si caben todos, los devuelve sin cambios. Si no, devuelve $limite
     * elementos seguidos de la lista, tomada como circular, a partir de la
     * posición del turno actual.
     *
     * @template T
     *
     * @param  list<T>  $lista
     * @return list<T>
     */
    public function rotar(array $lista, int $limite, DateTimeInterface $ahora): array
    {
        $total = count($lista);

        if ($total <= $limite) {
            return $lista;
        }

        $inicio = ($this->turno($ahora) * $limite) % $total;
        $resultado = [];

        for ($i = 0; $i < $limite; $i++) {
            $resultado[] = $lista[($inicio + $i) % $total];
        }

        return $resultado;
    }
}
