<?php

namespace App\Services\M1_Portal\Dobles;

use App\Contracts\M4_Inventario\InventarioServiceInterface;
use LogicException;

/**
 * Stub de M4 Inventario multiempresa (contrato 1.1.0) para las operaciones que
 * consume M1: consultarDisponibilidadLote y consultarDisponibilidadProductoLote.
 *
 * Las firmas son las de x-interfaz-php del contrato de M4. Ese contrato las
 * ubica en InventarioModuleInterface, que aún no existe; mientras tanto el
 * doble implementa la interfaz publicada en app/Contracts/M4_Inventario.
 *
 * Son consultas públicas: no aplican el filtro por comercio de RN-12. Una
 * variación o producto sin existencia registrada se devuelve agotado, sin error.
 */
class InventarioStub implements InventarioServiceInterface
{
    private const MAXIMO_LOTE = 100;

    /**
     * POST /existencias/disponibilidad/lote. Devuelve la disponibilidad de
     * cada ítem en el mismo orden de la solicitud.
     *
     * @param  list<array{id_comercio: int, id_variacion: int, id_ubicacion?: int}>  $items  Entre 1 y 100 elementos.
     * @return list<array{id_comercio: int, id_variacion: int, id_ubicacion: int|null, cantidad_disponible: int, estado_disponibilidad: string, bajo_stock: bool}>
     */
    public function consultarDisponibilidadLote(array $items): array
    {
        $this->validarLote($items, 'id_variacion', permiteUbicacion: true);

        return array_map(fn (array $item): array => $this->disponibilidadVariacion(
            $item['id_comercio'],
            $item['id_variacion'],
            $item['id_ubicacion'] ?? null,
        ), $items);
    }

    /**
     * POST /existencias/disponibilidad/producto/lote. Un producto está
     * disponible si al menos una de sus variaciones tiene unidades.
     *
     * @param  list<array{id_comercio: int, id_producto: int}>  $items  Entre 1 y 100 elementos.
     * @return list<array{id_comercio: int, id_producto: int, estado_disponibilidad: string}> En el mismo orden.
     */
    public function consultarDisponibilidadProductoLote(array $items): array
    {
        $this->validarLote($items, 'id_producto', permiteUbicacion: false);

        return array_map(function (array $item): array {
            $unidades = 0;

            foreach (DatosPrueba::existencias() as $existencia) {
                if ($existencia['id_comercio'] === $item['id_comercio'] && $existencia['id_producto'] === $item['id_producto']) {
                    $unidades += $existencia['cantidad_disponible'];
                }
            }

            return [
                'id_comercio' => $item['id_comercio'],
                'id_producto' => $item['id_producto'],
                'estado_disponibilidad' => $unidades > 0 ? 'disponible' : 'agotado',
            ];
        }, $items);
    }

    public function consultarExistencias(int $variacionId): int
    {
        throw new LogicException('Fuera del alcance del doble de M1.');
    }

    public function solicitarReserva(int $variacionId, int $cantidad, int $pedidoId): array
    {
        throw new LogicException('Fuera del alcance del doble de M1.');
    }

    public function confirmarSalidaPorDespacho(int $reservaId): bool
    {
        throw new LogicException('Fuera del alcance del doble de M1.');
    }

    /**
     * Suma las ubicaciones del comercio, o solo la indicada.
     *
     * @return array{id_comercio: int, id_variacion: int, id_ubicacion: int|null, cantidad_disponible: int, estado_disponibilidad: string, bajo_stock: bool}
     */
    private function disponibilidadVariacion(int $idComercio, int $idVariacion, ?int $idUbicacion): array
    {
        $cantidad = 0;
        $stockMinimo = null;

        foreach (DatosPrueba::existencias() as $existencia) {
            if ($existencia['id_comercio'] !== $idComercio || $existencia['id_variacion'] !== $idVariacion) {
                continue;
            }

            if ($idUbicacion !== null && $existencia['id_ubicacion'] !== $idUbicacion) {
                continue;
            }

            $cantidad += $existencia['cantidad_disponible'];
            $stockMinimo = ($stockMinimo ?? 0) + $existencia['stock_minimo'];
        }

        return [
            'id_comercio' => $idComercio,
            'id_variacion' => $idVariacion,
            'id_ubicacion' => $idUbicacion,
            'cantidad_disponible' => $cantidad,
            'estado_disponibilidad' => $cantidad > 0 ? 'disponible' : 'agotado',
            'bajo_stock' => $stockMinimo !== null && $cantidad <= $stockMinimo,
        ];
    }

    /**
     * @param  array<mixed>  $items
     */
    private function validarLote(array $items, string $campoId, bool $permiteUbicacion): void
    {
        if ($items === [] || count($items) > self::MAXIMO_LOTE) {
            throw ErrorProveedorException::datosInvalidos(['items' => 'Debe tener entre 1 y '.self::MAXIMO_LOTE.' elementos.']);
        }

        foreach (array_values($items) as $indice => $item) {
            $campos = ['id_comercio', $campoId];

            if ($permiteUbicacion && is_array($item) && array_key_exists('id_ubicacion', $item)) {
                $campos[] = 'id_ubicacion';
            }

            foreach ($campos as $campo) {
                if (! is_array($item) || ! is_int($item[$campo] ?? null) || $item[$campo] < 1) {
                    throw ErrorProveedorException::datosInvalidos(["items.{$indice}.{$campo}" => 'Debe ser un entero mayor o igual a 1.']);
                }
            }
        }
    }
}
