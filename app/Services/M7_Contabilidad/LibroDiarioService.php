<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\DB;
use LogicException;

final class LibroDiarioService
{
    public function __construct(
        private readonly ContextoComercio $contexto,
    ) {}

    /**
     * Consulta los asientos contables del comercio activo.
     *
     * Filtros opcionales:
     * - fecha_desde
     * - fecha_hasta
     * - tipo
     * - estado
     * - buscar
     *
     * @return array{
     *     asientos: array,
     *     totalDebe: float,
     *     totalHaber: float,
     *     cantidad: int
     * }
     */
    public function listar(array $filtros = []): array
    {
        $comercioId = $this->contexto->idActual();
        $modoSistema = $this->contexto->esModoSistema();

        if ($comercioId === null && ! $modoSistema) {
            throw new LogicException(
                'No existe un comercio activo ni autorización global para consultar el Libro Diario.'
            );
        }

        $consulta = DB::table('asientos_contables as a')
            ->leftJoin('detalle_asientos as d', 'd.id_asiento', '=', 'a.id_asiento')
            ->leftJoin(
                'cuentas_contables as c',
                function ($join) {
                    $join->on(
                        'c.id_cuenta_contable',
                        '=',
                        'd.id_cuenta_contable'
                    )->on(
                        'c.id_comercio',
                        '=',
                        'a.id_comercio'
                    );
                }
            )
            ->when(
                ! $modoSistema,
                fn ($query) => $query->where('a.id_comercio', $comercioId)
            )
            ->select([
                'a.id_asiento',
                'a.id_pedido',
                'a.id_subpedido',
                'a.id_pago',
                'a.fecha',
                'a.descripcion as descripcion_asiento',
                'a.tipo',
                'a.estado',
                'd.numero_linea',
                'd.descripcion as descripcion_detalle',
                'd.debe',
                'd.haber',
                'c.codigo as codigo_cuenta',
                'c.nombre as nombre_cuenta',
            ]);

        if (! empty($filtros['fecha_desde'])) {
            $consulta->whereDate('a.fecha', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $consulta->whereDate('a.fecha', '<=', $filtros['fecha_hasta']);
        }

        if (! empty($filtros['tipo'])) {
            $consulta->where('a.tipo', $filtros['tipo']);
        }

        if (! empty($filtros['estado'])) {
            $consulta->where('a.estado', $filtros['estado']);
        }

        if (! empty($filtros['buscar'])) {
            $buscar = trim((string) $filtros['buscar']);

            $consulta->where(function ($query) use ($buscar): void {
                $query->where('a.descripcion', 'like', "%{$buscar}%")
                    ->orWhere('a.tipo', 'like', "%{$buscar}%")
                    ->orWhereExists(function ($subconsulta) use ($buscar): void {
                        $subconsulta->selectRaw('1')
                            ->from('detalle_asientos as ds')
                            ->leftJoin(
                                'cuentas_contables as cc',
                                'cc.id_cuenta_contable',
                                '=',
                                'ds.id_cuenta_contable'
                            )
                            ->whereColumn('ds.id_asiento', 'a.id_asiento')
                            ->where(function ($detalleQuery) use ($buscar): void {
                                $detalleQuery->where('cc.codigo', 'like', "%{$buscar}%")
                                    ->orWhere('cc.nombre', 'like', "%{$buscar}%")
                                    ->orWhere('ds.descripcion', 'like', "%{$buscar}%");
                            });
                    });

                if (ctype_digit($buscar)) {
                    $query->orWhere('a.id_asiento', (int) $buscar)
                        ->orWhere('a.id_pedido', (int) $buscar)
                        ->orWhere('a.id_subpedido', (int) $buscar);
                }
            });
        }

        $filas = $consulta
            ->orderByDesc('a.fecha')
            ->orderByDesc('a.id_asiento')
            ->orderBy('d.numero_linea')
            ->get();

        $asientos = [];

        foreach ($filas as $fila) {
            $id = (int) $fila->id_asiento;

            if (! isset($asientos[$id])) {
                $referencia = $fila->id_subpedido !== null
                    ? 'Subpedido '.$fila->id_subpedido
                    : ($fila->id_pedido !== null
                        ? 'Pedido '.$fila->id_pedido
                        : ($fila->id_pago !== null
                            ? 'Pago '.$fila->id_pago
                            : 'Asiento '.$id));

                $asientos[$id] = [
                    'id' => $id,
                    'numero' => 'ASI-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT),
                    'fecha' => $fila->fecha,
                    'descripcion' => $fila->descripcion_asiento
                        ?? $this->etiquetaTipo((string) $fila->tipo),
                    'tipo' => $this->etiquetaTipo((string) $fila->tipo),
                    'estado' => $this->etiquetaEstado((string) $fila->estado),
                    'referencia' => $referencia,
                    'lineas' => [],
                    'totalDebe' => 0.0,
                    'totalHaber' => 0.0,
                ];
            }

            if ($fila->numero_linea !== null) {
                $debe = (float) ($fila->debe ?? 0);
                $haber = (float) ($fila->haber ?? 0);

                $asientos[$id]['lineas'][] = [
                    'codigo' => $fila->codigo_cuenta ?? '',
                    'cuenta' => $fila->nombre_cuenta ?? 'Cuenta no disponible',
                    'debe' => $debe,
                    'haber' => $haber,
                    'descripcion' => $fila->descripcion_detalle,
                ];

                $asientos[$id]['totalDebe'] += $debe;
                $asientos[$id]['totalHaber'] += $haber;
            }
        }

        $resultado = array_values($asientos);

        $totalDebe = 0.0;
        $totalHaber = 0.0;

        foreach ($resultado as $asiento) {
            $totalDebe += $asiento['totalDebe'];
            $totalHaber += $asiento['totalHaber'];
        }

        return [
            'asientos' => $resultado,
            'totalDebe' => round($totalDebe, 2),
            'totalHaber' => round($totalHaber, 2),
            'cantidad' => count($resultado),
        ];
    }

    private function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            'VENTA' => 'Venta automática',
            'COSTO_VENTA' => 'Costo de ventas',
            default => $tipo,
        };
    }

    private function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'REGISTRADO' => 'Registrado',
            'PENDIENTE' => 'Pendiente',
            'ANULADO' => 'Anulado',
            default => $estado,
        };
    }
}
