<?php

declare(strict_types=1);

namespace App\Http\Controllers\M2_Comercios;

use App\Http\Controllers\Controller;
use App\Http\Controllers\M2_Comercios\Concerns\ValidaEnEspanol;
use App\Models\M2_Comercios\Comercio;
use App\Models\M2_Comercios\PublicidadComercio;
use App\Services\M2_Comercios\ComerciosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Configuración comercial (diseño, tabla 4.1): publicidad para destacar comercios.
 * El encargado del comercio solicita el destacado; la plataforma lo aprueba, lo rechaza (con motivo),
 * lo retira antes de terminar (con motivo) o destaca un comercio directamente. La gestión de comisión y membresía queda pendiente.
 */
class ConfiguracionComercialController extends Controller
{
    use ValidaEnEspanol;

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
        $datos = $this->validarM2($request, ['dias' => ['required', 'integer', Rule::in(PublicidadComercio::DIAS_PERMITIDOS)]], [
            'dias.in' => 'Elegí un periodo de 7, 15 o 30 días.',
        ]);

        $this->comercios->solicitarPublicidad($idComercio, (int) $datos['dias'], $porPlataforma);

        return response()->json($this->comercios->listarPublicidadComercio($idComercio), 201);
    }

    public function publicidadPendiente(): JsonResponse
    {
        Gate::authorize('administrar', new Comercio);

        return response()->json($this->comercios->listarPublicidadPendiente());
    }

    /** Destacados aprobados que todavía no terminaron (vigentes o programados), para retirarlos. */
    public function publicidadVigente(): JsonResponse
    {
        Gate::authorize('administrar', new Comercio);

        return response()->json($this->comercios->listarPublicidadVigente());
    }

    public function gestionarPublicidad(int $idPublicidad, Request $request): JsonResponse
    {
        Gate::authorize('administrar', new Comercio);
        $datos = $this->validarM2($request, [
            'accion' => ['required', Rule::in(['aprobar', 'rechazar', 'retirar'])],
            'motivo' => ['nullable', 'required_unless:accion,aprobar', 'string', 'max:500'],
        ], [
            'motivo.required_unless' => 'Escribí el motivo: el comercio lo va a ver en su historial.',
        ]);

        $publicidad = $this->comercios->gestionarPublicidad($idPublicidad, $datos['accion'], $datos['motivo'] ?? null);

        return response()->json($this->comercios->listarPublicidadComercio($publicidad->id_comercio));
    }
}
