<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\SubpedidosM7GatewayInterface;
use App\Contracts\M7_Contabilidad\ProcesadorCuentaPagarComercioInterface;
use App\Models\M7_Contabilidad\AsientoContable;
use App\Models\M7_Contabilidad\DetalleAsiento;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ProcesarCuentaPagarComercioService implements ProcesadorCuentaPagarComercioInterface
{
    public function __construct(
        private readonly SubpedidosM7GatewayInterface $subpedidos,
        private readonly ComisionPendienteRepository $comisiones,
        private readonly CuentaContableRepository $cuentas,
        private readonly AsientoRepository $asientos,
        private readonly LibroPlataformaService $plataforma,
        private readonly ContextoComercio $contexto,
        private readonly ValidadorPartidaDoble $validador,
    ) {}

    public function procesar(
        string $eventoId,
        int $pedidoId,
        int $subpedidoId,
        int $comercioId,
        string $fecha,
    ): bool {
        $subpedido = $this->subpedidos->obtenerSubpedido($subpedidoId);

        if ((int) $subpedido['id_pedido'] !== $pedidoId) {
            throw new LogicException(
                'El pedido del evento no coincide con el subpedido de M5.'
            );
        }

        if ((int) $subpedido['id_comercio'] !== $comercioId) {
            throw new LogicException(
                'El comercio del evento no coincide con el subpedido de M5.'
            );
        }

        $comision = $this->comisiones->obtenerPorSubpedido($subpedidoId);

        if (
            $comision === null
            || $comision->estado === 'PENDIENTE'
            || $comision->monto_comision === null
        ) {
            return false;
        }

        if ($comision->estado === 'PROCESADO') {
            return true;
        }

        $comisionCentimos = $this->aCentimos(
            (float) $comision->monto_comision
        );

        $totalCentimos =
            $this->aCentimos((float) $subpedido['subtotal'])
            + $this->aCentimos((float) $subpedido['impuesto'])
            + $this->aCentimos((float) $subpedido['costo_envio']);

        if ($comisionCentimos > $totalCentimos) {
            throw new LogicException(
                'La comisión no puede superar el total del subpedido.'
            );
        }

        $registroFactura = DB::table('registros_financieros_m6')
            ->where('tipo', 'FACTURA')
            ->where('id_subpedido', $subpedidoId)
            ->where('id_comercio', $comercioId)
            ->first();

        if ($registroFactura === null) {
            return false;
        }

        $pagoId = (int) $registroFactura->id_pago;

        $this->contexto->ejecutarComoSistema(
            function () use (
                $eventoId,
                $pedidoId,
                $subpedidoId,
                $comercioId,
                $fecha,
                $pagoId,
                $comisionCentimos
            ): void {
                DB::transaction(function () use (
                    $eventoId,
                    $pedidoId,
                    $subpedidoId,
                    $comercioId,
                    $fecha,
                    $pagoId,
                    $comisionCentimos
                ): void {
                    if (
                        DB::table('asientos_contables')
                            ->where('id_subpedido', $subpedidoId)
                            ->where('tipo', 'COMISION')
                            ->where('estado', 'REGISTRADO')
                            ->exists()
                    ) {
                        $this->comisiones->marcarProcesada($subpedidoId);

                        return;
                    }

                    $cuentas = $this->cuentas->bloquearPorCodigos(
                        $comercioId,
                        ['1.1.04', '5.2.01']
                    );

                    $asiento = new AsientoContable(
                        0,
                        $comercioId,
                        $pedidoId,
                        $subpedidoId,
                        $eventoId,
                        'SubpedidoEntregado',
                        'COMISION',
                        Carbon::parse($fecha)->utc()->format('Y-m-d H:i:s'),
                        $pagoId,
                    );

                    $importe = $this->aDecimal($comisionCentimos);

                    $asiento->agregarDetalle(
                        new DetalleAsiento(
                            $cuentas['5.2.01'],
                            $importe,
                            '0'
                        )
                    );

                    $asiento->agregarDetalle(
                        new DetalleAsiento(
                            $cuentas['1.1.04'],
                            '0',
                            $importe
                        )
                    );

                    $this->asientos->guardar($asiento);

                    $this->plataforma->registrar(
                        eventoId: $eventoId,
                        tipoEvento: 'SubpedidoEntregado',
                        tipo: 'COMISION',
                        pagoId: $pagoId,
                        pedidoId: $pedidoId,
                        comercioId: $comercioId,
                        subpedidoId: $subpedidoId,
                        fecha: $fecha,
                        movimientos: [
                            [
                                'codigo' => 'P-COMERCIOS',
                                'debe' => $comisionCentimos,
                                'haber' => 0,
                            ],
                            [
                                'codigo' => 'P-COMISION',
                                'debe' => 0,
                                'haber' => $comisionCentimos,
                            ],
                        ],
                    );

                    $this->comisiones->marcarProcesada($subpedidoId);
                });
            }
        );

        return true;
    }

    private function aCentimos(float $monto): int
    {
        if (
            ! is_finite($monto)
            || $monto < 0
            || abs($monto - round($monto, 2)) > 0.000001
        ) {
            throw new LogicException(
                'El importe debe tener como máximo dos decimales.'
            );
        }

        return $this->validador->aCentimos(
            number_format($monto, 2, '.', '')
        );
    }

    private function aDecimal(int $centimos): string
    {
        return intdiv($centimos, 100)
            .'.'
            .str_pad(
                (string) ($centimos % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }
}