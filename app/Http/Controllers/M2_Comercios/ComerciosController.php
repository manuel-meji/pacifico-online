<?php

declare(strict_types=1);

namespace App\Http\Controllers\M2_Comercios;

use App\Http\Controllers\Controller;
use App\Models\M2_Comercios\Comercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Administración de comercios por el Encargado de la plataforma (diseño, tabla 4.1):
 * CU-0017 suspender y CU-0018 reactivar, más el listado y la ficha del panel.
 * Valida la forma de la entrada, autoriza con ComercioPolicy y delega en la fachada.
 */
class ComerciosController extends Controller
{
    public function __construct(private readonly ComerciosService $comercios) {}

    public function listar(Request $request): JsonResponse
    {
        Gate::authorize('administrar', new Comercio);

        $datos = $request->validate([
            'estado' => ['sometimes', 'in:'.implode(',', Comercio::ESTADOS)],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:'.ComerciosService::MAXIMO_POR_PAGINA],
        ]);

        return response()->json($this->comercios->listarComercios(
            $datos['estado'] ?? null,
            (int) ($datos['pagina'] ?? 1),
            (int) ($datos['por_pagina'] ?? 20),
        ));
    }

    public function consultar(int $idComercio): JsonResponse
    {
        Gate::authorize('administrar', Comercio::query()->findOrFail($idComercio));

        return response()->json($this->comercios->consultarComercio($idComercio));
    }

    public function suspender(int $idComercio, Request $request): JsonResponse
    {
        Gate::authorize('suspender', Comercio::query()->findOrFail($idComercio));
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);

        $this->comercios->suspenderComercio($idComercio, $datos['motivo']);

        return response()->json($this->comercios->consultarComercio($idComercio));
    }

    public function reactivar(int $idComercio): JsonResponse
    {
        Gate::authorize('reactivar', Comercio::query()->findOrFail($idComercio));

        $this->comercios->reactivarComercio($idComercio);

        return response()->json($this->comercios->consultarComercio($idComercio));
    }
}
