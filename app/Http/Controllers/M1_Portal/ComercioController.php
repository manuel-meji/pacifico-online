<?php

namespace App\Http\Controllers\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Http\Controllers\Controller;
use App\Services\M1_Portal\MicrositioService;
use App\Services\M1_Portal\PortalPublicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Página de comercios y micrositio del portal. Capa delgada: valida la
 * paginación y llama al contrato de M1. Los errores los convierte el
 * manejador del núcleo.
 */
class ComercioController extends Controller
{
    private const MENSAJES = [
        'integer' => 'Debe ser un número entero.',
        'min' => 'Debe ser mayor o igual a :min.',
        'between' => 'Debe estar entre :min y :max.',
    ];

    public function __construct(private PortalPublicoContract $portal) {}

    /**
     * GET /api/m1/v1/comercios (listarComercios). Desde 1.6.0 acepta q para buscar por
     * nombre comercial; esa búsqueda la resuelve PortalPublicoService, porque no forma
     * parte de la interfaz PHP.
     *
     * @throws DatosInvalidosException ERR-005 con el detalle por campo.
     */
    public function listar(Request $request, PortalPublicoService $servicio): JsonResponse
    {
        [$pagina, $porPagina] = $this->paginacion($request);
        $termino = $request->query('q');

        if (is_string($termino) && trim($termino) !== '') {
            return response()->json($servicio->buscarComercios($termino, $pagina, $porPagina));
        }

        return response()->json($this->portal->listarComercios($pagina, $porPagina));
    }

    /**
     * GET /api/m1/v1/comercios/{id_comercio}/micrositio (CU-009 Ver micrositio).
     *
     * @throws DatosInvalidosException ERR-005 con el detalle por campo.
     */
    public function micrositio(Request $request, int $idComercio): JsonResponse
    {
        [$pagina, $porPagina] = $this->paginacion($request);

        return response()->json($this->portal->obtenerMicrositio($idComercio, $pagina, $porPagina));
    }

    /**
     * Contrato 1.3.0: pagina >= 1 y por_pagina de 1 a 50; si no vienen, 1 y 20.
     *
     * @return array{int, int}
     *
     * @throws DatosInvalidosException
     */
    private function paginacion(Request $request): array
    {
        $validador = Validator::make($request->query(), [
            'pagina' => 'nullable|integer|min:1',
            'por_pagina' => 'nullable|integer|between:1,'.MicrositioService::MAXIMO_POR_PAGINA,
        ], self::MENSAJES);

        if ($validador->fails()) {
            throw new DatosInvalidosException(
                array_map(fn (array $mensajes): string => $mensajes[0], $validador->errors()->messages())
            );
        }

        $datos = $validador->validated();

        return [(int) ($datos['pagina'] ?? 1), (int) ($datos['por_pagina'] ?? 20)];
    }
}
