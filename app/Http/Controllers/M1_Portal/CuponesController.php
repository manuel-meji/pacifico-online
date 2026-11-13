<?php

namespace App\Http\Controllers\M1_Portal;

use App\Http\Controllers\Controller;
use App\Services\M1_Portal\AccesoCliente;
use App\Services\M1_Portal\CuponesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cupones de descuento (contrato M1 1.5.0, SC-11). Capa delgada: llama a
 * CuponesService, que valida los datos.
 *
 * - Portal (público, 1.6.0): lista los cupones globales vigentes.
 * - Checkout (cliente con sesión): valida un cupón y responde cuánto
 *   descuenta. El cliente sale de la sesión (EstablecerCliente).
 * - Comercio (encargado_comercio): crea, lista y desactiva los suyos. El
 *   comercio sale de la sesión (EstablecerComercio), nunca del cuerpo.
 * - Administración (encargado_plataforma): crea los globales, los lista
 *   todos y desactiva cualquiera.
 *
 * Errores: 401 ERR-001, 403 ERR-002, 404 ERR-003/ERR-004, 409 ERR-M1-007
 * (código repetido), 422 ERR-005.
 */
class CuponesController extends Controller
{
    public function __construct(
        private CuponesService $cupones,
        private AccesoCliente $acceso,
    ) {}

    /**
     * GET /api/m1/v1/cupones/vigentes (listarCuponesVigentes, 1.6.0). Pública:
     * cupones globales que el cliente puede usar hoy.
     */
    public function vigentes(): JsonResponse
    {
        return response()->json(['cupones' => $this->cupones->listarGlobalesVigentes()]);
    }

    /**
     * POST /api/m1/v1/cupones/validar (validarCupon). 200 ResultadoCupon,
     * también cuando el cupón no aplica (valido=false).
     */
    public function validar(Request $request): JsonResponse
    {
        $idCliente = $this->acceso->idClienteDeSesion($request->user());

        return $this->sinCache(response()->json($this->cupones->validarDesdeCheckout($request->json()->all(), $idCliente)));
    }

    /**
     * POST /api/m1/v1/comercio/cupones (crearCuponComercio). 201.
     */
    public function crearDelComercio(Request $request): JsonResponse
    {
        return response()->json($this->cupones->crearDelComercio($request->json()->all(), (int) $request->user()->id_usuario), 201);
    }

    /**
     * GET /api/m1/v1/comercio/cupones (listarCuponesComercio).
     */
    public function delComercio(Request $request): JsonResponse
    {
        return $this->sinCache(response()->json($this->cupones->listarDelComercio($this->texto($request, 'estado'))));
    }

    /**
     * POST /api/m1/v1/comercio/cupones/{id_cupon}/desactivar (desactivarCuponComercio) y
     * POST /api/m1/v1/administracion/cupones/{id_cupon}/desactivar (desactivarCuponAdministracion).
     */
    public function desactivar(int $idCupon): JsonResponse
    {
        return response()->json($this->cupones->desactivar($idCupon));
    }

    /**
     * POST /api/m1/v1/administracion/cupones (crearCuponGlobal). 201.
     */
    public function crearGlobal(Request $request): JsonResponse
    {
        return response()->json($this->cupones->crearGlobal($request->json()->all(), (int) $request->user()->id_usuario), 201);
    }

    /**
     * GET /api/m1/v1/administracion/cupones (listarCuponesAdministracion).
     * ambito=global deja solo los cupones de la plataforma.
     */
    public function administracion(Request $request): JsonResponse
    {
        $idComercio = filter_var($request->query('id_comercio'), FILTER_VALIDATE_INT);

        return $this->sinCache(response()->json($this->cupones->listarAdministracion(
            $this->texto($request, 'estado'),
            $idComercio === false ? null : $idComercio,
            $this->texto($request, 'ambito') === 'global',
        )));
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
