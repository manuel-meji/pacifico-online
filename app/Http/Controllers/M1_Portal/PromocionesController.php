<?php

namespace App\Http\Controllers\M1_Portal;

use App\Http\Controllers\Controller;
use App\Services\M1_Portal\PromocionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Promociones de los comercios (contrato M1 1.4.0, SC-08). Capa delgada:
 * llama a PromocionesService, que valida los datos.
 *
 * - Portal: promociones vigentes, pública.
 * - Comercio (encargado_comercio): crea y gestiona las suyas. El comercio sale
 *   de la sesión (EstablecerComercio), nunca del cuerpo.
 * - Administración (encargado_plataforma): ordena, oculta y vuelve a mostrar.
 *
 * Errores: 401 ERR-001, 403 ERR-002, 404 ERR-003/ERR-004, 409 ERR-M1-004
 * (traslape) o ERR-M1-005 (no modificable), 422 ERR-005, 503 ERR-006.
 */
class PromocionesController extends Controller
{
    public function __construct(private PromocionesService $promociones) {}

    /**
     * GET /api/m1/v1/promociones (listarPromocionesVigentes).
     */
    public function vigentes(): JsonResponse
    {
        return response()->json($this->promociones->listarVigentes());
    }

    /**
     * POST /api/m1/v1/comercio/promociones (crearPromocion). 201.
     */
    public function crear(Request $request): JsonResponse
    {
        return response()->json($this->promociones->crear($request->json()->all(), (int) $request->user()->id_usuario), 201);
    }

    /**
     * GET /api/m1/v1/comercio/promociones (listarPromocionesComercio).
     */
    public function delComercio(Request $request): JsonResponse
    {
        return $this->sinCache(response()->json($this->promociones->listarDelComercio($this->texto($request, 'estado'))));
    }

    /**
     * PUT /api/m1/v1/comercio/promociones/{id_promocion} (actualizarPromocion).
     */
    public function actualizar(Request $request, int $idPromocion): JsonResponse
    {
        return response()->json($this->promociones->actualizar($idPromocion, $request->json()->all()));
    }

    /**
     * POST /api/m1/v1/comercio/promociones/{id_promocion}/desactivar (desactivarPromocion).
     */
    public function desactivar(int $idPromocion): JsonResponse
    {
        return response()->json($this->promociones->desactivar($idPromocion));
    }

    /**
     * GET /api/m1/v1/administracion/promociones (listarPromocionesAdministracion).
     */
    public function administracion(Request $request): JsonResponse
    {
        $idComercio = filter_var($request->query('id_comercio'), FILTER_VALIDATE_INT);

        return $this->sinCache(response()->json($this->promociones->listarAdministracion(
            $this->texto($request, 'estado'),
            $idComercio === false ? null : $idComercio,
        )));
    }

    /**
     * PUT /api/m1/v1/administracion/promociones/{id_promocion}/orden (ordenarPromocion).
     */
    public function ordenar(Request $request, int $idPromocion): JsonResponse
    {
        return response()->json($this->promociones->ordenar($idPromocion, $request->json()->all()));
    }

    /**
     * POST /api/m1/v1/administracion/promociones/{id_promocion}/ocultar (ocultarPromocion).
     */
    public function ocultar(Request $request, int $idPromocion): JsonResponse
    {
        return response()->json($this->promociones->ocultar($idPromocion, $request->json()->all()));
    }

    /**
     * POST /api/m1/v1/administracion/promociones/{id_promocion}/mostrar (mostrarPromocion).
     */
    public function mostrar(int $idPromocion): JsonResponse
    {
        return response()->json($this->promociones->mostrar($idPromocion));
    }

    private function texto(Request $request, string $campo): ?string
    {
        $valor = $request->query($campo);

        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    private function sinCache(JsonResponse $respuesta): JsonResponse
    {
        return $respuesta->header('Cache-Control', 'no-store');
    }
}
