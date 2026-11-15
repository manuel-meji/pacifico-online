<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use InvalidArgumentException;

final class CalculadoraBalanceGeneral
{
    public function calcular(array $cuentas): array
    {
        $totales = [
            'ACTIVO' => 0,
            'PASIVO' => 0,
            'PATRIMONIO' => 0,
            'INGRESO' => 0,
            'GASTO' => 0,
        ];

        foreach ($cuentas as $cuenta) {
            $tipo = strtoupper($cuenta['tipo']);

            if (! array_key_exists($tipo, $totales)) {
                throw new InvalidArgumentException(
                    'El tipo de cuenta contable no es válido.'
                );
            }

            $totales[$tipo] += $cuenta['saldo_centimos'];
        }

        $resultadoAcumulado = $totales['INGRESO'] - $totales['GASTO'];

        $patrimonioTotal = $totales['PATRIMONIO'] + $resultadoAcumulado;

        $activos = $totales['ACTIVO'];
        $pasivos = $totales['PASIVO'];

        return [
            'activos_centimos' => $activos,
            'pasivos_centimos' => $pasivos,
            'patrimonio_centimos' => $patrimonioTotal,
            'resultado_acumulado_centimos' => $resultadoAcumulado,
            'cuadra' => $activos === ($pasivos + $patrimonioTotal),
        ];
    }
}
