<?php

declare(strict_types=1);

namespace App\Http\Controllers\M2_Comercios;

use App\Http\Controllers\Controller;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\PublicidadComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Configuración comercial (diseño, tabla 4.1): publicidad para destacar comercios.
 * El encargado del comercio solicita el destacado; la plataforma lo aprueba, lo rechaza
 * o destaca un comercio directamente. La gestión de comisión y membresía queda pendiente.
 */
class ConfiguracionComercialController extends Controller
{
    public function __construct(private readonly ComerciosService $comercios) {}

    public function publicidadDelComercio(int $idComercio): JsonResponse
    {
        Gate::authorize('verPublicidad', Comercio::query()->findOrFail($idComercio));

        return response()->json($this->comercios->listarPublicidadComercio($idComercio));
    }

    public function solicitarPublicidad(int $idComercio, Request $request): JsonResponse
    {
        $comercio = Comercio::query()->findOrFail($idComercio);
        $porPlataforma = Gate::allows('administrar', $comercio);
        if (! $porPlataforma) {
            Gate::authorize('actualizar', $comercio);
        }
        $datos = $request->validate(['dias' => ['required', 'integer', Rule::in(PublicidadComercio::DIAS_PERMITIDOS)]]);

        $this->comercios->solicitarPublicidad($idComercio, (int) $datos['dias'], $porPlataforma);

        return response()->json($this->comercios->listarPublicidadComercio($idComercio), 201);
    }

    public function publicidadPendiente(): JsonResponse
    {
        Gate::authorize('administrar', new Comercio);

        return response()->json($this->comercios->listarPublicidadPendiente());
    }

    public function gestionarPublicidad(int $idPublicidad, Request $request): JsonResponse
    {
        Gate::authorize('administrar', new Comercio);
        $datos = $request->validate(['accion' => ['required', Rule::in(['aprobar', 'rechazar'])]]);

        $publicidad = $this->comercios->gestionarPublicidad($idPublicidad, $datos['accion']);

        return response()->json($this->comercios->listarPublicidadComercio($publicidad->id_comercio));
    }
}
