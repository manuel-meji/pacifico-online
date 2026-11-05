<?php

declare(strict_types=1);

namespace App\Http\Controllers\M6_Pagos;

use App\Http\Controllers\Controller;
use App\Http\Requests\M6_Pagos\ConfirmarPagoRequest;
use App\Services\M6_Pagos\ConfirmacionPagoService;
use Illuminate\Http\JsonResponse;

/**
 * Webhook de la pasarela / SINPE / PayPal — ConfirmarPagoRecibido (CU-002).
 * Responde 200 también ante duplicados: así la pasarela deja de reintentar.
 */
class ConfirmacionPagoController extends Controller
{
    public function __construct(private readonly ConfirmacionPagoService $confirmaciones) {}

    public function __invoke(ConfirmarPagoRequest $request, int $idPago): JsonResponse
    {
        $datos = $request->validated();

        $resultado = $this->confirmaciones->confirmar(
            $idPago,
            $datos['referencia_proveedor'],
            $datos['estado'],
            $datos['motivo'] ?? null,
        );

        $pago = $resultado['pago'];

        return response()->json([
            'id' => $pago->id_pago,
            'estado' => $pago->codigoEstado(),
            'fecha' => ($pago->fecha_confirmacion ?? now())->toIso8601String(),
            'duplicada' => $resultado['duplicada'],
        ]);
    }
}
