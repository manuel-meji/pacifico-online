<?php

declare(strict_types=1);

namespace App\Console\Commands\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\CostosProductosVendidosGatewayInterface;
use App\Contracts\M7_Contabilidad\SubpedidosM7GatewayInterface;
use App\Services\M7_Contabilidad\ComisionPendienteRepository;
use App\Services\M7_Contabilidad\DTOs\CostoVentaDTO;
use App\Services\M7_Contabilidad\ProcesarComisionPendienteService;
use App\Services\M7_Contabilidad\ProcesarSubpedidoDespachadoService;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class ConciliarDespachosM7 extends Command
{
    protected $signature = 'm7:conciliar-despachos
                            {--subpedido=* : IDs específicos de subpedidos a auditar}
                            {--reintentar : Contabiliza los despachos listos que ya tengan costo válido en M4}';

    protected $description = 'Concilia subpedidos despachados o entregados contra asientos de costo en M7 y permite reintentar los recuperables';

    public function __construct(
        private readonly SubpedidosM7GatewayInterface $subpedidosGateway,
        private readonly CostosProductosVendidosGatewayInterface $costosGateway,
        private readonly ProcesarSubpedidoDespachadoService $procesadorDespacho,
        private readonly ComisionPendienteRepository $comisionesPendientes,
        private readonly ProcesarComisionPendienteService $procesadorComision,
        private readonly ContextoComercio $contexto,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $idsFiltro = array_values(array_filter(
            array_map('intval', (array) $this->option('subpedido')),
            fn (int $id): bool => $id > 0
        ));

        $candidatos = $this->obtenerCandidatosSinAsiento($idsFiltro);

        if ($candidatos === []) {
            $this->info('M7 Conciliación: Todos los subpedidos despachados/entregados tienen su asiento de costo registrado.');

            return self::SUCCESS;
        }

        $reintentar = (bool) $this->option('reintentar');
        $filas = [];
        $listos = 0;
        $sinRespaldoM4 = 0;
        $reprocesados = 0;

        foreach ($candidatos as $registro) {
            $subpedidoId = (int) $registro->id_subpedido;

            try {
                $subpedidoM5 = $this->subpedidosGateway->obtenerSubpedido($subpedidoId);
                $estadoM5 = strtolower((string) ($subpedidoM5['estado_subpedido'] ?? ''));

                if (! in_array($estadoM5, ['despachado', 'entregado'], true)) {
                    $filas[] = [$subpedidoId, $registro->id_comercio, $estadoM5, 'N/A', 'OMITIDO_NO_DESPACHADO'];
                    continue;
                }

                $costoM4 = $this->costosGateway->obtenerCostosProductosVendidos($subpedidoId);
                $costoTotal = (float) ($costoM4['costo_total'] ?? -1);

                if ((int) ($costoM4['id_subpedido'] ?? 0) !== $subpedidoId || $costoTotal < 0) {
                    throw new \LogicException('M4 devolvió datos incompletos o costo negativo.');
                }

                $listos++;
                $estadoDiagnostico = 'LISTO_PARA_CONTABILIZAR';

                if ($reintentar) {
                    $this->contabilizarRecuperado($subpedidoM5, $costoTotal);
                    $reprocesados++;
                    $estadoDiagnostico = $costoTotal == 0.0
                        ? 'REGULARIZADO_COSTO_CERO'
                        : 'REGULARIZADO_CON_ASIENTO';
                }

                $filas[] = [
                    $subpedidoId,
                    $subpedidoM5['id_comercio'],
                    $estadoM5,
                    number_format($costoTotal, 2, '.', ''),
                    $estadoDiagnostico,
                ];
            } catch (Throwable $error) {
                $sinRespaldoM4++;
                $motivo = $error->getMessage();

                Log::warning('M7 Conciliación: subpedido despachado sin costo disponible en M4.', [
                    'id_subpedido' => $subpedidoId,
                    'id_comercio' => $registro->id_comercio,
                    'motivo' => $motivo,
                ]);

                $filas[] = [
                    $subpedidoId,
                    $registro->id_comercio,
                    $registro->estado_subpedido,
                    'SIN_DATO',
                    'SIN_RESPALDO_M4: ' . Str::limit($motivo, 45),
                ];
            }
        }

        $this->table(
            ['Subpedido', 'Comercio', 'Estado M5', 'Costo M4', 'Diagnóstico M7'],
            $filas
        );

        $this->warn(
            "Resumen conciliación -> Sin asiento detectados: " . count($candidatos) .
            " | Recuperables (M4 OK): {$listos}" .
            " | Sin respaldo M4 (alertados): {$sinRespaldoM4}" .
            ($reintentar ? " | Regularizados ahora: {$reprocesados}" : '')
        );

        return self::SUCCESS;
    }

    /**
     * Identifica subpedidos en estado despachado/entregado que aún no poseen
     * asiento de COSTO_VENTA en M7.
     *
     * @param list<int> $idsFiltro
     * @return list<object>
     */
    private function obtenerCandidatosSinAsiento(array $idsFiltro): array
    {
        $subpedidosConCosto = DB::table('asientos_contables')
            ->where('tipo', 'COSTO_VENTA')
            ->whereNotNull('id_subpedido')
            ->pluck('id_subpedido')
            ->all();

        $query = DB::table('pedidos_subpedidos')
            ->select(['id_subpedido', 'id_pedido', 'id_comercio', 'estado_subpedido', 'fecha_despacho', 'updated_at'])
            ->whereIn('estado_subpedido', ['despachado', 'entregado'])
            ->orderBy('id_subpedido');

        if ($subpedidosConCosto !== []) {
            $query->whereNotIn('id_subpedido', $subpedidosConCosto);
        }

        if ($idsFiltro !== []) {
            $query->whereIn('id_subpedido', $idsFiltro);
        }

        return $query->get()->all();
    }

    /**
     * Genera un UUID determinístico por subpedido para garantizar idempotencia
     * si el comando de conciliación se ejecuta múltiples veces.
     *
     * @param array<string, mixed> $subpedidoM5
     */
    private function contabilizarRecuperado(array $subpedidoM5, float $costoTotal): void
    {
        $subpedidoId = (int) $subpedidoM5['id_subpedido'];
        $pedidoId = (int) $subpedidoM5['id_pedido'];
        $comercioId = (int) $subpedidoM5['id_comercio'];
        $fecha = (string) ($subpedidoM5['fecha_despacho'] ?? now()->toDateTimeString());

        $eventoIdDeterministico = Str::uuid5(
            Str::uuid5(Str::NIL, 'pacifico-online.m7.conciliacion-despacho'),
            (string) $subpedidoId
        )->toString();

        $dto = new CostoVentaDTO(
            eventoId: $eventoIdDeterministico,
            fecha: $fecha,
            pedidoId: $pedidoId,
            subpedidoId: $subpedidoId,
            comercioId: $comercioId,
            costoTotal: number_format($costoTotal, 2, '.', ''),
        );

        $this->contexto->ejecutarComoSistema(
            fn () => $this->procesadorDespacho->procesar($dto)
        );

        $this->comisionesPendientes->registrar(
            eventoId: $eventoIdDeterministico,
            pedidoId: $pedidoId,
            subpedidoId: $subpedidoId,
            comercioId: $comercioId,
            fechaEvento: $fecha,
        );

        $this->procesadorComision->intentar($subpedidoId);
    }
}
