<?php

namespace App\Services\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\Exceptions\DependenciaNoDisponibleException;
use App\Contracts\M1_Portal\Exceptions\RecursoNoEncontradoException;
use App\Services\M1_Portal\DTO\ComercioDTO;
use App\Services\M1_Portal\DTO\CriterioBusquedaDTO;
use App\Services\M1_Portal\DTO\EstadoComercio;
use App\Services\M1_Portal\DTO\ListadoComerciosDTO;
use App\Services\M1_Portal\DTO\MicrositioDTO;
use App\Services\M1_Portal\DTO\ResultadoBusquedaDTO;
use App\Services\M1_Portal\Integraciones\CatalogoClient;
use App\Services\M1_Portal\Integraciones\ComercioClient;
use Illuminate\Support\Facades\Log;

/**
 * Control de CU-009 Ver micrositio de un comercio y de la página de comercios
 * del portal. Lo usa la fachada PortalPublicoService.
 */
class MicrositioService
{
    /** Máximo de por_pagina del contrato (GET /comercios y /micrositio). */
    public const MAXIMO_POR_PAGINA = 50;

    private const NO_DISPONIBLE = 'El comercio no existe o no está disponible en el portal.';

    public function __construct(
        private ComercioClient $comercios,
        private CatalogoClient $catalogo,
        private CompletadorProductos $completador,
    ) {}

    /**
     * Página de comercios habilitados (RN-M1-01: solo los Activo).
     *
     * @throws DatosInvalidosException ERR-005 si pagina o por_pagina está fuera de rango.
     * @throws DependenciaNoDisponibleException ERR-006 si M2 no responde.
     */
    public function listarComercios(int $pagina, int $porPagina): ListadoComerciosDTO
    {
        $this->validarPaginacion($pagina, $porPagina);

        $listado = $this->comercios->listarComerciosHabilitados($pagina, $porPagina);
        $activos = array_values(array_filter(
            $listado->comercios,
            fn (ComercioDTO $comercio): bool => $comercio->estado === EstadoComercio::Activo,
        ));

        return new ListadoComerciosDTO(
            comercios: $activos,
            total: max(0, $listado->total - (count($listado->comercios) - count($activos))),
            pagina: $listado->pagina,
            porPagina: $listado->porPagina,
        );
    }

    /**
     * Micrositio: información pública del comercio (M2) y sus productos (M3),
     * con la disponibilidad de M4.
     * - A1: el comercio no existe o no está Activo → ERR-004 (RN-M1-01).
     * - A2: sin productos publicados → lista de productos vacía.
     * - A4: M2 no responde → ERR-006.
     * - M3 no responde: se muestra el comercio con la lista de productos vacía
     *   y productosDisponibles en false, para que el portal muestre un aviso.
     *
     * @throws RecursoNoEncontradoException ERR-004.
     * @throws DatosInvalidosException ERR-005 si pagina o por_pagina está fuera de rango.
     * @throws DependenciaNoDisponibleException ERR-006 si M2 no responde.
     */
    public function obtenerMicrositio(int $idComercio, int $pagina, int $porPagina): MicrositioDTO
    {
        $this->validarPaginacion($pagina, $porPagina);
        $comercio = $this->comercioActivo($idComercio);

        try {
            $productos = $this->productos($idComercio, $pagina, $porPagina);
        } catch (DependenciaNoDisponibleException) {
            Log::warning('M1 micrositio sin productos: M3 no respondió.', ['id_comercio' => $idComercio]);

            return new MicrositioDTO($comercio, new ResultadoBusquedaDTO([], 0, $pagina, $porPagina), productosDisponibles: false);
        }

        return new MicrositioDTO($comercio, $productos);
    }

    /**
     * @throws RecursoNoEncontradoException
     */
    private function comercioActivo(int $idComercio): ComercioDTO
    {
        if ($idComercio < 1) {
            throw new RecursoNoEncontradoException(self::NO_DISPONIBLE);
        }

        try {
            $comercio = $this->comercios->obtenerDatosComercioPublico($idComercio);
        } catch (RecursoNoEncontradoException $error) {
            throw new RecursoNoEncontradoException(self::NO_DISPONIBLE, $error);
        }

        if ($comercio->estado !== EstadoComercio::Activo) {
            throw new RecursoNoEncontradoException(self::NO_DISPONIBLE);
        }

        return $comercio;
    }

    /**
     * Productos del comercio (M3, filtro id_comercio), completados con
     * CompletadorProductos.
     *
     * @throws DependenciaNoDisponibleException si M3 no responde.
     */
    private function productos(int $idComercio, int $pagina, int $porPagina): ResultadoBusquedaDTO
    {
        $resultado = $this->catalogo->buscarProductos(new CriterioBusquedaDTO(
            idComercio: $idComercio,
            pagina: $pagina,
            porPagina: $porPagina,
        ));

        if ($resultado->productos === []) {
            return $resultado;
        }

        $productos = $this->completador->completar($resultado->productos);

        return new ResultadoBusquedaDTO(
            productos: $productos,
            totalResultados: max(0, $resultado->totalResultados - (count($resultado->productos) - count($productos))),
            pagina: $resultado->pagina,
            porPagina: $resultado->porPagina,
        );
    }

    /**
     * Contrato 1.3.0: pagina >= 1 y por_pagina entre 1 y 50.
     *
     * @throws DatosInvalidosException
     */
    private function validarPaginacion(int $pagina, int $porPagina): void
    {
        $errores = [];

        if ($pagina < 1) {
            $errores['pagina'] = 'Debe ser mayor o igual a 1.';
        }

        if ($porPagina < 1 || $porPagina > self::MAXIMO_POR_PAGINA) {
            $errores['por_pagina'] = 'Debe estar entre 1 y '.self::MAXIMO_POR_PAGINA.'.';
        }

        if ($errores !== []) {
            throw new DatosInvalidosException($errores);
        }
    }
}
