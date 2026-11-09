<?php

namespace App\Http\Controllers\M1_Portal;

use App\Contracts\M1_Portal\Exceptions\DatosInvalidosException;
use App\Http\Controllers\Controller;
use App\Services\M1_Portal\NoticiasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Noticias de la plataforma (contrato M1 1.4.0, SC-08). Capa delgada: llama a
 * NoticiasService, que valida los datos.
 *
 * - Portal: noticias publicadas y vigentes, públicas.
 * - Administración (encargado_plataforma): crea, edita, publica, archiva y
 *   lista todas. Los comercios no publican noticias (RequiereRol en la ruta).
 *
 * Errores: 401 ERR-001, 403 ERR-002, 404 ERR-004, 409 ERR-M1-006 (archivada o
 * ya publicada), 422 ERR-005.
 */
class NoticiasController extends Controller
{
    private const MENSAJES = [
        'integer' => 'Debe ser un número entero.',
        'min' => 'Debe ser mayor o igual a :min.',
        'between' => 'Debe estar entre :min y :max.',
    ];

    public function __construct(private NoticiasService $noticias) {}

    /**
     * GET /api/m1/v1/noticias (listarNoticias).
     *
     * @throws DatosInvalidosException ERR-005 si pagina o por_pagina no es válido.
     */
    public function listar(Request $request): JsonResponse
    {
        $validador = Validator::make($request->query(), [
            'pagina' => 'nullable|integer|min:1',
            'por_pagina' => 'nullable|integer|between:1,'.NoticiasService::MAXIMO_POR_PAGINA,
        ], self::MENSAJES);

        if ($validador->fails()) {
            throw new DatosInvalidosException(array_map(fn (array $mensajes): string => $mensajes[0], $validador->errors()->messages()));
        }

        $datos = $validador->validated();

        return response()->json($this->noticias->listarPublicadas(
            (int) ($datos['pagina'] ?? 1),
            (int) ($datos['por_pagina'] ?? NoticiasService::POR_PAGINA_POR_DEFECTO),
        ));
    }

    /**
     * GET /api/m1/v1/noticias/{slug} (obtenerNoticia).
     */
    public function mostrar(string $slug): JsonResponse
    {
        return response()->json($this->noticias->obtenerPublicada($slug));
    }

    /**
     * POST /api/m1/v1/administracion/noticias (crearNoticia). 201.
     */
    public function crear(Request $request): JsonResponse
    {
        return response()->json($this->noticias->crear($request->json()->all(), (int) $request->user()->id_usuario), 201);
    }

    /**
     * GET /api/m1/v1/administracion/noticias (listarNoticiasAdministracion).
     */
    public function administracion(Request $request): JsonResponse
    {
        $estado = $request->query('estado');

        return $this->sinCache(response()->json($this->noticias->listarAdministracion(is_string($estado) && $estado !== '' ? $estado : null)));
    }

    /**
     * PUT /api/m1/v1/administracion/noticias/{id_noticia} (actualizarNoticia).
     */
    public function actualizar(Request $request, int $idNoticia): JsonResponse
    {
        return response()->json($this->noticias->actualizar($idNoticia, $request->json()->all()));
    }

    /**
     * POST /api/m1/v1/administracion/noticias/{id_noticia}/publicar (publicarNoticia).
     */
    public function publicar(Request $request, int $idNoticia): JsonResponse
    {
        return response()->json($this->noticias->publicar($idNoticia, $request->json()->all()));
    }

    /**
     * POST /api/m1/v1/administracion/noticias/{id_noticia}/archivar (archivarNoticia).
     */
    public function archivar(int $idNoticia): JsonResponse
    {
        return response()->json($this->noticias->archivar($idNoticia));
    }

    private function sinCache(JsonResponse $respuesta): JsonResponse
    {
        return $respuesta->header('Cache-Control', 'no-store');
    }
}
