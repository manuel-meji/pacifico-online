<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\DirectorioComerciosM7GatewayInterface;
use App\Models\M7_Contabilidad\CentroCosto;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OverflowException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use UnexpectedValueException;

final class ConsultasOperativasService
{
    public function __construct(
        private readonly DirectorioComerciosM7GatewayInterface $directorio,
    ) {}

    /**
     * Comercios con plan de cuentas en M7, identificados por su nombre comercial en M2.
     *
     * @return list<array{id_comercio: int, nombre: string, nombre_disponible: bool}>
     */
    public function comercios(): array
    {
        $ids = DB::table('cuentas_contables')->distinct()->orderBy('id_comercio')
            ->pluck('id_comercio')->map(fn ($id): int => (int) $id)->all();
        if ($ids === []) {
            return [];
        }

        try {
            $nombres = $this->directorio->obtenerNombres($ids);
        } catch (Throwable $error) {
            Log::warning('M7 no pudo obtener los nombres de comercios desde M2.', ['error' => $error->getMessage()]);
            $nombres = [];
        }

        $comercios = array_map(fn (int $id): array => [
            'id_comercio' => $id,
            'nombre' => $nombres[$id] ?? 'Comercio #'.$id,
            'nombre_disponible' => isset($nombres[$id]),
        ], $ids);
        usort($comercios, fn (array $a, array $b): int => [$b['nombre_disponible'], mb_strtolower($a['nombre']), $a['id_comercio']]
            <=> [$a['nombre_disponible'], mb_strtolower($b['nombre']), $b['id_comercio']]);

        return $comercios;
    }

    /**
     * @return array{id_comercio: int, cuentas: list<array{id_cuenta_contable: int, codigo: string, nombre: string, tipo: string, saldo: string}>, resumen: array{por_cobrar: string, por_pagar: string}}
     */
    public function saldos(int $idComercio): array
    {
        $cuentas = DB::table('cuentas_contables')
            ->where('id_comercio', $idComercio)
            ->where(function (Builder $consulta): void {
                foreach (['1.1.02', '1.1.04', '2.1.01'] as $codigo) {
                    $consulta->orWhere('codigo', $codigo)->orWhere('codigo', 'like', $codigo.'.%');
                }
            })
            ->orderBy('codigo')->get();
        $saldos = DB::table('detalle_asientos as d')
            ->join('asientos_contables as a', 'a.id_asiento', '=', 'd.id_asiento')
            ->join('cuentas_contables as c', 'c.id_cuenta_contable', '=', 'd.id_cuenta_contable')
            ->where('a.id_comercio', $idComercio)->where('c.id_comercio', $idComercio)
            ->where('a.estado', 'REGISTRADO')
            ->whereIn('c.id_cuenta_contable', $cuentas->pluck('id_cuenta_contable'))
            ->groupBy('c.id_cuenta_contable')
            ->selectRaw('c.id_cuenta_contable, SUM(d.debe - d.haber) as saldo')
            ->get()->keyBy('id_cuenta_contable');

        $porCobrar = 0;
        $porPagar = 0;
        $filas = [];
        foreach ($cuentas as $cuenta) {
            $esPorPagar = $cuenta->codigo === '2.1.01' || str_starts_with($cuenta->codigo, '2.1.01.');
            $saldo = $this->centimos($saldos->get($cuenta->id_cuenta_contable)?->saldo ?? 0);
            if ($esPorPagar) {
                $saldo = -$saldo;
                $porPagar += $saldo;
            } else {
                $porCobrar += $saldo;
            }
            $filas[] = [
                'id_cuenta_contable' => (int) $cuenta->id_cuenta_contable,
                'codigo' => $cuenta->codigo, 'nombre' => $cuenta->nombre,
                'tipo' => $esPorPagar ? 'Por pagar' : 'Por cobrar',
                'saldo' => $this->importe($saldo),
            ];
        }

        return [
            'id_comercio' => $idComercio,
            'cuentas' => $filas,
            'resumen' => ['por_cobrar' => $this->importe($porCobrar), 'por_pagar' => $this->importe($porPagar)],
        ];
    }

    /**
     * Rentabilidad por centro: ingresos (haber − debe en INGRESO), costos (debe − haber en GASTO) y utilidad.
     * Las líneas sin centro del comercio se agrupan aparte sin repartirse entre centros.
     *
     * @return array{id_comercio: int, fecha_desde: string|null, fecha_hasta: string|null, hay_movimientos: bool, centros: list<array{id_centro_costo: int, codigo: string, nombre: string, activo: bool, ingresos: string, costos: string, utilidad: string, margen: string|null}>, sin_centro: array{ingresos: string, costos: string, utilidad: string, margen: string|null}|null, resumen: array{cantidad: int, activos: int, ingresos: string, costos: string, utilidad: string, margen: string|null}}
     */
    public function centros(int $idComercio, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $centros = DB::table('centros_costo')->where('id_comercio', $idComercio)->orderBy('codigo')->get();
        $resultados = $this->consultaResultados($idComercio, $fechaDesde, $fechaHasta)
            ->groupBy('cc.id_centro_costo')
            ->selectRaw("cc.id_centro_costo, SUM(CASE WHEN c.tipo = 'INGRESO' THEN d.haber - d.debe ELSE 0 END) as ingresos")
            ->selectRaw("SUM(CASE WHEN c.tipo = 'GASTO' THEN d.debe - d.haber ELSE 0 END) as costos")
            ->selectRaw('COUNT(*) as lineas')
            ->get();
        $porCentro = $resultados->whereNotNull('id_centro_costo')->keyBy('id_centro_costo');
        $sinCentro = $resultados->first(fn (object $fila): bool => $fila->id_centro_costo === null);

        $totalIngresos = 0;
        $totalCostos = 0;
        $filas = [];
        foreach ($centros as $centro) {
            $resultado = $porCentro->get($centro->id_centro_costo);
            $ingresos = $this->centimos($resultado?->ingresos ?? 0);
            $costos = $this->centimos($resultado?->costos ?? 0);
            $totalIngresos += $ingresos;
            $totalCostos += $costos;
            $filas[] = [
                'id_centro_costo' => (int) $centro->id_centro_costo,
                'codigo' => $centro->codigo, 'nombre' => $centro->nombre, 'activo' => (bool) $centro->activo,
                ...$this->rentabilidad($ingresos, $costos),
            ];
        }
        $grupoSinCentro = null;
        if ($sinCentro !== null) {
            $ingresos = $this->centimos($sinCentro->ingresos);
            $costos = $this->centimos($sinCentro->costos);
            $totalIngresos += $ingresos;
            $totalCostos += $costos;
            $grupoSinCentro = $this->rentabilidad($ingresos, $costos);
        }

        return [
            'id_comercio' => $idComercio, 'fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta,
            'hay_movimientos' => $resultados->sum('lineas') > 0,
            'centros' => $filas, 'sin_centro' => $grupoSinCentro,
            'resumen' => [
                'cantidad' => count($filas),
                'activos' => count(array_filter($filas, fn (array $centro): bool => $centro['activo'])),
                ...$this->rentabilidad($totalIngresos, $totalCostos),
            ],
        ];
    }

    /**
     * Detalle de las líneas de ingreso y gasto de un centro; con $idCentro nulo devuelve las líneas sin centro.
     * El acumulado es la utilidad parcial: los ingresos suman y los costos restan.
     *
     * @return array{id_comercio: int, fecha_desde: string|null, fecha_hasta: string|null, centro: array{id_centro_costo: int, codigo: string, nombre: string, activo: bool}|null, ingresos: string, costos: string, utilidad: string, movimientos: list<array<string, int|string|null>>}
     */
    public function movimientosCentro(int $idComercio, ?int $idCentro, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $centro = $idCentro === null ? null : CentroCosto::query()->where('id_comercio', $idComercio)
            ->where('id_centro_costo', $idCentro)->firstOrFail();
        $consulta = $this->consultaResultados($idComercio, $fechaDesde, $fechaHasta);
        $idCentro === null ? $consulta->whereNull('cc.id_centro_costo') : $consulta->where('cc.id_centro_costo', $idCentro);
        $registros = $consulta
            ->orderBy('a.fecha')->orderBy('a.id_asiento')->orderBy('d.numero_linea')
            ->select([
                'd.id_detalle_asiento', 'd.numero_linea', 'd.debe', 'd.haber',
                'd.descripcion as descripcion_detalle',
                'a.id_asiento', 'a.fecha', 'a.tipo', 'a.descripcion as descripcion_asiento',
                'a.id_pedido', 'a.id_subpedido', 'a.id_pago',
                'c.codigo', 'c.nombre', 'c.tipo as tipo_cuenta',
            ])->get();
        $ingresos = 0;
        $costos = 0;
        $movimientos = [];
        foreach ($registros as $registro) {
            $debe = $this->centimos($registro->debe);
            $haber = $this->centimos($registro->haber);
            if ($registro->tipo_cuenta === 'INGRESO') {
                $ingresos += $haber - $debe;
            } else {
                $costos += $debe - $haber;
            }
            $movimientos[] = [
                'id_detalle_asiento' => (int) $registro->id_detalle_asiento,
                'id_asiento' => (int) $registro->id_asiento,
                'numero_linea' => (int) $registro->numero_linea,
                'fecha' => $registro->fecha, 'tipo' => $registro->tipo,
                'descripcion' => $registro->descripcion_detalle ?? $registro->descripcion_asiento ?? '',
                'codigo_cuenta' => $registro->codigo, 'nombre_cuenta' => $registro->nombre,
                'tipo_cuenta' => $registro->tipo_cuenta,
                'id_pedido' => $registro->id_pedido === null ? null : (int) $registro->id_pedido,
                'id_subpedido' => $registro->id_subpedido === null ? null : (int) $registro->id_subpedido,
                'id_pago' => $registro->id_pago === null ? null : (int) $registro->id_pago,
                'debe' => $this->importe($debe), 'haber' => $this->importe($haber),
                'saldo' => $this->importe($ingresos - $costos),
            ];
        }

        return [
            'id_comercio' => $idComercio, 'fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta,
            'centro' => $centro === null ? null : [
                'id_centro_costo' => (int) $centro->id_centro_costo,
                'codigo' => $centro->codigo, 'nombre' => $centro->nombre, 'activo' => (bool) $centro->activo,
            ],
            'ingresos' => $this->importe($ingresos), 'costos' => $this->importe($costos),
            'utilidad' => $this->importe($ingresos - $costos), 'movimientos' => $movimientos,
        ];
    }

    /**
     * Asiento completo con todas sus líneas, para consultarlo desde los detalles de los reportes.
     *
     * @return array{id_asiento: int, id_comercio: int, fecha: string, tipo: string, tipo_evento: string|null, descripcion: string|null, estado: string, id_pedido: int|null, id_subpedido: int|null, id_pago: int|null, id_asiento_original: int|null, total_debe: string, total_haber: string, cuadrado: bool, lineas: list<array{numero_linea: int, codigo_cuenta: string, nombre_cuenta: string, tipo_cuenta: string, centro: array{codigo: string, nombre: string}|null, descripcion: string|null, debe: string, haber: string}>}
     */
    public function asiento(int $idComercio, int $idAsiento): array
    {
        $asiento = DB::table('asientos_contables')->where('id_comercio', $idComercio)
            ->where('id_asiento', $idAsiento)->first();
        if ($asiento === null) {
            throw new NotFoundHttpException('El asiento no existe para este comercio.');
        }
        $registros = DB::table('detalle_asientos as d')
            ->join('cuentas_contables as c', function (JoinClause $union) use ($idComercio): void {
                $union->on('c.id_cuenta_contable', '=', 'd.id_cuenta_contable')->where('c.id_comercio', $idComercio);
            })
            ->leftJoin('centros_costo as cc', function (JoinClause $union) use ($idComercio): void {
                $union->on('cc.id_centro_costo', '=', 'd.id_centro_costo')->where('cc.id_comercio', $idComercio);
            })
            ->where('d.id_asiento', $idAsiento)->orderBy('d.numero_linea')
            ->select([
                'd.numero_linea', 'd.debe', 'd.haber', 'd.descripcion',
                'c.codigo', 'c.nombre', 'c.tipo as tipo_cuenta', 'cc.codigo as codigo_centro', 'cc.nombre as nombre_centro',
            ])->get();
        $totalDebe = 0;
        $totalHaber = 0;
        $lineas = [];
        foreach ($registros as $registro) {
            $debe = $this->centimos($registro->debe);
            $haber = $this->centimos($registro->haber);
            $totalDebe += $debe;
            $totalHaber += $haber;
            $lineas[] = [
                'numero_linea' => (int) $registro->numero_linea,
                'codigo_cuenta' => $registro->codigo, 'nombre_cuenta' => $registro->nombre, 'tipo_cuenta' => $registro->tipo_cuenta,
                'centro' => $registro->codigo_centro === null ? null : ['codigo' => $registro->codigo_centro, 'nombre' => $registro->nombre_centro],
                'descripcion' => $registro->descripcion,
                'debe' => $this->importe($debe), 'haber' => $this->importe($haber),
            ];
        }
        $entero = fn (int|string|null $valor): ?int => $valor === null ? null : (int) $valor;

        return [
            'id_asiento' => (int) $asiento->id_asiento, 'id_comercio' => $idComercio,
            'fecha' => (string) $asiento->fecha, 'tipo' => $asiento->tipo, 'tipo_evento' => $asiento->tipo_evento,
            'descripcion' => $asiento->descripcion, 'estado' => $asiento->estado,
            'id_pedido' => $entero($asiento->id_pedido), 'id_subpedido' => $entero($asiento->id_subpedido),
            'id_pago' => $entero($asiento->id_pago), 'id_asiento_original' => $entero($asiento->id_asiento_original),
            'total_debe' => $this->importe($totalDebe), 'total_haber' => $this->importe($totalHaber),
            'cuadrado' => $lineas !== [] && $totalDebe === $totalHaber, 'lineas' => $lineas,
        ];
    }

    private function consultaResultados(int $idComercio, ?string $fechaDesde, ?string $fechaHasta): Builder
    {
        return DB::table('detalle_asientos as d')
            ->join('asientos_contables as a', 'a.id_asiento', '=', 'd.id_asiento')
            ->join('cuentas_contables as c', 'c.id_cuenta_contable', '=', 'd.id_cuenta_contable')
            ->leftJoin('centros_costo as cc', function (JoinClause $union) use ($idComercio): void {
                $union->on('cc.id_centro_costo', '=', 'd.id_centro_costo')->where('cc.id_comercio', $idComercio);
            })
            ->where('a.id_comercio', $idComercio)->where('c.id_comercio', $idComercio)
            ->where('a.estado', 'REGISTRADO')->whereIn('c.tipo', ['INGRESO', 'GASTO'])
            ->when($fechaDesde !== null, fn (Builder $consulta) => $consulta->whereDate('a.fecha', '>=', $fechaDesde))
            ->when($fechaHasta !== null, fn (Builder $consulta) => $consulta->whereDate('a.fecha', '<=', $fechaHasta));
    }

    /**
     * El margen es la utilidad sobre ingresos, en porcentaje redondeado a dos decimales; sin ingresos positivos no aplica.
     *
     * @return array{ingresos: string, costos: string, utilidad: string, margen: string|null}
     */
    private function rentabilidad(int $ingresos, int $costos): array
    {
        $utilidad = $ingresos - $costos;
        $margen = null;
        if ($ingresos > 0) {
            $centesimas = intdiv(abs($utilidad) * 10000 * 2 + $ingresos, $ingresos * 2);
            $margen = $this->importe($utilidad < 0 ? -$centesimas : $centesimas);
        }

        return [
            'ingresos' => $this->importe($ingresos), 'costos' => $this->importe($costos),
            'utilidad' => $this->importe($utilidad), 'margen' => $margen,
        ];
    }

    private function centimos(int|float|string $importe): int
    {
        $decimal = is_float($importe) ? number_format($importe, 2, '.', '') : (string) $importe;
        if (! preg_match('/\A(-?)([0-9]+)(?:\.([0-9]{1,2}))?\z/', $decimal, $partes)) {
            throw new UnexpectedValueException('El saldo contable no es un decimal válido.');
        }
        $centimos = ltrim($partes[2].str_pad($partes[3] ?? '', 2, '0'), '0');
        $limite = (string) PHP_INT_MAX;
        if (strlen($centimos) > strlen($limite)
            || (strlen($centimos) === strlen($limite) && strcmp($centimos, $limite) > 0)) {
            throw new OverflowException('El saldo contable excede el rango permitido.');
        }

        return ($partes[1] === '-' ? -1 : 1) * (int) $centimos;
    }

    private function importe(int $centimos): string
    {
        return ($centimos < 0 ? '-' : '').intdiv(abs($centimos), 100).'.'.str_pad((string) (abs($centimos) % 100), 2, '0', STR_PAD_LEFT);
    }
}
