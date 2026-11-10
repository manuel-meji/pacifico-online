<?php

declare(strict_types=1);

namespace App\Http\Controllers\M6_Pagos;

use App\Http\Controllers\Controller;
use App\Http\Requests\M6_Pagos\ConsultarHistorialRequest;
use App\Http\Resources\M6_Pagos\FacturaResource;
use App\Http\Resources\M6_Pagos\PagoResource;
use App\Services\M6_Pagos\HistorialService;
use Illuminate\Http\JsonResponse;

/** Historial de pagos y facturas (CU-008). Controlador delgado. */
class HistorialController extends Controller
{
    public function __construct(private readonly HistorialService $historial) {}

    /** GET /api/m6/v1/historial — ConsultarHistorialPagosFacturas. */
    public function __invoke(ConsultarHistorialRequest $request): JsonResponse
    {
        $datos = $request->validated();

        ['pagos' => $pagos, 'facturas' => $facturas] = $this->historial->consultar(
            isset($datos['id_cliente']) ? (int) $datos['id_cliente'] : null,
            isset($datos['id_comercio']) ? (int) $datos['id_comercio'] : null,
        );

        return response()->json([
            'pagos' => PagoResource::collection($pagos)->resolve($request),
            'facturas' => FacturaResource::collection($facturas)->resolve($request),
        ]);
    }
}
