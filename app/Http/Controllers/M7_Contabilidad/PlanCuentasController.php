<?php

declare(strict_types=1);

namespace App\Http\Controllers\M7_Contabilidad;

use App\Http\Controllers\Controller;
use App\Services\M7_Contabilidad\PlanCuentasService;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class PlanCuentasController extends Controller
{
    public function __construct(
        private readonly PlanCuentasService $planCuentas,
        private readonly ContextoComercio $contexto,
    ) {
    }

    public function listar(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'id_comercio' => ['nullable', 'integer', 'min:1'],
            'solo_activas' => ['nullable', 'boolean'],
        ]);

        $idComercio = $this->resolverComercio(
            $filtros['id_comercio'] ?? null
        );

        $cuentas = $this->planCuentas->listar(
            $idComercio,
            filter_var(
                $filtros['solo_activas'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            )
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id_comercio' => $idComercio,
                'cuentas' => $cuentas->map(
                    fn ($cuenta): array => $this->formatearCuenta($cuenta)
                )->values()->all(),
            ],
        ]);
    }

    public function consultar(
        Request $request,
        int $idCuentaContable
    ): JsonResponse {
        $filtros = $request->validate([
            'id_comercio' => ['nullable', 'integer', 'min:1'],
        ]);

        $idComercio = $this->resolverComercio(
            $filtros['id_comercio'] ?? null
        );

        $cuenta = $this->planCuentas->obtener(
            $idComercio,
            $idCuentaContable
        );

        return response()->json([
            'success' => true,
            'data' => $this->formatearCuenta($cuenta),
        ]);
    }

    private function resolverComercio(mixed $solicitado): int
    {
        $actual = $this->contexto->idActual();

        if ($this->contexto->esModoSistema()) {
            if ($solicitado === null) {
                throw new LogicException(
                    'Debe indicar el comercio que desea consultar.'
                );
            }

            $idComercio = (int) $solicitado;
        } else {
            if ($actual === null) {
                throw new LogicException(
                    'No existe un comercio activo para consultar el plan de cuentas.'
                );
            }

            $idComercio = $actual;

            if (
                $solicitado !== null
                && (int) $solicitado !== $actual
            ) {
                throw new LogicException(
                    'No tiene autorización para consultar otro comercio.'
                );
            }
        }

        if ($idComercio <= 0) {
            throw new LogicException(
                'El comercio indicado no es válido.'
            );
        }

        return $idComercio;
    }

    private function formatearCuenta($cuenta): array
    {
        return [
            'id_cuenta_contable' => (int) $cuenta->id_cuenta_contable,
            'codigo' => $cuenta->codigo,
            'nombre' => $cuenta->nombre,
            'tipo' => $cuenta->tipo,
            'naturaleza' => $cuenta->naturaleza,
            'nivel' => (int) $cuenta->nivel,
            'id_cuenta_padre' => $cuenta->id_cuenta_padre !== null
                ? (int) $cuenta->id_cuenta_padre
                : null,
            'activa' => (bool) $cuenta->activa,
        ];
    }
}