<?php

namespace App\Http\Controllers\M1_Portal;

use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Http\Controllers\Controller;
use App\Services\M1_Portal\AccesoCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Direcciones de envío del cliente (tarea 17 del plan del PC4). Capa delgada:
 * llama al contrato de M1, que valida los datos (esquema DatosDireccionCliente).
 *
 * Todas las rutas requieren sesión y aplican RN-M1-16 con AccesoCliente: solo
 * la propia persona ve y edita sus direcciones. Un cliente ajeno responde
 * ERR-003 (404); una dirección que no existe o es de otro cliente, ERR-004 (404).
 */
class DireccionClienteController extends Controller
{
    public function __construct(
        private PortalPublicoContract $portal,
        private AccesoCliente $acceso,
    ) {}

    /**
     * GET /api/m1/v1/clientes/{id_cliente}/direcciones (listarDireccionesCliente).
     * La principal viene primero.
     */
    public function listar(Request $request, int $idCliente): JsonResponse
    {
        $this->acceso->exigirPropio($request->user(), $idCliente);

        return response()->json($this->portal->listarDireccionesCliente($idCliente))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * POST /api/m1/v1/clientes/{id_cliente}/direcciones (agregarDireccionCliente).
     * Si se marca como principal, la anterior deja de serlo.
     *
     * 201 DireccionCliente · 401 ERR-001 · 404 ERR-003 · 422 ERR-005.
     */
    public function agregar(Request $request, int $idCliente): JsonResponse
    {
        $this->acceso->exigirPropio($request->user(), $idCliente);

        return response()->json($this->portal->agregarDireccionCliente($idCliente, $request->json()->all()), 201);
    }

    /**
     * PUT /api/m1/v1/clientes/{id_cliente}/direcciones/{id_direccion_cliente}
     * (actualizarDireccionCliente).
     *
     * 200 DireccionCliente · 401 ERR-001 · 404 ERR-003 o ERR-004 · 422 ERR-005.
     */
    public function actualizar(Request $request, int $idCliente, int $idDireccionCliente): JsonResponse
    {
        $this->acceso->exigirPropio($request->user(), $idCliente);

        return response()->json(
            $this->portal->actualizarDireccionCliente($idCliente, $idDireccionCliente, $request->json()->all()),
        );
    }
}
