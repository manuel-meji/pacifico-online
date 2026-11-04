<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\CriterioInvalidoException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\DTO\Disponibilidad;
use App\Services\M1_Portal\DTO\EstadoComercio;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\DTO\ResultadoBusquedaDTO;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use App\Services\M1_Portal\Integraciones\ComercioClient;
use App\Services\M1_Portal\Integraciones\InventarioClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Control de CU-002 Buscar productos. Lo usa la fachada PortalPublicoService.
 */
class BusquedaService
{
    public const LARGO_MINIMO_TERMINO = 2;

    public function __construct(
        private CatalogoClient $catalogo,
        private ComercioClient $comercios,
        private InventarioClient $inventario,
        private RegistroBusquedas $registro,
    ) {}

    /**
     * @throws CriterioInvalidoException ERR-005 (RN-M1-08).
     * @throws DependenciaNoDisponibleException ERR-006 si M3 no responde.
     */
    public function buscar(CriterioBusquedaDTO $criterio): ResultadoBusquedaDTO
    {
        $criterio = $this->normalizar($criterio);
        $this->validarCriterio($criterio);

        $resultado = $this->catalogo->buscarProductos($criterio);

        if ($resultado->productos === []) {
            $this->registrar($criterio, 0);

            return $resultado;
        }

        $productos = $this->descartarComerciosNoHabilitados($resultado->productos);
        $descartados = count($resultado->productos) - count($productos);
        $productos = $this->combinarDisponibilidad($productos);

        $busqueda = new ResultadoBusquedaDTO(
            productos: $productos,
            totalResultados: max(0, $resultado->totalResultados - $descartados),
            pagina: $resultado->pagina,
            porPagina: $resultado->porPagina,
        );

        $this->registrar($criterio, $busqueda->totalResultados);

        return $busqueda;
    }

    private function normalizar(CriterioBusquedaDTO $criterio): CriterioBusquedaDTO
    {
        $termino = trim((string) $criterio->termino);

        return new CriterioBusquedaDTO(
            termino: $termino === '' ? null : $termino,
            idCategoria: $criterio->idCategoria,
            idComercio: $criterio->idComercio,
            idMarca: $criterio->idMarca,
            precioMinimo: $criterio->precioMinimo,
            precioMaximo: $criterio->precioMaximo,
            orden: $criterio->orden,
            pagina: $criterio->pagina,
            porPagina: $criterio->porPagina,
        );
    }

    /**
     * RN-M1-08: término de al menos 2 caracteres o una categoría.
     *
     * @throws CriterioInvalidoException
     */
    private function validarCriterio(CriterioBusquedaDTO $criterio): void
    {
        if ($criterio->idCategoria === null && mb_strlen((string) $criterio->termino) < self::LARGO_MINIMO_TERMINO) {
            throw new CriterioInvalidoException(
                'El término de búsqueda debe tener al menos '.self::LARGO_MINIMO_TERMINO.' caracteres, o se debe elegir una categoría.'
            );
        }
    }

    /**
     * RN-M1-01 y RN-M1-02: solo productos de comercios Activo. Si M2 no
     * responde, quedan como los entregó M3.
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
     * Si M4 no responde, quedan como Agotado (valor del adaptador).
     *
     * @param  list<ProductoResumenDTO>  $productos
     * @return list<ProductoResumenDTO>
     */
    private function combinarDisponibilidad(array $productos): array
    {
        $idsProducto = [];

        foreach ($productos as $producto) {
            $idsProducto[$producto->idProducto] = $producto->idComercio;
        }

        try {
            $disponibilidades = $this->inventario->consultarDisponibilidadProductos($idsProducto);
        } catch (DependenciaNoDisponibleException) {
            return $productos;
        }

        $estados = [];

        foreach ($disponibilidades as $disponibilidad) {
            $estados[$disponibilidad->idProducto] = $disponibilidad->estado;
        }

        return array_map(
            fn (ProductoResumenDTO $producto): ProductoResumenDTO => $this->copiar(
                $producto,
                disponibilidad: $estados[$producto->idProducto] ?? $producto->disponibilidad,
            ),
            $productos,
        );
    }

    /**
     * Un fallo al registrar no afecta la respuesta.
     */
    private function registrar(CriterioBusquedaDTO $criterio, int $cantidadResultados): void
    {
        try {
            $this->registro->registrar($criterio, $cantidadResultados, $this->tipoCategoria($criterio->idCategoria));
        } catch (Throwable $error) {
            Log::warning('M1: no se pudo registrar la búsqueda.', ['error' => $error->getMessage()]);
        }
    }

    /**
     * 'categoria' si no tiene padre en M3, 'subcategoria' si lo tiene.
     */
    private function tipoCategoria(?int $idCategoria): ?string
    {
        if ($idCategoria === null) {
            return null;
        }

        try {
            foreach ($this->catalogo->listarCategorias() as $categoria) {
                if ($categoria->idCategoria === $idCategoria) {
                    return $categoria->idCategoriaPadre === null ? 'categoria' : 'subcategoria';
                }
            }
        } catch (DependenciaNoDisponibleException) {
            return 'categoria';
        }

        return 'categoria';
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
