<?php

declare(strict_types=1);

namespace App\Http\Controllers\M7_Contabilidad;

use App\Http\Controllers\Controller;
use App\Services\M7_Contabilidad\ConsultasOperativasService;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ConsultasOperativasController extends Controller
{
    public function __construct(
        private readonly ConsultasOperativasService $consultas,
        private readonly ContextoComercio $contexto,
    ) {}

    public function comercios(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['comercios' => $this->consultas->comercios()]]);
    }

    public function saldos(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->consultas->saldos($this->comercio($request))]);
    }

    public function centros(Request $request): JsonResponse
    {
        $comercio = $this->comercio($request);
        [$desde, $hasta] = $this->periodo($request);

        return response()->json(['success' => true, 'data' => $this->consultas->centros($comercio, $desde, $hasta)]);
    }

    public function movimientosCentro(Request $request, int $idCentroCosto): JsonResponse
    {
        return $this->detalleCentro($request, $idCentroCosto);
    }

    public function movimientosSinCentro(Request $request): JsonResponse
    {
        return $this->detalleCentro($request, null);
    }

    public function asiento(Request $request, int $idAsiento): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->consultas->asiento($this->comercio($request), $idAsiento)]);
    }

    private function detalleCentro(Request $request, ?int $idCentroCosto): JsonResponse
    {
        $comercio = $this->comercio($request);
        [$desde, $hasta] = $this->periodo($request);

        return response()->json([
            'success' => true,
            'data' => $this->consultas->movimientosCentro($comercio, $idCentroCosto, $desde, $hasta),
        ]);
    }

    /** @return array{0: string|null, 1: string|null} */
    private function periodo(Request $request): array
    {
        $filtros = $request->validate([
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
        ]);

        return [$filtros['fecha_desde'] ?? null, $filtros['fecha_hasta'] ?? null];
    }

    private function comercio(Request $request): int
    {
        $filtros = $request->validate(['id_comercio' => ['nullable', 'integer', 'min:1']]);
        $solicitado = $filtros['id_comercio'] ?? null;
        $actual = $this->contexto->idActual();
        if ($this->contexto->esModoSistema()) {
            if ($solicitado === null) {
                throw ValidationException::withMessages(['id_comercio' => 'Debe indicar el comercio que desea consultar.']);
            }

            return (int) $solicitado;
        }
        if ($actual === null) {
            throw new AuthorizationException('No existe un comercio activo para consultar.');
        }
        if ($solicitado !== null && (int) $solicitado !== $actual) {
            throw new AuthorizationException('No tiene autorización para consultar otro comercio.');
        }

        return $actual;
    }
}
