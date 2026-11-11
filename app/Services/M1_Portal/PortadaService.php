<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Services\M1_Portal\DTO\ComercioDTO;
use App\Services\M1_Portal\DTO\DestacadosDTO;
use App\Services\M1_Portal\DTO\Disponibilidad;
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
    /** La portada muestra hasta 12 destacados (contrato M1 1.4.0). */
    public const LIMITE_POR_DEFECTO = 12;

    public const LIMITE_MAXIMO = 12;

    /** Destacados ya armados, por límite y turno de rotación (RNF-M1-06). */
    public const CLAVE_DESTACADOS = 'portada.destacados.%d.%d';

    public function __construct(
        private SeleccionDestacados $seleccion,
        private CatalogoClient $catalogo,
        private ComercioClient $comercios,
        private CompletadorProductos $completador,
        private ReglasDestacados $reglas,
        private CachePortal $cache,
    ) {}

    /**
     * Destacados vigentes (RN-M1-11), en el orden de posicion, solo de
     * productos publicados y comercios activos (RN-M1-05, RN-M1-06).
     * - Productos: como máximo 3 por comercio; se ocultan los agotados (y
     *   reaparecen solos al volver a tener existencias); si quedan más que
     *   el límite, se rotan por turnos de 15 minutos (ReglasDestacados).
     * - Si M2 o M3 no responden, esa sección viene vacía y se agrega a
     *   seccionesNoDisponibles; la portada no falla (RNF-M1-05).
     * - limite fuera de 1 a 12 se ajusta al más cercano (el contrato solo
     *   declara la respuesta 200).
     * - El resultado completo se guarda en CachePortal; uno con una sección
     *   no disponible no se guarda, para que se recupere apenas vuelva M2 o M3.
     */
    public function listarDestacados(int $limite): DestacadosDTO
    {
        $limite = min(self::LIMITE_MAXIMO, max(1, $limite));
        $ahora = Carbon::now();
        $clave = self::clave($limite, $this->reglas->turno($ahora));
        $guardado = $this->cache->obtener($clave);

        if ($guardado instanceof DestacadosDTO) {
            return $guardado;
        }

        $noDisponibles = [];

        try {
            $comercios = $this->comercios($limite);
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
     * Clave de caché de la portada para un límite y un turno de rotación.
     */
    public static function clave(int $limite, int $turno): string
    {
        return sprintf(self::CLAVE_DESTACADOS, $limite, $turno);
    }

    /**
     * Productos vigentes que M3 todavía publica (M3 omite los que el comercio
     * desactivó), con el comercio (M2) y la disponibilidad (M4):
     * 1. Se descartan los de comercios no activos (suspendidos).
     * 2. Se dejan los primeros 3 de cada comercio, contando los agotados,
     *    porque siguen activos aunque no se muestren.
     * 3. Se ocultan los agotados. Si M4 no respondió, la disponibilidad no es
     *    cierta y no se oculta ninguno (salen marcados como Agotado).
     * 4. Se rotan si quedan más que el límite. Se recorta después de filtrar,
     *    así los omitidos no dejan huecos.
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

        [$productos, $inventarioRespondio] = $this->completador->completarConInventario($this->catalogo->buscarPorIds($ids));
        $productos = $this->reglas->limitarPorComercio($productos);

        if ($inventarioRespondio) {
            $productos = array_values(array_filter(
                $productos,
                fn (ProductoResumenDTO $producto): bool => $producto->disponibilidad === Disponibilidad::Disponible,
            ));
        }

        return $this->reglas->rotar($productos, $limite, $ahora);
    }

    /**
     * Comercios destacados que decide M2 (contrato 1.4.0): los que tienen una
     * publicidad vigente aprobada en el panel de M2 (el comercio la solicita y
     * la plataforma la aprueba, o la plataforma destaca directamente), en el
     * orden de M2 (la publicidad más reciente primero). Solo los Activo.
     *
     * Antes salían de la tabla comercios_destacados de M1, que nadie llenaba
     * desde una pantalla; así lo que se aprueba en M2 se ve en la portada.
     *
     * @return list<ComercioDTO>
     *
     * @throws DependenciaNoDisponibleException si M2 no responde.
     */
    private function comercios(int $limite): array
    {
        $destacados = $this->comercios->listarComerciosHabilitados(1, $limite, destacado: true)->comercios;

        $activos = array_filter(
            $destacados,
            fn (ComercioDTO $comercio): bool => $comercio->estado === EstadoComercio::Activo,
        );

        return array_slice(array_values($activos), 0, $limite);
    }
}
