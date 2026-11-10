<?php

declare(strict_types=1);

namespace App\Http\Controllers\M7_Contabilidad;

use App\Http\Controllers\Controller;
use App\Services\M7_Contabilidad\LibroDiarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LibroDiarioController extends Controller
{
    public function __construct(
        private readonly LibroDiarioService $libroDiarioService,
    ) {}

    public function listar(Request $request): JsonResponse
    {
        $filtrosValidados = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'tipo' => ['nullable', 'string', 'max:40'],
            'estado' => ['nullable', 'in:REGISTRADO,PENDIENTE,ANULADO'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        $resultado = $this->libroDiarioService->listar($filtrosValidados);

        return response()->json([
            'success' => true,
            'data' => $resultado,
        ]);
    }
}
