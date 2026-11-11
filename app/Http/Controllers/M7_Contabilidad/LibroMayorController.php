<?php

declare(strict_types=1);

namespace App\Http\Controllers\M7_Contabilidad;

use App\Http\Controllers\Controller;
use App\Services\M7_Contabilidad\LibroMayorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LibroMayorController extends Controller
{
    public function __construct(
        private readonly LibroMayorService $libroMayorService,
    ) {}

    public function consultar(Request $request): JsonResponse
    {
        $filtrosValidados = $request->validate([
            'id_cuenta_contable' => ['required', 'integer', 'min:1'],
            'id_comercio' => ['nullable', 'integer', 'min:1'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => [
                'nullable',
                'date',
                'after_or_equal:fecha_desde',
            ],
            'incluirAnulados' => ['nullable', 'boolean'],
        ]);

        $resultado = $this->libroMayorService->consultar(
            $filtrosValidados
        );

        return response()->json([
            'success' => true,
            'data' => $resultado,
        ]);
    }
    public function listarCuentas(Request $request): JsonResponse
{
    $filtrosValidados = $request->validate([
        'id_comercio' => ['nullable', 'integer', 'min:1'],
    ]);

    $resultado = $this->libroMayorService->listarCuentas(
        $filtrosValidados
    );

    return response()->json([
        'success' => true,
        'data' => $resultado,
    ]);
}
}
