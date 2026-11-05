<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Services\M1_Portal\DTO\ComercioDTO;
use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\DTO\EstadoComercio;
use App\Services\M1_Portal\DTO\FichaProductoDTO;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\DTO\VariacionDTO;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use App\Services\M1_Portal\Integraciones\ComercioClient;
use App\Services\M1_Portal\Integraciones\InventarioClient;

/**
 * Control de CU-003 Consultar ficha de producto. Lo usa la fachada
 * PortalPublicoService.
 */
class FichaProductoService
{
    private const NO_DISPONIBLE = 'El producto no existe o no está disponible en el portal.';

    public function __construct(
        private CatalogoClient $catalogo,
        private ComercioClient $comercios,
        private InventarioClient $inventario,
        private CompletadorProductos $completador,
    ) {}

    /**
     * Un producto agotado no es un error: se muestra con compraHabilitada falso (A2).
     *
     * @throws RecursoNoEncontradoException ERR-004 si el producto no existe, está retirado o su comercio no está Activo.
     * @throws DependenciaNoDisponibleException ERR-006 si M3 no responde.
     */
    public function obtenerFicha(int $idProducto): FichaProductoDTO
    {
        $producto = $this->catalogo->obtenerProducto($idProducto);
        $comercio = $this->verificarComercioHabilitado($producto->comercio);
        $variaciones = $this->combinarDisponibilidad($producto);

        return new FichaProductoDTO(
            idProducto: $producto->idProducto,
            nombre: $producto->nombre,
            descripcion: $producto->descripcion,
            marca: $producto->marca,
            videoUrl: $producto->videoUrl,
            moneda: $producto->moneda,
            imagenes: $producto->imagenes,
            variaciones: $variaciones,
            comercio: $comercio,
            relacionados: $this->relacionados($producto->idProducto),
            compraHabilitada: $this->evaluarCompra($variaciones),
        );
    }

    /**
     * RN-M1-01 y RN-M1-02. Si M2 no responde, sigue con el comercio provisional
     * del adaptador.
     *
     * @throws RecursoNoEncontradoException
     */
    private function verificarComercioHabilitado(ComercioDTO $provisional): ComercioDTO
    {
        try {
            $comercio = $this->comercios->obtenerDatosComercioPublico($provisional->idComercio);
        } catch (DependenciaNoDisponibleException) {
            return $provisional;
        } catch (RecursoNoEncontradoException $error) {
            throw new RecursoNoEncontradoException(self::NO_DISPONIBLE, $error);
        }

        if ($comercio->estado !== EstadoComercio::Activo) {
            throw new RecursoNoEncontradoException(self::NO_DISPONIBLE);
        }

        return $comercio;
    }

    /**
     * Comprable = venta habilitada y Disponible. Si M4 no responde, quedan
     * Agotado y no comprables (valores del adaptador).
     *
     * @return list<VariacionDTO>
     */
    private function combinarDisponibilidad(FichaProductoDTO $producto): array
    {
        try {
            $disponibilidades = $this->inventario->consultarDisponibilidadVariaciones(
                $producto->idProducto,
                $producto->comercio->idComercio,
                array_map(fn (VariacionDTO $variacion): int => $variacion->idVariacion, $producto->variaciones),
            );
        } catch (DependenciaNoDisponibleException) {
            return $producto->variaciones;
        }

        $estados = [];

        foreach ($disponibilidades as $disponibilidad) {
            $estados[$disponibilidad->idVariacion] = $disponibilidad->estado;
        }

        return array_map(function (VariacionDTO $variacion) use ($estados): VariacionDTO {
            $estado = $estados[$variacion->idVariacion] ?? Disponibilidad::Agotado;

            return new VariacionDTO(
                idVariacion: $variacion->idVariacion,
                sku: $variacion->sku,
                atributos: $variacion->atributos,
                precio: $variacion->precio,
                imagenes: $variacion->imagenes,
                ventaHabilitada: $variacion->ventaHabilitada,
                disponibilidad: $estado,
                comprable: $variacion->ventaHabilitada && $estado === Disponibilidad::Disponible,
            );
        }, $producto->variaciones);
    }

    /**
     * RN-M1-04: se puede comprar si al menos una variación es comprable.
     *
     * @param  list<VariacionDTO>  $variaciones
     */
    private function evaluarCompra(array $variaciones): bool
    {
        foreach ($variaciones as $variacion) {
            if ($variacion->comprable) {
                return true;
            }
        }

        return false;
    }

    /**
     * Si M3 no responde con los relacionados, la ficha se muestra sin ellos.
     *
     * @return list<ProductoResumenDTO>
     */
    private function relacionados(int $idProducto): array
    {
        try {
            return $this->completador->completar($this->catalogo->listarRelacionados($idProducto));
        } catch (DependenciaNoDisponibleException) {
            return [];
        }
    }
}
