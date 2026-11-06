<?php

namespace App\Http\Controllers\M1_Portal;

use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Http\Controllers\Controller;
use App\Services\M1_Portal\AccesoCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clientes del portal (CU-010, RF-010). Capa delgada: llama al contrato de M1,
 * que valida los datos. Los errores (ERR-001, ERR-003, ERR-005, ERR-M1-001)
 * los convierte el manejador del núcleo al formato común.
 *
 * Las rutas con sesión aplican RN-M1-16 con AccesoCliente: cada persona solo
 * ve y edita sus propios datos; cualquier otro caso responde ERR-003 (404).
 */
class ClienteController extends Controller
{
    public function __construct(
        private PortalPublicoContract $portal,
        private AccesoCliente $acceso,
    ) {}

    /**
     * POST /api/m1/v1/clientes (registrarCliente). Ruta pública: autorregistro
     * de la persona compradora. Cuerpo según RegistroCliente 1.2.0.
     *
     * 201 {"id_cliente": int} · 409 ERR-M1-001 · 422 ERR-005.
     */
    public function registrar(Request $request): JsonResponse
    {
        $idCliente = $this->portal->registrarCliente($request->json()->all());

        return response()->json(['id_cliente' => $idCliente], 201);
    }

    /**
     * GET /api/m1/v1/clientes/sesion (obtenerClienteSesion). Datos de la
     * persona con sesión, para que el frontend conozca su id_cliente.
     *
     * 200 Cliente · 401 ERR-001 · 404 ERR-003 (la cuenta no es de un cliente).
     */
    public function sesion(Request $request): JsonResponse
    {
        $idCliente = $this->acceso->idClienteDeSesion($request->user());

        return $this->sinCache(response()->json($this->portal->obtenerCliente($idCliente)));
    }

    /**
     * GET /api/m1/v1/clientes/{id_cliente} (obtenerCliente).
     *
     * 200 Cliente · 401 ERR-001 · 404 ERR-003 (no es la persona con sesión).
     */
    public function mostrar(Request $request, int $idCliente): JsonResponse
    {
        $this->acceso->exigirPropio($request->user(), $idCliente);

        return $this->sinCache(response()->json($this->portal->obtenerCliente($idCliente)));
    }

    /**
     * PUT /api/m1/v1/clientes/{id_cliente} (actualizarCliente). Cuerpo según
     * DatosCliente 1.2.0.
     *
     * 200 {"id_cliente": int} · 401 ERR-001 · 404 ERR-003 · 409 ERR-M1-001 · 422 ERR-005.
     */
    public function actualizar(Request $request, int $idCliente): JsonResponse
    {
        $this->acceso->exigirPropio($request->user(), $idCliente);

        return response()->json(['id_cliente' => $this->portal->actualizarCliente($idCliente, $request->json()->all())]);
    }

    /**
     * Datos personales: el navegador no debe guardarlos en caché.
     */
    private function sinCache(JsonResponse $respuesta): JsonResponse
    {
        return $respuesta->header('Cache-Control', 'no-store');
    }
}
