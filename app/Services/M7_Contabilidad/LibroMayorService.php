<?php

namespace App\Services\M7_Contabilidad;

use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\DB;
use LogicException;

class LibroMayorService
{
    public function __construct(
        private readonly ContextoComercio $contexto,
        private readonly PlanCuentasService $planCuentas
    ) {}

    /**
     * Consulta los movimientos de una cuenta y calcula su saldo.
     *
     * Filtros admitidos:
     * - id_comercio
     * - id_cuenta_contable
     * - fecha_desde
     * - fecha_hasta
     * - incluirAnulados
     */
    /**
     * Lista las cuentas activas disponibles para consultar el Libro Mayor.
     */
    public function listarCuentas(array $filtros = []): array
    {
        $idComercio = $this->resolverComercio(
            $filtros['id_comercio'] ?? null
        );

        $cuentas = $this->planCuentas->listar(
            $idComercio,
            true
        );

        return [
            'id_comercio' => $idComercio,
            'cuentas' => $cuentas->map(fn ($cuenta) => [
                'id_cuenta_contable' => $cuenta->id_cuenta_contable,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'tipo' => $cuenta->tipo,
                'naturaleza' => $cuenta->naturaleza,
            ])->values()->all(),
        ];
    }

    public function consultar(array $filtros): array
    {
        $idCuenta = (int) ($filtros['id_cuenta_contable'] ?? 0);

        if ($idCuenta <= 0) {
            throw new LogicException(
                'Debe indicar una cuenta contable válida.'
            );
        }

        $idComercio = $this->resolverComercio(
            $filtros['id_comercio'] ?? null
        );

        $cuenta = $this->planCuentas->obtener(
            $idComercio,
            $idCuenta
        );

        $fechaDesde = $filtros['fecha_desde'] ?? null;
        $fechaHasta = $filtros['fecha_hasta'] ?? null;

        $incluirAnulados = filter_var(
            $filtros['incluirAnulados'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $estados = $incluirAnulados
            ? ['REGISTRADO', 'ANULADO']
            : ['REGISTRADO'];

        $saldoInicialCentimos = 0;

        // Obtener movimientos anteriores al período.
        if ($fechaDesde !== null && $fechaDesde !== '') {
            $movimientosAnteriores = $this->consultaBase(
                $idComercio,
                $idCuenta,
                $estados
            )
                ->whereDate('a.fecha', '<', $fechaDesde)
                ->orderBy('a.fecha')
                ->orderBy('a.id_asiento')
                ->orderBy('d.numero_linea')
                ->get();

            foreach ($movimientosAnteriores as $movimiento) {
                $debe = $this->aCentimos($movimiento->debe);
                $haber = $this->aCentimos($movimiento->haber);

                $saldoInicialCentimos += $this->calcularVariacion(
                    $debe,
                    $haber,
                    $cuenta->naturaleza
                );
            }
        }

        // Obtener movimientos correspondientes al período.
        $consultaPeriodo = $this->consultaBase(
            $idComercio,
            $idCuenta,
            $estados
        );

        if ($fechaDesde !== null && $fechaDesde !== '') {
            $consultaPeriodo->whereDate(
                'a.fecha',
                '>=',
                $fechaDesde
            );
        }

        if ($fechaHasta !== null && $fechaHasta !== '') {
            $consultaPeriodo->whereDate(
                'a.fecha',
                '<=',
                $fechaHasta
            );
        }

        $movimientos = $consultaPeriodo
            ->orderBy('a.fecha')
            ->orderBy('a.id_asiento')
            ->orderBy('d.numero_linea')
            ->get();

        $saldoCentimos = $saldoInicialCentimos;
        $totalDebeCentimos = 0;
        $totalHaberCentimos = 0;
        $resultado = [];

        foreach ($movimientos as $movimiento) {
            $debe = $this->aCentimos($movimiento->debe);
            $haber = $this->aCentimos($movimiento->haber);

            $totalDebeCentimos += $debe;
            $totalHaberCentimos += $haber;

            $saldoCentimos += $this->calcularVariacion(
                $debe,
                $haber,
                $cuenta->naturaleza
            );

            $resultado[] = [
                'id_asiento' => $movimiento->id_asiento,
                'fecha' => $movimiento->fecha,
                'numero_linea' => $movimiento->numero_linea,
                'asiento' => 'ASI-'.str_pad(
                    (string) $movimiento->id_asiento,
                    4,
                    '0',
                    STR_PAD_LEFT
                ),
                'tipo' => $movimiento->tipo,
                'descripcion' => $movimiento->descripcion_detalle
                    ?? $movimiento->descripcion_asiento
                    ?? '',
                'estado' => $movimiento->estado,
                'debe' => $this->formatearImporte($debe),
                'haber' => $this->formatearImporte($haber),
                'saldo' => $this->formatearImporte(
                    $saldoCentimos
                ),
            ];
        }

        return [
            'cuenta' => [
                'id_cuenta_contable' => $cuenta->id_cuenta_contable,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'naturaleza' => $cuenta->naturaleza,
            ],
            'id_comercio' => $idComercio,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'incluir_anulados' => $incluirAnulados,
            'saldo_inicial' => $this->formatearImporte(
                $saldoInicialCentimos
            ),
            'total_debe' => $this->formatearImporte(
                $totalDebeCentimos
            ),
            'total_haber' => $this->formatearImporte(
                $totalHaberCentimos
            ),
            'saldo_final' => $this->formatearImporte(
                $saldoCentimos
            ),
            'movimientos' => $resultado,
        ];
    }

    /**
     * Construye la consulta de movimientos de una cuenta.
     * Se limita explícitamente al comercio correspondiente.
     */
    private function consultaBase(
        int $idComercio,
        int $idCuenta,
        array $estados
    ) {
        return DB::table('asientos_contables as a')
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
            ->where('d.id_cuenta_contable', $idCuenta)
            ->whereIn('a.estado', $estados)
            ->select([
                'a.id_asiento',
                'a.fecha',
                'a.descripcion as descripcion_asiento',
                'a.tipo',
                'a.estado',
                'd.numero_linea',
                'd.descripcion as descripcion_detalle',
                'd.debe',
                'd.haber',
            ]);
    }

    /**
     * Determina el comercio autorizado para la consulta.
     */
    private function resolverComercio(mixed $idComercioSolicitado): int
    {
        $idActual = $this->contexto->idActual();

        if ($this->contexto->esModoSistema()) {
            if ($idComercioSolicitado === null) {
                throw new LogicException(
                    'En modo sistema debe indicar el comercio que desea consultar.'
                );
            }

            $idComercio = (int) $idComercioSolicitado;
        } else {
            if ($idActual === null) {
                throw new LogicException(
                    'No existe un comercio activo para consultar el Libro Mayor.'
                );
            }

            $idComercio = $idActual;

            if (
                $idComercioSolicitado !== null
                && (int) $idComercioSolicitado !== $idActual
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

    /**
     * Calcula la variación del saldo según la naturaleza de la cuenta.
     */
    private function calcularVariacion(
        int $debe,
        int $haber,
        string $naturaleza
    ): int {
        if (strtoupper($naturaleza) === 'ACREEDORA') {
            return $haber - $debe;
        }

        return $debe - $haber;
    }

    /**
     * Convierte un importe decimal a céntimos sin utilizar float.
     */
    private function aCentimos(string|int|float|null $importe): int
    {
        $importe = (string) ($importe ?? '0');

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $importe)) {
            throw new LogicException(
                'Se encontró un importe contable con formato inválido.'
            );
        }

        [$entero, $decimal] = array_pad(
            explode('.', $importe, 2),
            2,
            ''
        );

        $decimal = str_pad($decimal, 2, '0');

        return ((int) $entero * 100) + (int) $decimal;
    }

    /**
     * Convierte céntimos a un importe decimal.
     */
    private function formatearImporte(int $centimos): string
    {
        $signo = $centimos < 0 ? '-' : '';
        $valorAbsoluto = abs($centimos);

        return $signo
            .intdiv($valorAbsoluto, 100)
            .'.'
            .str_pad(
                (string) ($valorAbsoluto % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }
}
