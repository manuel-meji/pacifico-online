<?php

declare(strict_types=1);

namespace App\Http\Controllers\M7_Contabilidad;

use App\Http\Controllers\Controller;
use App\Services\M7_Contabilidad\ReporteGerencialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReporteGerencialController extends Controller
{
    public function __construct(
        private readonly ReporteGerencialService $reporte,
    ) {}

    public function consultar(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'id_comercio' => ['nullable', 'integer', 'min:1'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => [
                'nullable',
                'date',
                'after_or_equal:fecha_desde',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->reporte->generar($filtros),
        ]);
    }
}
