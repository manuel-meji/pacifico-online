<?php

declare(strict_types=1);

namespace App\Http\Controllers\M6_Pagos;

use App\Http\Controllers\Controller;
use App\Http\Requests\M6_Pagos\GestionarReembolsoRequest;
use App\Http\Resources\M6_Pagos\ReembolsoResource;
use App\Services\M6_Pagos\ReembolsoService;
use Illuminate\Http\JsonResponse;

/** Reembolsos (CU-007). Controlador delgado. */
class ReembolsosController extends Controller
{
    public function __construct(private readonly ReembolsoService $reembolsos) {}

    /** POST /api/m6/v1/pedidos/{id_pedido}/reembolsos — GestionarReembolso. */
    public function store(GestionarReembolsoRequest $request, int $idPedido): JsonResponse
    {
        $datos = $request->validated();

        $reembolsos = $this->reembolsos->gestionar(
            $idPedido,
            isset($datos['id_subpedido']) ? (int) $datos['id_subpedido'] : null,
            (float) $datos['monto'],
            (string) $datos['motivo'],
        );

        $reembolsos->each->loadMissing(['pago', 'estado']);

        // Un subpedido → un objeto Reembolso (contrato). Reembolso total multicomercio → lista (ver nota de contrato).
        if ($reembolsos->count() === 1) {
            return (new ReembolsoResource($reembolsos->first()))->response()->setStatusCode(201);
        }

        return response()->json(
            $reembolsos->map(fn ($r) => (new ReembolsoResource($r))->resolve($request))->values()->all(),
            201,
        );
    }
}
