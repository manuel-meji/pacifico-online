<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\CriterioInvalidoException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Services\M1_Portal\DTO\CategoriaDTO;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\DTO\ResultadoBusquedaDTO;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Control de CU-002 Buscar productos y del menú de categorías. Lo usa la
 * fachada PortalPublicoService.
 */
class BusquedaService
{
    public const LARGO_MINIMO_TERMINO = 2;

    public function __construct(
        private CatalogoClient $catalogo,
        private CompletadorProductos $completador,
        private RegistroBusquedas $registro,
        private ConfiguracionMenuCategorias $configuracionMenu,
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

        $productos = $this->completador->completar($resultado->productos);
        $descartados = count($resultado->productos) - count($productos);

        $busqueda = new ResultadoBusquedaDTO(
            productos: $productos,
            totalResultados: max(0, $resultado->totalResultados - $descartados),
            pagina: $resultado->pagina,
            porPagina: $resultado->porPagina,
        );

        $this->registrar($criterio, $busqueda->totalResultados);

        return $busqueda;
    }

    /**
     * Menú de categorías (RN-M1-09), plano y en el orden en que se muestra:
     * cada principal seguida de sus subcategorías.
     * - Principal: necesita fila visible en categorias_portal y productos
     *   visibles (contando sus subcategorías). Orden por orden_visualizacion y nombre.
     * - Subcategoría: aparece si su principal aparece y tiene productos; se
     *   oculta solo con fila visible en falso. Primero las de fila, por orden;
     *   después las demás, por nombre (ordenVisualizacion null).
     * - Ícono: el de categorias_portal o, si no hay, el de M3.
     *
     * @return list<CategoriaDTO>
     *
     * @throws DependenciaNoDisponibleException ERR-006 si M3 no responde.
     */
    public function listarCategoriasPortal(): array
    {
        $categorias = $this->catalogo->listarCategorias();
        $filas = $this->configuracionMenu->filas();

        $principales = array_filter(
            $categorias,
            fn (CategoriaDTO $categoria): bool => $categoria->idCategoriaPadre === null
                && ($filas[$categoria->idCategoria]['visible'] ?? false),
        );

        $menu = [];

        foreach ($this->ordenarPorFila($principales, $filas) as $principal) {
            if (! $this->tieneProductos($principal->idCategoria)) {
                continue;
            }

            $menu[] = $this->conPresentacion($principal, $filas);

            $subcategorias = array_filter(
                $categorias,
                fn (CategoriaDTO $categoria): bool => $categoria->idCategoriaPadre === $principal->idCategoria
                    && ($filas[$categoria->idCategoria]['visible'] ?? true),
            );

            foreach ($this->ordenarPorFila($subcategorias, $filas) as $subcategoria) {
                if ($this->tieneProductos($subcategoria->idCategoria)) {
                    $menu[] = $this->conPresentacion($subcategoria, $filas);
                }
            }
        }

        return $menu;
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

    /**
     * Primero las que tienen fila, por orden y nombre; después las demás, por nombre.
     *
     * @param  array<int, CategoriaDTO>  $categorias
     * @param  array<int, array{orden: int, visible: bool, icono_url: string|null}>  $filas
     * @return list<CategoriaDTO>
     */
    private function ordenarPorFila(array $categorias, array $filas): array
    {
        $nombre = fn (CategoriaDTO $categoria): string => Str::lower(Str::ascii($categoria->nombre));

        usort($categorias, fn (CategoriaDTO $a, CategoriaDTO $b): int => [
            isset($filas[$a->idCategoria]) ? 0 : 1,
            $filas[$a->idCategoria]['orden'] ?? 0,
            $nombre($a),
        ] <=> [
            isset($filas[$b->idCategoria]) ? 0 : 1,
            $filas[$b->idCategoria]['orden'] ?? 0,
            $nombre($b),
        ]);

        return $categorias;
    }

    /**
     * M3 ya incluye las subcategorías al filtrar por una principal.
     */
    private function tieneProductos(int $idCategoria): bool
    {
        return $this->catalogo->buscarProductos(new CriterioBusquedaDTO(idCategoria: $idCategoria, porPagina: 1))->totalResultados > 0;
    }

    /**
     * @param  array<int, array{orden: int, visible: bool, icono_url: string|null}>  $filas
     */
    private function conPresentacion(CategoriaDTO $categoria, array $filas): CategoriaDTO
    {
        $fila = $filas[$categoria->idCategoria] ?? null;

        return new CategoriaDTO(
            idCategoria: $categoria->idCategoria,
            nombre: $categoria->nombre,
            idCategoriaPadre: $categoria->idCategoriaPadre,
            iconoUrl: $fila['icono_url'] ?? $categoria->iconoUrl,
            ordenVisualizacion: $fila['orden'] ?? null,
        );
    }
}
