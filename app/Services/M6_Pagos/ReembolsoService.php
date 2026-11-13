<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Models\M6_Pagos\EstadoPago;
use App\Models\M6_Pagos\EstadoReembolso;
use App\Models\M6_Pagos\FacturaElectronica;
use App\Models\M6_Pagos\Pago;
use App\Models\M6_Pagos\Reembolso;
use App\Services\M6_Pagos\Clients\PedidoClient;
use App\Services\M6_Pagos\Clients\SubpedidoDTO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as ColeccionBase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * CU-007 Gestionar reembolso (RF-007, RN-006).
 *
 * - Decisión del subgrupo: un reembolso por subpedido, cada uno con su nota de
 *   crédito (la tabla reembolsos enlaza una sola nota). Un reembolso total de un
 *   pedido multicomercio crea un reembolso por cada subpedido con saldo.
 * - RN-006: requiere un pago confirmado y el monto no puede superar lo pagado
 *   del subpedido menos lo ya reembolsado. La fila del pago se bloquea mientras
 *   se calcula, así dos reembolsos simultáneos no pasan juntos el límite.
 * - La nota de crédito la emite DocumentoAjusteService.
 * - Si el subpedido todavía no tiene factura, el reembolso queda "solicitado" y
 *   se reintenta con reprocesarPendientes()
 */
class ReembolsoService
{
    public function __construct(
        private readonly PedidoClient $pedidos,
        private readonly DocumentoAjusteService $ajustes,
        private readonly NucleoServiceInterface $nucleo,
    ) {}

    /**
     * @return Collection<int, Reembolso> Uno por subpedido.
     *
     * @throws ErrorContratoException PAGO_NO_CONFIRMADO | REEMBOLSO_EXCEDE_MONTO | PEDIDO_NO_ENCONTRADO
     */
    public function gestionar(int $idPedido, ?int $idSubpedido, float $monto, string $motivo): Collection
    {
        $motivo = trim($motivo);

        if ($this->centimos($monto) <= 0 || $motivo === '') {
            throw ValidationException::withMessages(['monto' => 'El reembolso necesita un monto mayor que cero y un motivo.']);
        }

        // Montos por subpedido: se piden a M5 por su puerto (nunca a sus tablas).
        $subpedidos = collect($this->pedidos->obtenerDatosPedido($idPedido)->subpedidos)
            ->keyBy(fn (SubpedidoDTO $s): int => $s->idSubpedido);

        $reembolsos = DB::transaction(function () use ($idPedido, $idSubpedido, $monto, $motivo, $subpedidos): Collection {
            $pago = $this->pagoConfirmadoBloqueado($idPedido);
            $creados = new Collection;

            foreach ($this->repartir($pago, $subpedidos, $idSubpedido, $monto) as $idSub => $montoSub) {
                $creados->push($this->registrar($pago, $idSub, $montoSub, $motivo));
            }

            return $creados;
        });

        $this->publicarProcesados($reembolsos, $idPedido);

        return $reembolsos;
    }

    /**
     * Reintenta los reembolsos "solicitado" del pedido cuya factura ya existe.
     *
     * @return Collection<int, Reembolso> Los que quedaron procesados.
     */
    public function reprocesarPendientes(int $idPedido): Collection
    {
        $procesados = DB::transaction(function () use ($idPedido): Collection {
            $pendientes = Reembolso::query()
                ->whereNull('id_nota_credito')
                ->where('id_estado_reembolso', EstadoReembolso::idDe(EstadoReembolso::SOLICITADO))
                ->whereHas('pago', fn ($pago) => $pago->where('id_pedido', $idPedido))
                ->lockForUpdate()
                ->get();

            return $pendientes->filter(fn (Reembolso $reembolso): bool => $this->emitirNota($reembolso))->values();
        });

        $this->publicarProcesados($procesados, $idPedido);

        return $procesados;
    }

    private function pagoConfirmadoBloqueado(int $idPedido): Pago
    {
        return Pago::query()
            ->where('id_pedido', $idPedido)
            ->where('id_estado_pago', EstadoPago::idDe(EstadoPago::CONFIRMADO))
            ->latest('id_pago')
            ->lockForUpdate()
            ->first()
            ?? throw ErrorContratoException::de(CodigoErrorM6::PagoNoConfirmado);
    }

    /**
     * Decide cuánto se reembolsa de cada subpedido.
     *
     * - Con id_subpedido: reembolso parcial de ese subpedido.
     * - Sin id_subpedido: reembolso total; el monto debe ser el saldo de todo el pedido.
     *
     * @param  ColeccionBase<int, SubpedidoDTO>  $subpedidos
     * @return array<int, float> id_subpedido => monto
     */
    private function repartir(Pago $pago, ColeccionBase $subpedidos, ?int $idSubpedido, float $monto): array
    {
        $disponibles = $subpedidos->map(fn (SubpedidoDTO $s): int => $this->centimos($s->subtotal + $s->impuesto + $s->envio)
            - $this->reembolsadoCentimos($pago, $s->idSubpedido));

        if ($idSubpedido !== null) {
            if (! $disponibles->has($idSubpedido)) {
                throw ValidationException::withMessages(['id_subpedido' => 'El subpedido no pertenece a este pedido.']);
            }

            $this->exigirDisponible($this->centimos($monto), $disponibles[$idSubpedido]);

            return [$idSubpedido => round($monto, 2)];
        }

        $total = (int) $disponibles->sum();
        $this->exigirDisponible($this->centimos($monto), $total);

        if ($this->centimos($monto) !== $total) {
            throw ValidationException::withMessages([
                'id_subpedido' => 'Para un reembolso parcial indique el subpedido; sin subpedido el monto debe ser el saldo total del pedido.',
            ]);
        }

        return $disponibles->filter(fn (int $c): bool => $c > 0)->map(fn (int $c): float => $c / 100)->all();
    }

    private function exigirDisponible(int $solicitadoCentimos, int $disponibleCentimos): void
    {
        if ($solicitadoCentimos > $disponibleCentimos) {
            throw ErrorContratoException::de(CodigoErrorM6::ReembolsoExcedeMonto, null, ['disponible' => $disponibleCentimos / 100]);
        }
    }

    private function reembolsadoCentimos(Pago $pago, int $idSubpedido): int
    {
        return $this->centimos((float) Reembolso::query()
            ->where('id_pago', $pago->id_pago)
            ->where('id_subpedido', $idSubpedido)
            ->where('id_estado_reembolso', '!=', EstadoReembolso::idDe(EstadoReembolso::RECHAZADO))
            ->sum('monto'));
    }

    private function registrar(Pago $pago, int $idSubpedido, float $monto, string $motivo): Reembolso
    {
        $reembolso = Reembolso::query()->create([
            'id_pago' => $pago->id_pago,
            'id_subpedido' => $idSubpedido,
            'id_estado_reembolso' => EstadoReembolso::idDe(EstadoReembolso::SOLICITADO),
            'monto' => $monto,
            'motivo' => $motivo,
            'fecha_solicitud' => now(),
        ]);

        $this->emitirNota($reembolso);

        return $reembolso;
    }

    /** Emite la nota de crédito sobre la factura del subpedido. false si la factura aún no existe. */
    private function emitirNota(Reembolso $reembolso): bool
    {
        $factura = FacturaElectronica::query()->where('id_subpedido', $reembolso->id_subpedido)->first();

        if ($factura === null) {
            Log::warning("[M6_Pagos] El reembolso {$reembolso->id_reembolso} queda solicitado: el subpedido {$reembolso->id_subpedido} aún no tiene factura.");

            return false;
        }

        $nota = $this->ajustes->emitirNotaCredito($factura->id_factura_electronica, (float) $reembolso->monto, $reembolso->motivo);

        $reembolso->forceFill([
            'id_nota_credito' => $nota->id_nota_credito,
            'id_estado_reembolso' => EstadoReembolso::idDe(EstadoReembolso::PROCESADO),
            'fecha_procesado' => now(),
        ])->save();

        return true;
    }

    /** @param Collection<int, Reembolso> $reembolsos */
    private function publicarProcesados(Collection $reembolsos, int $idPedido): void
    {
        foreach ($reembolsos as $reembolso) {
            $reembolso->load(['estado', 'notaCredito.factura']);

            if ($reembolso->codigoEstado() !== EstadoReembolso::PROCESADO) {
                continue;
            }

            // Conexión M6–M7: el mismo armado que usa ConsultaEventosPagosService.
            event(FabricaEventosM6::reembolsoProcesado($reembolso));

            $this->auditar($reembolso);
        }
    }

    private function auditar(Reembolso $reembolso): void
    {
        try {
            $this->nucleo->registrarAuditoria('procesar_reembolso', 'reembolsos', $reembolso->id_reembolso, [
                'modulo' => 'M6_Pagos',
                'id_pago' => $reembolso->id_pago,
                'id_subpedido' => $reembolso->id_subpedido,
                'monto' => (float) $reembolso->monto,
                'id_nota_credito' => $reembolso->id_nota_credito,
            ]);
        } catch (Throwable $e) {
            Log::warning("[M6_Pagos] No se pudo registrar la auditoría del reembolso {$reembolso->id_reembolso}: {$e->getMessage()}");
        }
    }

    /** Se compara en céntimos para no arrastrar errores de punto flotante. */
    private function centimos(float $monto): int
    {
        return (int) round($monto * 100);
    }
}
