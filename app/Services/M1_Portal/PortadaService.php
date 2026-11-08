<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Services\M1_Portal\DTO\ComercioDTO;
use App\Services\M1_Portal\DTO\DestacadosDTO;
use App\Services\M1_Portal\DTO\EstadoComercio;
use App\Services\M1_Portal\DTO\ProductoResumenDTO;
use App\Services\M1_Portal\Integraciones\CachePortal;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use App\Services\M1_Portal\Integraciones\ComercioClient;
use Illuminate\Support\Carbon;

/**
 * Control de la página principal: comercios y productos destacados. Lo usa la
 * fachada PortalPublicoService.
 */
class PortadaService
{
    public const LIMITE_POR_DEFECTO = 8;

    public const LIMITE_MAXIMO = 24;

    /** Destacados ya armados, por límite (RNF-M1-06). */
    public const CLAVE_DESTACADOS = 'portada.destacados.%d';

    public function __construct(
        private SeleccionDestacados $seleccion,
        private CatalogoClient $catalogo,
        private ComercioClient $comercios,
        private CompletadorProductos $completador,
        private CachePortal $cache,
    ) {}

    /**
     * Destacados vigentes (RN-M1-11), en el orden de posicion, solo de
     * productos publicados y comercios activos (RN-M1-05, RN-M1-06).
     * - Si M2 o M3 no responden, esa sección viene vacía y se agrega a
     *   seccionesNoDisponibles; la portada no falla (RNF-M1-05).
     * - limite fuera de 1 a 24 se ajusta al más cercano (el contrato solo
     *   declara la respuesta 200).
     * - El resultado completo se guarda en CachePortal; uno con una sección
     *   no disponible no se guarda, para que se recupere apenas vuelva M2 o M3.
     */
    public function listarDestacados(int $limite): DestacadosDTO
    {
        $limite = min(self::LIMITE_MAXIMO, max(1, $limite));
        $clave = sprintf(self::CLAVE_DESTACADOS, $limite);
        $guardado = $this->cache->obtener($clave);

        if ($guardado instanceof DestacadosDTO) {
            return $guardado;
        }

        $ahora = Carbon::now();
        $noDisponibles = [];

        try {
            $comercios = $this->comercios($ahora, $limite);
        } catch (DependenciaNoDisponibleException) {
            $comercios = [];
            $noDisponibles[] = 'comercios';
        }

        try {
            $productos = $this->productos($ahora, $limite);
        } catch (DependenciaNoDisponibleException) {
            $productos = [];
            $noDisponibles[] = 'productos';
        }

        $destacados = new DestacadosDTO($productos, $comercios, $noDisponibles);

        if ($noDisponibles === []) {
            $this->cache->guardar($clave, $destacados);
        }

        return $destacados;
    }

    /**
     * Productos vigentes que M3 todavía publica, con el comercio (M2) y la
     * disponibilidad (M4). Se recorta al límite después de filtrar, así los
     * que M3 o M2 omiten no dejan huecos.
     *
     * @return list<ProductoResumenDTO>
     *
     * @throws DependenciaNoDisponibleException si M3 no responde.
     */
    private function productos(Carbon $ahora, int $limite): array
    {
        $ids = $this->seleccion->productosVigentes($ahora);

        if ($ids === []) {
            return [];
        }

        return array_slice($this->completador->completar($this->catalogo->buscarPorIds($ids)), 0, $limite);
    }

    /**
     * Comercios vigentes que M2 devuelve como Activo.
     *
     * @return list<ComercioDTO>
     *
     * @throws DependenciaNoDisponibleException si M2 no responde.
     */
    private function comercios(Carbon $ahora, int $limite): array
    {
        $ids = $this->seleccion->comerciosVigentes($ahora);

        if ($ids === []) {
            return [];
        }

        $activos = array_filter(
            $this->comercios->obtenerResumenComercios($ids),
            fn (ComercioDTO $comercio): bool => $comercio->estado === EstadoComercio::Activo,
        );

        return array_slice(array_values($activos), 0, $limite);
    }
}
