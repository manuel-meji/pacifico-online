<?php

declare(strict_types=1);

namespace App\Http\Controllers\M6_Pagos;

use App\Http\Controllers\Controller;
use App\Http\Requests\M6_Pagos\ProcesarPagoRequest;
use App\Http\Resources\M6_Pagos\PagoResource;
use App\Services\M6_Pagos\ProcesamientoPagosService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador delgado: valida (FormRequest), delega al servicio y da formato.
 * Los errores los lanza el servicio y los formatea el Núcleo (ManejadorErrores).
 */
class PagosController extends Controller
{
    public function __construct(private readonly ProcesamientoPagosService $procesamiento) {}

    /** POST /api/m6/v1/pagos — ProcesarPagoPedido (CU-001). */
    public function store(ProcesarPagoRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $resultado = $this->procesamiento->procesar(
            (int) $datos['id_pedido'],
            strtolower($datos['medio_pago']),
            (float) $datos['monto_total'],
            $datos['idempotency_key'],
            ['token_medio_pago' => $datos['token_medio_pago'] ?? null],
        );

        // 201 la primera vez; 200 + cabecera cuando es un reintento con la misma clave.
        return (new PagoResource($resultado['pago']))
            ->response()
            ->setStatusCode($resultado['reutilizado'] ? 200 : 201)
            ->header('Idempotent-Replayed', $resultado['reutilizado'] ? 'true' : 'false');
    }

    /** GET /api/m6/v1/pagos/{id_pago} — ConsultarEstadoPago. */
    public function show(int $idPago): PagoResource
    {
        return new PagoResource($this->procesamiento->consultar($idPago));
    }
}
