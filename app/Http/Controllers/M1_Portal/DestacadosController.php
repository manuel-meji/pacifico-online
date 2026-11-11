<?php

namespace App\Http\Controllers\M1_Portal;

use App\Http\Controllers\Controller;
use App\Services\M1_Portal\DestacadosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Solicitud, aprobación y cobro de productos destacados (contrato M1 1.4.0,
 * SC-08). Capa delgada: llama a DestacadosService, que valida los datos.
 *
 * - Tarifas: públicas para consulta; solo la administración las define.
 * - Comercio (encargado_comercio): solicita y consulta sus solicitudes. El
 *   comercio sale de la sesión (EstablecerComercio), nunca del cuerpo.
 * - Administración (encargado_plataforma): resuelve solicitudes y destaca
 *   productos por su cuenta, sin costo.
 *
 * Errores: 401 ERR-001, 403 ERR-002 (rol), 404 ERR-003/ERR-004, 409 ERR-M1-002
 * (máximo 3 por comercio) o ERR-M1-003 (conflicto), 422 ERR-005, 503 ERR-006.
 */
class DestacadosController extends Controller
{
    public function __construct(private DestacadosService $destacados) {}

    /**
     * GET /api/m1/v1/destacados/tarifas (listarTarifasDestacado).
     */
    public function tarifas(): JsonResponse
    {
        return response()->json($this->destacados->listarTarifas());
    }

    /**
     * PUT /api/m1/v1/administracion/destacados/tarifas/{periodo_dias} (definirTarifaDestacado).
     */
    public function definirTarifa(Request $request, int $periodoDias): JsonResponse
    {
        return response()->json($this->destacados->definirTarifa($periodoDias, $request->json()->all(), $this->idUsuario($request)));
    }

    /**
     * POST /api/m1/v1/comercio/destacados/solicitudes (solicitarDestacado). 201.
     */
    public function solicitar(Request $request): JsonResponse
    {
        return response()->json($this->destacados->solicitar($request->json()->all(), $this->idUsuario($request)), 201);
    }

    /**
     * GET /api/m1/v1/comercio/destacados/solicitudes (listarSolicitudesDestacado).
     */
    public function solicitudesDelComercio(Request $request): JsonResponse
    {
        return $this->sinCache(response()->json($this->destacados->listarSolicitudesDelComercio($this->texto($request, 'estado'))));
    }

    /**
     * GET /api/m1/v1/administracion/destacados/solicitudes (listarSolicitudesDestacadoAdministracion).
     */
    public function solicitudes(Request $request): JsonResponse
    {
        $idComercio = filter_var($request->query('id_comercio'), FILTER_VALIDATE_INT);

        return $this->sinCache(response()->json($this->destacados->listarSolicitudes(
            $this->texto($request, 'estado'),
            $idComercio === false ? null : $idComercio,
        )));
    }

    /**
     * POST /api/m1/v1/administracion/destacados/solicitudes/{id}/aprobar (aprobarSolicitudDestacado).
     */
    public function aprobar(Request $request, int $idSolicitud): JsonResponse
    {
        return response()->json($this->destacados->aprobar($idSolicitud, $this->idUsuario($request)));
    }

    /**
     * POST /api/m1/v1/administracion/destacados/solicitudes/{id}/rechazar (rechazarSolicitudDestacado).
     */
    public function rechazar(Request $request, int $idSolicitud): JsonResponse
    {
        return response()->json($this->destacados->rechazar($idSolicitud, $request->json()->all(), $this->idUsuario($request)));
    }

    /**
     * POST /api/m1/v1/administracion/destacados (destacarProductoPlataforma). 201.
     */
    public function destacarPorPlataforma(Request $request): JsonResponse
    {
        return response()->json($this->destacados->destacarPorPlataforma($request->json()->all()), 201);
    }

    private function idUsuario(Request $request): int
    {
        return (int) $request->user()->id_usuario;
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
