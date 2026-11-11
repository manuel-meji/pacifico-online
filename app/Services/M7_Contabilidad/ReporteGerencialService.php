<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ReporteGerencialService
{
    public function __construct(
        private readonly ContextoComercio $contexto,
    ) {}

    public function generar(array $filtros = []): array
    {
        $fechaDesde = $filtros['fecha_desde']
            ?? Carbon::now()->startOfMonth()->toDateString();

        $fechaHasta = $filtros['fecha_hasta']
            ?? Carbon::now()->endOfMonth()->toDateString();

        $idComercio = $this->resolverComercio(
            $filtros['id_comercio'] ?? null
        );

        $movimientos = DB::table('asientos_contables as a')
            ->join('detalle_asientos as d', 'd.id_asiento', '=', 'a.id_asiento')
            ->join('cuentas_contables as c', function ($join) {
                $join->on(
                    'c.id_cuenta_contable',
                    '=',
                    'd.id_cuenta_contable'
                )->on(
                    'c.id_comercio',
                    '=',
                    'a.id_comercio'
                );
            })
            ->where('a.id_comercio', $idComercio)
            ->where('a.estado', 'REGISTRADO')
            ->whereIn('a.tipo', ['VENTA', 'COSTO_VENTA'])
            ->whereDate('a.fecha', '>=', $fechaDesde)
            ->whereDate('a.fecha', '<=', $fechaHasta)
            ->selectRaw("TO_CHAR(a.fecha, 'YYYY-MM') AS periodo")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN a.tipo = 'VENTA' AND c.codigo = '4.1.01'
                        THEN d.haber - d.debe
                        ELSE 0
                    END
                ) AS ingresos
            ")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN a.tipo = 'COSTO_VENTA' AND c.codigo = '5.1.01'
                        THEN d.debe - d.haber
                        ELSE 0
                    END
                ) AS costos
            ")
            ->groupByRaw("TO_CHAR(a.fecha, 'YYYY-MM')")
            ->orderBy('periodo')
            ->get();

        $ingresosCentimos = 0;
        $costosCentimos = 0;
        $mensual = [];

        foreach ($movimientos as $movimiento) {
            $ingresos = $this->aCentimos($movimiento->ingresos);
            $costos = $this->aCentimos($movimiento->costos);

            $ingresosCentimos += $ingresos;
            $costosCentimos += $costos;

            $mensual[] = [
                'periodo' => $movimiento->periodo,
                'ingresos' => $this->formatearImporte($ingresos),
                'costos' => $this->formatearImporte($costos),
                'utilidad_bruta' => $this->formatearImporte(
                    $ingresos - $costos
                ),
            ];
        }

        return [
            'id_comercio' => $idComercio,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'resumen' => [
                'ingresos' => $this->formatearImporte($ingresosCentimos),
                'costos' => $this->formatearImporte($costosCentimos),
                'utilidad_bruta' => $this->formatearImporte(
                    $ingresosCentimos - $costosCentimos
                ),
            ],
            'mensual' => $mensual,
        ];
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
                    'No existe un comercio activo para consultar el reporte.'
                );
            }

            $idComercio = $actual;

            if ($solicitado !== null && (int) $solicitado !== $actual) {
                throw new LogicException(
                    'No tiene autorización para consultar otro comercio.'
                );
            }
        }

        if ($idComercio <= 0) {
            throw new LogicException('El comercio indicado no es válido.');
        }

        return $idComercio;
    }

    private function aCentimos(string|int|float|null $importe): int
    {
        $importe = (string) ($importe ?? '0');

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $importe)) {
            throw new LogicException('El importe del reporte tiene formato inválido.');
        }

        [$entero, $decimal] = array_pad(explode('.', $importe, 2), 2, '');

        return ((int) $entero * 100)
            + (int) str_pad($decimal, 2, '0');
    }

    private function formatearImporte(int $centimos): string
    {
        return intdiv($centimos, 100)
            . '.'
            . str_pad(
                (string) ($centimos % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }
}