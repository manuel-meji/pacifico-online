<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BalanceGeneralService
{
    public function __construct(
    private readonly ContextoComercio $contexto,
    private readonly ValidadorPartidaDoble $validador,
    private readonly CalculadoraBalanceGeneral $calculadora,
) {}

    public function consultar(int $idComercio, string $fechaCorte): array
    {
        if ($idComercio <= 0) {
            throw new InvalidArgumentException(
                'El identificador del comercio no es válido.'
            );
        }

        $comercioActual = $this->contexto->idActual();

        if (! $this->contexto->esModoSistema()) {
            if ($comercioActual === null) {
                throw new InvalidArgumentException(
                    'No existe un comercio autorizado en el contexto actual.'
                );
            }

            if ($idComercio !== $comercioActual) {
                throw new InvalidArgumentException(
                    'No tiene autorización para consultar este comercio.'
                );
            }
        }

        if (
            ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaCorte)
            || ! Carbon::hasFormatWithModifiers($fechaCorte, 'Y-m-d')
        ) {
            throw new InvalidArgumentException(
                'La fecha de corte debe ser una fecha válida con formato YYYY-MM-DD.'
            );
        }

        $movimientos = DB::table('asientos_contables as a')
            ->join(
                'detalle_asientos as d',
                'd.id_asiento',
                '=',
                'a.id_asiento'
            )
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
            ->whereDate('a.fecha', '<=', $fechaCorte)
            ->select([
                'c.id_cuenta_contable',
                'c.codigo',
                'c.nombre',
                'c.tipo',
                'c.id_cuenta_padre',
                'd.debe',
                'd.haber',
            ])
            ->get();

        $saldosPorCuenta = [];

        foreach ($movimientos as $movimiento) {
            $idCuenta = (int) $movimiento->id_cuenta_contable;

            $debe = $this->validador->aCentimos(
                (string) $movimiento->debe
            );

            $haber = $this->validador->aCentimos(
                (string) $movimiento->haber
            );

            if (! isset($saldosPorCuenta[$idCuenta])) {
                $saldosPorCuenta[$idCuenta] = [
                    'id_cuenta_contable' => $idCuenta,
                    'codigo' => $movimiento->codigo,
                    'nombre' => $movimiento->nombre,
                    'tipo' => $movimiento->tipo,
                    'id_cuenta_padre' => $movimiento->id_cuenta_padre,
                    'saldo_centimos' => 0,
                ];
            }

            $saldosPorCuenta[$idCuenta]['saldo_centimos'] +=
                $this->calcularSaldoMovimiento(
                    $movimiento->tipo,
                    $debe,
                    $haber
                );
        }

        

        $resultado = $this->calculadora->calcular(
            array_values($saldosPorCuenta)
        );

        return [
            'id_comercio' => $idComercio,
            'fecha_corte' => $fechaCorte,
            ...$resultado,
            'cuentas' => array_values($saldosPorCuenta),
        ];


foreach ($saldosPorCuenta as $cuenta) {
    $tipo = strtoupper($cuenta['tipo']);

    $totales[$tipo] += $cuenta['saldo_centimos'];
}

$resultadoAcumulado = $totales['INGRESO'] - $totales['GASTO'];
$patrimonioTotal = $totales['PATRIMONIO'] + $resultadoAcumulado;

return [
    'id_comercio' => $idComercio,
    'fecha_corte' => $fechaCorte,
    'activos_centimos' => $totales['ACTIVO'],
    'pasivos_centimos' => $totales['PASIVO'],
    'patrimonio_centimos' => $patrimonioTotal,
    'resultado_acumulado_centimos' => $resultadoAcumulado,
    'cuentas' => array_values($saldosPorCuenta),
];

    }

    private function calcularSaldoMovimiento(
        string $tipo,
        int $debe,
        int $haber
    ): int {
        return match (strtoupper($tipo)) {
            'ACTIVO', 'GASTO' => $debe - $haber,
            'PASIVO', 'PATRIMONIO', 'INGRESO' => $haber - $debe,
            default => throw new InvalidArgumentException(
                'El tipo de cuenta contable no es válido.'
            ),
        };
    }
}
