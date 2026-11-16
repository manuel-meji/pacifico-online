<?php

namespace App\Http\Controllers\M5_Pedidos;

use App\Contracts\M1_Portal\PortalPublicoContract;
use App\Contracts\M1_Portal\PortalServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\M5_Pedidos\DevolucionSubpedido;
use App\Services\M5_Pedidos\DevolucionesService;
use App\Services\M5_Pedidos\Excepciones\ErrorDevolucionException;
use App\Services\Nucleo\ContextoCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Devoluciones de subpedidos (RF-019, RF-020, RF-021). Controlador delgado: la lógica está en
 * DevolucionesService.
 *
 * Identidad: igual que el resto de M5, el cliente y el comercio salen primero de la sesión
 * autenticada; sin sesión se usan id_cliente / id_comercio de la petición (modo desarrollo).
 */
class ControladorDevoluciones extends Controller
{
    public function __construct(private readonly DevolucionesService $devoluciones) {}

    // ---- Cliente (RF-019)

    public function solicitar(Request $request, int $id_subpedido): JsonResponse
    {
        $maxArchivos = (int) config('devoluciones.evidencia_max_archivos', 5);
        $maxKb = (int) config('devoluciones.evidencia_max_kb', 5120);
        $mimes = implode(',', (array) config('devoluciones.evidencia_mimes', ['jpg', 'jpeg', 'png', 'webp', 'pdf']));

        $validador = Validator::make($request->all(), [
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
            'lineas' => ['nullable', 'array'],
            'lineas.*.id_linea' => ['required_with:lineas', 'integer', 'min:1'],
            'lineas.*.cantidad' => ['required_with:lineas', 'integer', 'min:1'],
            'evidencias' => ['required', 'array', 'min:1', "max:{$maxArchivos}"],
            'evidencias.*' => ['file', "mimes:{$mimes}", "max:{$maxKb}"],
        ], [
            'motivo.required' => 'Indique el motivo de la devolución.',
            'evidencias.required' => 'Debe adjuntar al menos un archivo de evidencia.',
            'evidencias.min' => 'Debe adjuntar al menos un archivo de evidencia.',
        ]);

        if ($validador->fails()) {
            return $this->error(ErrorDevolucionException::validacion($validador->errors()->toArray()));
        }

        try {
            $devolucion = $this->devoluciones->solicitar(
                $this->idCliente($request),
                $id_subpedido,
                (string) $request->input('motivo'),
                array_values((array) $request->input('lineas', [])),
                array_values($request->file('evidencias', [])),
            );
        } catch (ErrorDevolucionException $e) {
            return $this->error($e);
        }

        return response()->json([
            'mensaje' => 'Solicitud de devolución registrada. El comercio la revisará.',
            'devolucion' => $this->presentar($devolucion),
        ], 201);
    }

    public function porSubpedido(Request $request, int $id_subpedido): JsonResponse
    {
        $idCliente = $this->idClienteOpcional($request);
        $idComercio = $this->idComercioOpcional($request);

        if ($idCliente === null && $idComercio === null) {
            return $this->error(ErrorDevolucionException::noAutorizado());
        }

        $lista = $this->devoluciones->listarPorSubpedido($id_subpedido, $idComercio === null ? $idCliente : null, $idComercio);

        return response()->json(['devoluciones' => $lista->map(fn ($d) => $this->presentar($d))->values()]);
    }

    // ---- Consulta (cliente o comercio)

    /** GET /devoluciones?rol=comercio|cliente&estado=solicitada */
    public function listar(Request $request): JsonResponse
    {
        $estado = $request->filled('estado') ? (string) $request->input('estado') : null;

        if ($request->input('rol') === 'comercio') {
            $idComercio = $this->idComercioOpcional($request);

            if ($idComercio === null) {
                return $this->error(ErrorDevolucionException::noAutorizado('No se pudo identificar el comercio.'));
            }

            $lista = $this->devoluciones->listarDelComercio($idComercio, $estado);
        } else {
            $lista = $this->devoluciones->listarDelCliente($this->idCliente($request), $estado);
        }

        return response()->json(['devoluciones' => $lista->map(fn ($d) => $this->presentar($d))->values()]);
    }

    public function mostrar(Request $request, int $id_devolucion): JsonResponse
    {
        try {
            $devolucion = $this->devoluciones->obtener($id_devolucion, $this->idClienteOpcional($request), $this->idComercioOpcional($request));
        } catch (ErrorDevolucionException $e) {
            return $this->error($e);
        }

        return response()->json(['devolucion' => $this->presentar($devolucion)]);
    }

    public function descargarEvidencia(Request $request, int $id_devolucion, int $id_evidencia): StreamedResponse|JsonResponse
    {
        try {
            return $this->devoluciones->descargarEvidencia(
                $id_devolucion,
                $id_evidencia,
                $this->idClienteOpcional($request),
                $this->idComercioOpcional($request),
            );
        } catch (ErrorDevolucionException $e) {
            return $this->error($e);
        }
    }

    // ---- Comercio (RF-020)

    public function aprobar(Request $request, int $id_devolucion): JsonResponse
    {
        $idComercio = $this->idComercioOpcional($request);

        if ($idComercio === null) {
            return $this->error(ErrorDevolucionException::noAutorizado('No se pudo identificar el comercio.'));
        }

        try {
            $resultado = $this->devoluciones->aprobar($idComercio, $id_devolucion, $this->idUsuario($request));
        } catch (ErrorDevolucionException $e) {
            return $this->error($e);
        }

        return response()->json([
            'mensaje' => 'Devolución aprobada. Se notificó a Inventario, Pagos y Comercios.',
            'advertencia' => $resultado['advertencia'],
            'devolucion' => $this->presentar($resultado['devolucion']),
        ]);
    }

    public function rechazar(Request $request, int $id_devolucion): JsonResponse
    {
        $idComercio = $this->idComercioOpcional($request);

        if ($idComercio === null) {
            return $this->error(ErrorDevolucionException::noAutorizado('No se pudo identificar el comercio.'));
        }

        $validador = Validator::make($request->all(), [
            'motivo_rechazo' => ['required', 'string', 'min:5', 'max:1000'],
        ], ['motivo_rechazo.required' => 'Indique el motivo del rechazo.']);

        if ($validador->fails()) {
            return $this->error(ErrorDevolucionException::validacion($validador->errors()->toArray()));
        }

        try {
            $devolucion = $this->devoluciones->rechazar(
                $idComercio,
                $id_devolucion,
                (string) $request->input('motivo_rechazo'),
                $this->idUsuario($request),
            );
        } catch (ErrorDevolucionException $e) {
            return $this->error($e);
        }

        return response()->json([
            'mensaje' => 'Devolución rechazada.',
            'devolucion' => $this->presentar($devolucion),
        ]);
    }

    public function reintentarReembolso(Request $request, int $id_devolucion): JsonResponse
    {
        $idComercio = $this->idComercioOpcional($request);

        if ($idComercio === null) {
            return $this->error(ErrorDevolucionException::noAutorizado('No se pudo identificar el comercio.'));
        }

        try {
            $resultado = $this->devoluciones->reintentarReembolso($idComercio, $id_devolucion);
        } catch (ErrorDevolucionException $e) {
            return $this->error($e);
        }

        return response()->json([
            'mensaje' => $resultado['advertencia'] ?? 'Reembolso solicitado en Pagos.',
            'advertencia' => $resultado['advertencia'],
            'devolucion' => $this->presentar($resultado['devolucion']),
        ]);
    }

    /**
     * Respuesta de la ruta anterior (POST /subpedidos/{id}/devolucion) cuando se bloquea con
     * M5_DEVOLUCIONES_BLOQUEAR_RUTA_LEGADA=true: reembolsaba al instante, sin revisión del comercio.
     */
    public function rutaLegada(): JsonResponse
    {
        return response()->json([
            'error' => [
                'codigo' => 'ERR-M5-013',
                'mensaje' => 'Esta ruta fue reemplazada. Use POST /subpedidos/{id}/devoluciones con motivo y evidencia.',
                'detalles' => null,
            ],
            'mensaje' => 'Esta ruta fue reemplazada. Use POST /subpedidos/{id}/devoluciones con motivo y evidencia.',
        ], 410);
    }

    // ---- Internos

    private function error(ErrorDevolucionException $e): JsonResponse
    {
        return response()->json([
            'error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage(), 'detalles' => $e->detalles],
            'mensaje' => $e->getMessage(),
        ], $e->status);
    }

    /** @return array<string, mixed> */
    private function presentar(DevolucionSubpedido $devolucion): array
    {
        $devolucion->loadMissing(['lineas', 'evidencias']);
        $datos = $devolucion->toArray();

        $datos['evidencias'] = $devolucion->evidencias->map(fn ($e): array => [
            'id_evidencia' => (int) $e->id_evidencia,
            'nombre_original' => $e->nombre_original,
            'mime' => $e->mime,
            'tamano' => (int) $e->tamano,
            'url' => "/api/m5/v1/devoluciones/{$devolucion->id_devolucion}/evidencias/{$e->id_evidencia}",
        ])->values()->all();

        return $datos;
    }

    private function idCliente(Request $request): int
    {
        return $this->idClienteOpcional($request) ?? (int) $request->input('id_cliente', 1);
    }

    private function idClienteOpcional(Request $request): ?int
    {
        $usuario = $request->user();

        if ($usuario !== null) {
            $idUsuario = (int) ($usuario->id_usuario ?? $usuario->id ?? 0);
            $portal = app()->bound(PortalPublicoContract::class)
                ? app(PortalPublicoContract::class)
                : (app()->bound(PortalServiceInterface::class) ? app(PortalServiceInterface::class) : null);

            if ($idUsuario > 0 && $portal !== null && method_exists($portal, 'obtenerIdClientePorUsuario')) {
                $idCliente = (int) $portal->obtenerIdClientePorUsuario($idUsuario);

                if ($idCliente > 0) {
                    if (app()->bound(ContextoCliente::class)) {
                        app(ContextoCliente::class)->establecer($idCliente);
                    }

                    return $idCliente;
                }
            }

            return null;
        }

        return $request->filled('id_cliente') ? (int) $request->input('id_cliente') : null;
    }

    private function idComercioOpcional(Request $request): ?int
    {
        $usuario = $request->user();

        if ($usuario !== null) {
            return isset($usuario->id_comercio) && (int) $usuario->id_comercio > 0 ? (int) $usuario->id_comercio : null;
        }

        if ($request->hasHeader('X-Comercio-Id')) {
            return (int) $request->header('X-Comercio-Id') ?: null;
        }

        return $request->filled('id_comercio') ? (int) $request->input('id_comercio') : null;
    }

    private function idUsuario(Request $request): ?int
    {
        $usuario = $request->user();

        return $usuario === null ? null : ((int) ($usuario->id_usuario ?? $usuario->id ?? 0) ?: null);
    }
}
