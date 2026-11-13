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
use Illuminate\Support\Str;

/**
 * Control de CU-009 Ver micrositio de un comercio y de la página de comercios
 * del portal. Lo usa la fachada PortalPublicoService.
 */
class MicrositioService
{
    /** Máximo de por_pagina del contrato (GET /comercios y /micrositio). */
    public const MAXIMO_POR_PAGINA = 50;

    /** Largo del término de búsqueda de comercios (contrato 1.6.0, igual que RN-M1-08). */
    public const LARGO_MINIMO_TERMINO = 2;

    public const LARGO_MAXIMO_TERMINO = 100;

    /** Tope de páginas de 50 que se piden a M2 al buscar comercios por nombre (1000 comercios). */
    private const MAXIMO_PAGINAS_M2 = 20;

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
     * Búsqueda de comercios por nombre (contrato 1.6.0, parámetro q de GET /comercios).
     *
     * M2 no busca por nombre, así que M1 recorre su listado de habilitados en páginas de 50
     * (hasta MAXIMO_PAGINAS_M2), deja los Activo cuyo nombre comercial contiene el término
     * (sin distinguir mayúsculas ni tildes) y pagina el resultado. Primero van los que
     * empiezan con el término y luego el resto, en orden alfabético.
     *
     * @throws DatosInvalidosException ERR-005 si el término o la paginación no son válidos.
     * @throws DependenciaNoDisponibleException ERR-006 si M2 no responde.
     */
    public function buscarComercios(string $termino, int $pagina, int $porPagina): ListadoComerciosDTO
    {
        $this->validarPaginacion($pagina, $porPagina);
        $termino = trim($termino);
        $largo = mb_strlen($termino);

        if ($largo < self::LARGO_MINIMO_TERMINO || $largo > self::LARGO_MAXIMO_TERMINO) {
            throw new DatosInvalidosException([
                'q' => 'Debe tener entre '.self::LARGO_MINIMO_TERMINO.' y '.self::LARGO_MAXIMO_TERMINO.' caracteres.',
            ]);
        }

        $buscado = self::normalizar($termino);
        $encontrados = [];

        for ($paginaM2 = 1; $paginaM2 <= self::MAXIMO_PAGINAS_M2; $paginaM2++) {
            $listado = $this->comercios->listarComerciosHabilitados($paginaM2, self::MAXIMO_POR_PAGINA);

            foreach ($listado->comercios as $comercio) {
                if ($comercio->estado === EstadoComercio::Activo && str_contains(self::normalizar($comercio->nombreComercial), $buscado)) {
                    $encontrados[$comercio->idComercio] = $comercio;
                }
            }

            if (count($listado->comercios) < self::MAXIMO_POR_PAGINA || $paginaM2 * self::MAXIMO_POR_PAGINA >= $listado->total) {
                break;
            }
        }

        $encontrados = array_values($encontrados);
        usort($encontrados, function (ComercioDTO $a, ComercioDTO $b) use ($buscado): int {
            $nombreA = self::normalizar($a->nombreComercial);
            $nombreB = self::normalizar($b->nombreComercial);

            return [! str_starts_with($nombreA, $buscado), $nombreA] <=> [! str_starts_with($nombreB, $buscado), $nombreB];
        });

        return new ListadoComerciosDTO(
            comercios: array_slice($encontrados, ($pagina - 1) * $porPagina, $porPagina),
            total: count($encontrados),
            pagina: $pagina,
            porPagina: $porPagina,
        );
    }

    /** Texto en minúsculas y sin tildes, para comparar nombres. */
    private static function normalizar(string $texto): string
    {
        return mb_strtolower(Str::ascii($texto));
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
