<?php

declare(strict_types=1);

namespace App\Http\Controllers\M7_Contabilidad;

use App\Http\Controllers\Controller;
use App\Services\M7_Contabilidad\BalanceGeneralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BalanceGeneralController extends Controller
{
    public function __construct(
        private readonly BalanceGeneralService $balanceGeneralService,
    ) {}

    public function consultar(Request $request): JsonResponse
    {
        $datosValidados = $request->validate([
            'id_comercio' => ['required', 'integer', 'min:1'],
            'fecha_corte' => ['required', 'date_format:Y-m-d'],
        ]);

        $resultado = $this->balanceGeneralService->consultar(
            (int) $datosValidados['id_comercio'],
            $datosValidados['fecha_corte']
        );

        return response()->json([
            'success' => true,
            'data' => $resultado,
        ]);
    }
}
