<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\DTO\EstadoComercio;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\Integraciones\ComercioClient;
use App\Services\M1_Portal\Integraciones\InventarioClient;

/**
 * Completa una lista de productos de M3 con el comercio (M2) y la
 * disponibilidad (M4). La usan la búsqueda y la ficha (relacionados).
 */
class CompletadorProductos
{
    public function __construct(
        private ComercioClient $comercios,
        private InventarioClient $inventario,
    ) {}

    /**
     * Deja solo los de comercios Activo, con su nombre, y les pone la
     * disponibilidad. Respeta el orden.
     *
     * @param  list<ProductoResumenDTO>  $productos
     * @return list<ProductoResumenDTO>
     */
    public function completar(array $productos): array
    {
        return $this->completarConInventario($productos)[0];
    }

    /**
     * Igual que completar(), y además dice si M4 respondió. Si no respondió,
     * la disponibilidad es la que dejó el adaptador y no se debe tomar como
     * cierta (la portada no oculta productos por eso).
     *
     * @param  list<ProductoResumenDTO>  $productos
     * @return array{0: list<ProductoResumenDTO>, 1: bool}
     */
    public function completarConInventario(array $productos): array
    {
        if ($productos === []) {
            return [[], true];
        }

        return $this->combinarDisponibilidad($this->descartarComerciosNoHabilitados($productos));
    }

    /**
     * RN-M1-01 y RN-M1-02. Si M2 no responde, quedan como los entregó M3.
     *
     * @param  list<ProductoResumenDTO>  $productos
     * @return list<ProductoResumenDTO>
     */
    private function descartarComerciosNoHabilitados(array $productos): array
    {
        try {
            $comercios = $this->comercios->obtenerResumenComercios(
                array_values(array_unique(array_map(fn (ProductoResumenDTO $producto): int => $producto->idComercio, $productos)))
            );
        } catch (DependenciaNoDisponibleException) {
            return $productos;
        }

        $nombres = [];

        foreach ($comercios as $comercio) {
            if ($comercio->estado === EstadoComercio::Activo) {
                $nombres[$comercio->idComercio] = $comercio->nombreComercial;
            }
        }

        $habilitados = [];

        foreach ($productos as $producto) {
            if (isset($nombres[$producto->idComercio])) {
                $habilitados[] = $this->copiar($producto, nombreComercio: $nombres[$producto->idComercio]);
            }
        }

        return $habilitados;
    }

    /**
     * Si M4 no responde, quedan como Agotado (valor del adaptador) y el
     * segundo valor es false.
     *
     * @param  list<ProductoResumenDTO>  $productos
     * @return array{0: list<ProductoResumenDTO>, 1: bool}
     */
    private function combinarDisponibilidad(array $productos): array
    {
        if ($productos === []) {
            return [[], true];
        }

        $idsProducto = [];

        foreach ($productos as $producto) {
            $idsProducto[$producto->idProducto] = $producto->idComercio;
        }

        try {
            $disponibilidades = $this->inventario->consultarDisponibilidadProductos($idsProducto);
        } catch (DependenciaNoDisponibleException) {
            return [$productos, false];
        }

        $estados = [];

        foreach ($disponibilidades as $disponibilidad) {
            $estados[$disponibilidad->idProducto] = $disponibilidad->estado;
        }

        return [array_map(
            fn (ProductoResumenDTO $producto): ProductoResumenDTO => $this->copiar(
                $producto,
                disponibilidad: $estados[$producto->idProducto] ?? $producto->disponibilidad,
            ),
            $productos,
        ), true];
    }

    private function copiar(ProductoResumenDTO $producto, ?string $nombreComercio = null, ?Disponibilidad $disponibilidad = null): ProductoResumenDTO
    {
        return new ProductoResumenDTO(
            idProducto: $producto->idProducto,
            idComercio: $producto->idComercio,
            nombre: $producto->nombre,
            imagenPrincipal: $producto->imagenPrincipal,
            precio: $producto->precio,
            precioHasta: $producto->precioHasta,
            moneda: $producto->moneda,
            nombreComercio: $nombreComercio ?? $producto->nombreComercio,
            disponibilidad: $disponibilidad ?? $producto->disponibilidad,
        );
    }
}
