<?php

declare(strict_types=1);

namespace App\Http\Controllers\M6_Pagos;

use App\Http\Controllers\Controller;
use App\Http\Requests\M6_Pagos\ConciliarPagosRequest;
use App\Services\M6_Pagos\ConciliacionService;
use Illuminate\Http\JsonResponse;

/** Conciliación de pagos del período (CU-003). Controlador delgado. */
class ConciliacionController extends Controller
{
    public function __construct(private readonly ConciliacionService $conciliacion) {}

    /** POST /api/m6/v1/conciliaciones — ConciliarPagosPeriodo. */
    public function __invoke(ConciliarPagosRequest $request): JsonResponse
    {
        return response()->json($this->conciliacion->conciliar(
            (string) $request->validated('fecha_inicio'),
            (string) $request->validated('fecha_fin'),
        ));
    }
}
