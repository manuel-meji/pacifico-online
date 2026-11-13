<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Contracts\M6_Pagos\PasarelaPagoInterface;
use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Models\M6_Pagos\Conciliacion;
use App\Models\M6_Pagos\EstadoConciliacion;
use App\Models\M6_Pagos\EstadoPago;
use App\Models\M6_Pagos\EstadoReembolso;
use App\Models\M6_Pagos\EstadoRevisionDiferencia;
use App\Models\M6_Pagos\FacturaElectronica;
use App\Models\M6_Pagos\Pago;
use App\Models\M6_Pagos\Reembolso;
use App\Models\M6_Pagos\TipoDiferencia;
use App\Services\M6_Pagos\Clients\ContabilidadClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CU-003 Conciliar pagos del período (RF-003, RN-007).
 *
 * - Compara los pagos confirmados del período contra lo que reporta la pasarela
 *   (por referencia_proveedor). Cada diferencia queda registrada como
 *   monto_distinto, faltante_en_pasarela o faltante_en_sistema, en estado
 *   pendiente_revision.
 * - Pide a M7 la confirmación del registro contable de cada pago. Si M7 no
 *   responde o no confirma, la conciliación NO falla: queda "parcial".
 */
class ConciliacionService
{
    public function __construct(
        private readonly PasarelaPagoInterface $pasarela,
        private readonly ContabilidadClient $contabilidad,
    ) {}

    /**
     * @return array{fecha_inicio: string, fecha_fin: string, total_cobrado: float, total_reembolsado: float, estado: string, diferencias: list<array<string, mixed>>}
     *
     * @throws ErrorContratoException PERIODO_INVALIDO
     */
    public function conciliar(string $fechaInicio, string $fechaFin): array
    {
        $inicio = Carbon::parse($fechaInicio)->startOfDay();
        $fin = Carbon::parse($fechaFin)->endOfDay();

        if ($fin->lt($inicio)) {
            throw ErrorContratoException::de(CodigoErrorM6::PeriodoInvalido);
        }

        $locales = Pago::query()
            ->where('id_estado_pago', EstadoPago::idDe(EstadoPago::CONFIRMADO))
            ->whereBetween('fecha_confirmacion', [$inicio, $fin])
            ->get();

        $reportados = collect($this->pasarela->reportarMovimientos($inicio->toDateString(), $fin->toDateString()))
            ->keyBy('referencia_proveedor');

        $diferencias = $this->comparar($locales, $reportados);
        $estado = $this->m7ConfirmaTodo($locales) ? EstadoConciliacion::COMPLETADA : EstadoConciliacion::PARCIAL;

        $totalCobrado = (int) $locales->sum(fn (Pago $p): int => $this->centimos((float) $p->monto));
        $totalPasarela = (int) $reportados->sum(fn (array $m): int => $this->centimos((float) $m['monto']));
        $totalReembolsado = $this->centimos((float) Reembolso::query()
            ->where('id_estado_reembolso', EstadoReembolso::idDe(EstadoReembolso::PROCESADO))
            ->whereBetween('fecha_procesado', [$inicio, $fin])
            ->sum('monto'));

        DB::transaction(function () use ($inicio, $fin, $estado, $totalCobrado, $totalPasarela, $diferencias): void {
            $conciliacion = Conciliacion::query()->create([
                'id_estado_conciliacion' => EstadoConciliacion::idDe($estado),
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin' => $fin->toDateString(),
                'total_cobrado_sistema' => $totalCobrado / 100,
                'total_reportado_pasarela' => $totalPasarela / 100,
                'fecha_generacion' => now(),
            ]);

            foreach ($diferencias as $diferencia) {
                $conciliacion->diferencias()->create([
                    'id_pago' => $diferencia['id_pago'],
                    'id_tipo_diferencia' => TipoDiferencia::idDe($diferencia['tipo']),
                    'id_estado_revision_diferencia' => EstadoRevisionDiferencia::idDe(EstadoRevisionDiferencia::PENDIENTE_REVISION),
                    'monto_diferencia' => ($diferencia['sistema'] - $diferencia['pasarela']) / 100,
                ]);
            }
        });

        // Esquema ReporteConciliacion del contrato.
        return [
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $fin->toDateString(),
            'total_cobrado' => $totalCobrado / 100,
            'total_reembolsado' => $totalReembolsado / 100,
            'estado' => EstadoConciliacion::valorContrato($estado),
            'diferencias' => array_map(fn (array $d): array => [
                'id_pago' => $d['id_pago'],
                'monto_sistema' => $d['sistema'] / 100,
                'monto_pasarela' => $d['pasarela'] / 100,
                'estado' => EstadoRevisionDiferencia::valorContrato(EstadoRevisionDiferencia::PENDIENTE_REVISION),
            ], $diferencias),
        ];
    }

    /**
     * @param  Collection<int, Pago>  $locales
     * @param  \Illuminate\Support\Collection<string, array{referencia_proveedor: string, monto: float}>  $reportados
     * @return list<array{id_pago: int, tipo: string, sistema: int, pasarela: int}> Montos en céntimos.
     */
    private function comparar(Collection $locales, \Illuminate\Support\Collection $reportados): array
    {
        $diferencias = [];

        foreach ($locales as $pago) {
            $sistema = $this->centimos((float) $pago->monto);
            $movimiento = $pago->referencia_proveedor !== null ? $reportados->get($pago->referencia_proveedor) : null;

            if ($movimiento === null) {
                $diferencias[] = ['id_pago' => $pago->id_pago, 'tipo' => TipoDiferencia::FALTANTE_EN_PASARELA, 'sistema' => $sistema, 'pasarela' => 0];

                continue;
            }

            $pasarela = $this->centimos((float) $movimiento['monto']);

            if ($sistema !== $pasarela) {
                $diferencias[] = ['id_pago' => $pago->id_pago, 'tipo' => TipoDiferencia::MONTO_DISTINTO, 'sistema' => $sistema, 'pasarela' => $pasarela];
            }
        }

        // Lo que la pasarela reporta y M6 no tiene como pago confirmado del período.
        $referenciasLocales = $locales->pluck('referencia_proveedor')->filter()->all();

        foreach ($reportados->except($referenciasLocales) as $referencia => $movimiento) {
            $pago = Pago::query()->where('referencia_proveedor', $referencia)->first();
            $pasarela = $this->centimos((float) $movimiento['monto']);

            // diferencias_conciliacion.id_pago es obligatorio: sin pago local no se puede guardar.
            if ($pago === null || $pasarela === 0) {
                Log::warning("[M6_Pagos] La pasarela reporta la referencia {$referencia}, que no existe en M6: no se puede registrar la diferencia.");

                continue;
            }

            $diferencias[] = ['id_pago' => $pago->id_pago, 'tipo' => TipoDiferencia::FALTANTE_EN_SISTEMA, 'sistema' => 0, 'pasarela' => $pasarela];
        }

        return $diferencias;
    }

    /** @param Collection<int, Pago> $pagos */
    private function m7ConfirmaTodo(Collection $pagos): bool
    {
        try {
            foreach ($pagos as $pago) {
                if (! $this->contabilidad->consultarRegistroContablePago($pago->id_pago)) {
                    return false;
                }

                if (! $this->m7RegistroTodasLasFacturas($pago)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable $e) {
            Log::warning("[M6_Pagos] M7 no confirmó los registros contables; la conciliación queda parcial: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Conexión M6–M7 (pago multicomercio): un pago está registrado en M7 solo si
     * cada factura que M6 emitió para ese pago tiene su cobro en M7. M6 es la
     * fuente de qué facturas existen; M7 informa los subpedidos con cobro.
     * Si M7 no informa ese detalle (null), se mantiene el criterio anterior.
     */
    private function m7RegistroTodasLasFacturas(Pago $pago): bool
    {
        $registrados = $this->contabilidad->subpedidosConCobro($pago->id_pago);

        if ($registrados === null) {
            return true;
        }

        $facturados = FacturaElectronica::query()
            ->where('id_pago', $pago->id_pago)
            ->pluck('id_subpedido')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $faltantes = array_values(array_diff($facturados, $registrados));

        if ($facturados === [] || $faltantes !== []) {
            Log::warning("[M6_Pagos] M7 no registró el cobro de todas las facturas del pago {$pago->id_pago}.", [
                'facturados' => $facturados,
                'registrados_en_m7' => $registrados,
                'faltantes' => $facturados === [] ? 'el pago aún no tiene facturas' : $faltantes,
            ]);

            return false;
        }

        return true;
    }

    /** Se compara en céntimos para no arrastrar errores de punto flotante. */
    private function centimos(float $monto): int
    {
        return (int) round($monto * 100);
    }
}
