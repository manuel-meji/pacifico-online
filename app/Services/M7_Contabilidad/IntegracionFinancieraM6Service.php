<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Events\M6_Pagos\FacturaGenerada;
use App\Events\M6_Pagos\PagoConfirmado;
use App\Events\M6_Pagos\ReembolsoProcesado;
use App\Models\M7_Contabilidad\AsientoContable;
use App\Models\M7_Contabilidad\DetalleAsiento;
use App\Services\Nucleo\ContextoComercio;
use Brick\Math\BigInteger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class IntegracionFinancieraM6Service
{
    public function __construct(
        private readonly EventoProcesadoRepository $eventos,
        private readonly AsientoRepository $asientos,
        private readonly CuentaContableRepository $cuentas,
        private readonly ValidadorPartidaDoble $validador,
        private readonly ContextoComercio $contexto,
        private readonly LibroPlataformaService $plataforma,
    ) {}

    public function procesarPago(PagoConfirmado $pago): void
    {
        $this->validarIdentificadores($pago->idEvento, $pago->fecha, [$pago->idPago, $pago->idPedido]);
        $monto = $this->centimos($pago->monto);
        if ($monto <= 0) {
            throw new InvalidArgumentException('El cobro debe tener un monto positivo.');
        }
        $fecha = Carbon::parse($pago->fecha)->utc()->format('Y-m-d H:i:s');
        $huella = $this->huella([$pago->idPago, $pago->idPedido, $fecha, $monto]);
        $this->eventos->procesar($pago->idEvento, 'PagoConfirmado', function (bool $yaProcesado) use ($pago, $monto, $fecha, $huella): void {
            $this->exigirSistema();
            DB::table('pagos_contables_m6')->insertOrIgnore([
                'id_pago' => $pago->idPago, 'id_pedido' => $pago->idPedido,
                'monto_centimos' => $monto, 'fecha' => $fecha, 'huella' => $huella,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $registro = $this->bloquearPago($pago->idPago, $pago->idPedido, false);
            if (! hash_equals($registro->huella, $huella)) {
                throw new LogicException('El pago ya existe con datos financieros diferentes.');
            }
            if ($registro->id_asiento_plataforma !== null) {
                return;
            }
            if ($yaProcesado) {
                throw new LogicException('El evento de pago ya procesado no tiene su asiento global.');
            }
            $asiento = $this->plataforma->registrar(
                $pago->idEvento, 'PagoConfirmado', 'COBRO', $pago->idPago, $pago->idPedido,
                null, null, $fecha, [
                    ['codigo' => 'P-BANCOS', 'debe' => $monto, 'haber' => 0],
                    ['codigo' => 'P-PENDIENTE', 'debe' => 0, 'haber' => $monto],
                ],
            );
            DB::table('pagos_contables_m6')->where('id_pago', $pago->idPago)->update([
                'id_asiento_plataforma' => $asiento, 'updated_at' => now(),
            ]);
        });
    }

    public function procesarFactura(FacturaGenerada $factura): void
    {
        $this->validarIdentificadores($factura->idEvento, $factura->fecha, [
            $factura->idFactura, $factura->idPedido, $factura->idSubpedido, $factura->idComercio, $factura->idPago,
        ]);
        if ($factura->subtotal === null || $factura->impuesto === null || $factura->envio === null) {
            throw new InvalidArgumentException('La factura no contiene el desglose completo.');
        }

        $componentes = [
            'subtotal' => $this->centimos($factura->subtotal),
            'impuesto' => $this->centimos($factura->impuesto),
            'envio' => $this->centimos($factura->envio),
        ];
        $total = $this->centimos($factura->monto);
        if ($total <= 0 || array_sum($componentes) !== $total) {
            throw new InvalidArgumentException('El desglose de la factura no coincide con su monto.');
        }

        $referencia = "factura:{$factura->idFactura}";
        $huella = $this->huella([$factura->idFactura, $factura->idPago, $factura->idPedido, $factura->idSubpedido, $factura->idComercio, Carbon::parse($factura->fecha)->utc()->format('Y-m-d H:i:s'), $componentes]);

        $this->eventos->procesar($factura->idEvento, 'FacturaGenerada', function (bool $yaProcesado) use ($factura, $componentes, $total, $referencia, $huella): void {
            $this->exigirSistema();
            $pago = $this->bloquearPago($factura->idPago, $factura->idPedido);
            if ($this->yaRegistrado($referencia, $huella, $yaProcesado)) {
                return;
            }
            $distribuido = (int) DB::table('registros_financieros_m6')
                ->where('id_pago', $factura->idPago)->where('tipo', 'FACTURA')
                ->selectRaw('COALESCE(SUM(subtotal_centimos + impuesto_centimos + envio_centimos), 0) AS total')->value('total');
            if ($total > (int) $pago->monto_centimos - $distribuido) {
                throw new LogicException('Las facturas exceden el cobro; se requiere revisión sin distribuir el exceso.');
            }
            if (DB::table('registros_financieros_m6')->where('tipo', 'FACTURA')
                ->where('id_pago', $factura->idPago)->where('id_subpedido', $factura->idSubpedido)->exists()) {
                throw new LogicException('El subpedido ya tiene otra factura distribuida.');
            }
            $cuentas = $this->cuentas->bloquearPorCodigos($factura->idComercio, ['1.1.04', '1.1.02', '2.1.02', '4.1.02']);

            $venta = $this->asientos->buscarPorSubpedido($factura->idComercio, $factura->idSubpedido, 'VENTA');
            if ($venta === null
                || $venta->pedidoId !== $factura->idPedido
                || ! $venta->estaBalanceado()
                || $this->validador->aCentimos($venta->totalDebe()) !== $componentes['subtotal']
                || ! DB::table('asientos_contables')->where('id_asiento', $venta->id)->where('estado', 'REGISTRADO')->exists()) {
                throw new LogicException('La venta del subpedido no existe o no coincide con la factura.');
            }

            $asiento = new AsientoContable(
                0, $factura->idComercio, $factura->idPedido, $factura->idSubpedido,
                $factura->idEvento, 'FacturaGenerada', 'DISTRIBUCION',
                Carbon::parse($factura->fecha)->utc()->format('Y-m-d H:i:s'), $factura->idPago,
            );
            $this->agregarLinea($asiento, $cuentas['1.1.04'], $total, true);
            $this->agregarLinea($asiento, $cuentas['1.1.02'], $componentes['subtotal'], false);
            $this->agregarLinea($asiento, $cuentas['2.1.02'], $componentes['impuesto'], false);
            $this->agregarLinea($asiento, $cuentas['4.1.02'], $componentes['envio'], false);
            $guardado = $this->asientos->guardar($asiento);
            $plataforma = $this->plataforma->registrar(
                $factura->idEvento, 'FacturaGenerada', 'DISTRIBUCION', $factura->idPago, $factura->idPedido,
                $factura->idComercio, $factura->idSubpedido, $asiento->fecha, [
                    ['codigo' => 'P-PENDIENTE', 'debe' => $total, 'haber' => 0],
                    ['codigo' => 'P-COMERCIOS', 'debe' => 0, 'haber' => $total],
                ],
            );

            $this->guardarRegistro($referencia, 'FACTURA', $factura->idFactura, $factura->idPago, $factura->idPedido, $factura->idSubpedido, $factura->idComercio, $guardado->id, $componentes, $huella);
            DB::table('registros_financieros_m6')->where('referencia', $referencia)->update(['id_asiento_plataforma' => $plataforma]);
        });
    }

    public function procesarReembolso(ReembolsoProcesado $reembolso): void
    {
        $this->validarIdentificadores($reembolso->idEvento, $reembolso->fecha, [
            $reembolso->idReembolso, $reembolso->idPedido, $reembolso->idSubpedido, $reembolso->idComercio, $reembolso->idPago, $reembolso->notaCreditoId,
        ]);
        $monto = $this->centimos($reembolso->monto);
        if ($monto <= 0) {
            throw new InvalidArgumentException('El reembolso debe tener un monto positivo.');
        }
        $referencia = "reembolso:{$reembolso->idReembolso}";
        $huella = $this->huella([$reembolso->idReembolso, $reembolso->idPago, $reembolso->idPedido, $reembolso->idSubpedido, $reembolso->idComercio, $reembolso->notaCreditoId, Carbon::parse($reembolso->fecha)->utc()->format('Y-m-d H:i:s'), $monto]);

        $this->eventos->procesar($reembolso->idEvento, 'ReembolsoProcesado', function (bool $yaProcesado) use ($reembolso, $monto, $referencia, $huella): void {
            $this->exigirSistema();
            $this->bloquearPago($reembolso->idPago, $reembolso->idPedido);
            if ($this->yaRegistrado($referencia, $huella, $yaProcesado)) {
                return;
            }
            $cuentas = $this->cuentas->bloquearPorCodigos($reembolso->idComercio, ['1.1.04', '4.1.01', '2.1.02', '4.1.02']);

            $factura = DB::table('registros_financieros_m6')
                ->where('tipo', 'FACTURA')->where('id_pago', $reembolso->idPago)->where('id_subpedido', $reembolso->idSubpedido)
                ->lockForUpdate()->first();
            if ($factura === null || (int) $factura->id_comercio !== $reembolso->idComercio || (int) $factura->id_pedido !== $reembolso->idPedido) {
                throw new LogicException('No existe un cobro registrado para la factura del reembolso.');
            }
            if (! DB::table('asientos_contables')->where('id_asiento', $factura->id_asiento)->where('estado', 'REGISTRADO')->exists()) {
                throw new LogicException('El asiento original del cobro no está registrado.');
            }
            $original = [
                'subtotal' => (int) $factura->subtotal_centimos,
                'impuesto' => (int) $factura->impuesto_centimos,
                'envio' => (int) $factura->envio_centimos,
            ];
            $anteriores = DB::table('registros_financieros_m6')
                ->where('tipo', 'REEMBOLSO')->where('id_pago', $reembolso->idPago)
                ->where('id_subpedido', $reembolso->idSubpedido)->where('id_comercio', $reembolso->idComercio)
                ->selectRaw('COALESCE(SUM(subtotal_centimos), 0) AS subtotal, COALESCE(SUM(impuesto_centimos), 0) AS impuesto, COALESCE(SUM(envio_centimos), 0) AS envio')
                ->first();
            $disponibles = [];
            foreach ($original as $campo => $valor) {
                $disponibles[$campo] = $valor - (int) $anteriores->{$campo};
            }
            if (min($disponibles) < 0 || $monto > array_sum($disponibles)) {
                throw new LogicException('El reembolso excede el saldo pendiente de la factura.');
            }
            $componentes = $this->prorratear($monto, $original, $disponibles);

            $asiento = new AsientoContable(
                0, $reembolso->idComercio, $reembolso->idPedido, $reembolso->idSubpedido,
                $reembolso->idEvento, 'ReembolsoProcesado', 'REEMBOLSO',
                Carbon::parse($reembolso->fecha)->utc()->format('Y-m-d H:i:s'), $reembolso->idPago, (int) $factura->id_asiento,
            );
            $this->agregarLinea($asiento, $cuentas['4.1.01'], $componentes['subtotal'], true);
            $this->agregarLinea($asiento, $cuentas['2.1.02'], $componentes['impuesto'], true);
            $this->agregarLinea($asiento, $cuentas['4.1.02'], $componentes['envio'], true);
            $this->agregarLinea($asiento, $cuentas['1.1.04'], $monto, false);
            $guardado = $this->asientos->guardar($asiento);
            $plataforma = $this->plataforma->registrar(
                $reembolso->idEvento, 'ReembolsoProcesado', 'REEMBOLSO', $reembolso->idPago, $reembolso->idPedido,
                $reembolso->idComercio, $reembolso->idSubpedido, $asiento->fecha, [
                    ['codigo' => 'P-COMERCIOS', 'debe' => $monto, 'haber' => 0],
                    ['codigo' => 'P-BANCOS', 'debe' => 0, 'haber' => $monto],
                ],
            );
            $this->guardarRegistro($referencia, 'REEMBOLSO', $reembolso->idReembolso, $reembolso->idPago, $reembolso->idPedido, $reembolso->idSubpedido, $reembolso->idComercio, $guardado->id, $componentes, $huella);
            DB::table('registros_financieros_m6')->where('referencia', $referencia)->update(['id_asiento_plataforma' => $plataforma]);
        });
    }

    private function bloquearPago(int $pagoId, int $pedidoId, bool $exigirAsiento = true): \stdClass
    {
        $pago = DB::table('pagos_contables_m6')->where('id_pago', $pagoId)->lockForUpdate()->first();
        if ($pago === null || (int) $pago->id_pedido !== $pedidoId || ($exigirAsiento && $pago->id_asiento_plataforma === null)) {
            throw new LogicException('No existe un cobro global registrado para el pedido.');
        }

        return $pago;
    }

    /** @param list<int|null> $identificadores */
    private function validarIdentificadores(string $eventoId, string $fecha, array $identificadores): void
    {
        if (! Str::isUuid($eventoId) || trim($fecha) === '') {
            throw new InvalidArgumentException('El evento o su fecha no son válidos.');
        }
        foreach ($identificadores as $identificador) {
            if ($identificador === null || $identificador <= 0) {
                throw new InvalidArgumentException('Los identificadores financieros deben ser positivos.');
            }
        }
    }

    private function exigirSistema(): void
    {
        if (! $this->contexto->esModoSistema()) {
            throw new LogicException('El procesamiento financiero requiere contexto de sistema.');
        }
    }

    private function centimos(float $monto): int
    {
        if (! is_finite($monto) || $monto < 0 || abs($monto - round($monto, 2)) > 0.000001) {
            throw new InvalidArgumentException('El importe financiero debe tener como máximo dos decimales.');
        }

        return $this->validador->aCentimos(number_format($monto, 2, '.', ''));
    }

    private function agregarLinea(AsientoContable $asiento, int $cuenta, int $centimos, bool $debe): void
    {
        if ($centimos === 0) {
            return;
        }
        $importe = intdiv($centimos, 100).'.'.str_pad((string) ($centimos % 100), 2, '0', STR_PAD_LEFT);
        $asiento->agregarDetalle(new DetalleAsiento($cuenta, $debe ? $importe : '0', $debe ? '0' : $importe));
    }

    private function huella(array $datos): string
    {
        return hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));
    }

    private function yaRegistrado(string $referencia, string $huella, bool $yaProcesado): bool
    {
        $existente = DB::table('registros_financieros_m6')->where('referencia', $referencia)->first();
        if ($existente !== null) {
            if (! hash_equals($existente->huella, $huella)) {
                throw new LogicException('La operación financiera ya existe con datos diferentes.');
            }

            return true;
        }
        if ($yaProcesado) {
            throw new LogicException('El evento ya procesado no tiene su registro financiero.');
        }

        return false;
    }

    /** @param array{subtotal:int, impuesto:int, envio:int} $componentes */
    private function guardarRegistro(string $referencia, string $tipo, int $operacionId, int $pagoId, int $pedidoId, int $subpedidoId, int $comercioId, int $asientoId, array $componentes, string $huella): void
    {
        DB::table('registros_financieros_m6')->insert([
            'referencia' => $referencia, 'tipo' => $tipo, 'id_operacion' => $operacionId,
            'id_pago' => $pagoId, 'id_pedido' => $pedidoId, 'id_subpedido' => $subpedidoId,
            'id_comercio' => $comercioId, 'id_asiento' => $asientoId,
            'subtotal_centimos' => $componentes['subtotal'], 'impuesto_centimos' => $componentes['impuesto'],
            'envio_centimos' => $componentes['envio'], 'huella' => $huella,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * @param  array{subtotal:int, impuesto:int, envio:int}  $original
     * @param  array{subtotal:int, impuesto:int, envio:int}  $disponibles
     * @return array{subtotal:int, impuesto:int, envio:int}
     */
    private function prorratear(int $monto, array $original, array $disponibles): array
    {
        $resultado = array_fill_keys(array_keys($original), 0);
        $pesos = $original;
        $restante = $monto;

        while ($restante > 0) {
            $totalPeso = array_sum($pesos);
            $restos = [];
            $repartido = 0;
            foreach ($pesos as $campo => $peso) {
                [$cociente, $residuo] = BigInteger::of($restante)->multipliedBy($peso)->quotientAndRemainder($totalPeso);
                $asignado = min($cociente->toInt(), $disponibles[$campo] - $resultado[$campo]);
                $resultado[$campo] += $asignado;
                $repartido += $asignado;
                $restos[$campo] = $residuo->toInt();
            }
            $restante -= $repartido;
            arsort($restos);
            foreach ($restos as $campo => $residuo) {
                if ($restante > 0 && $resultado[$campo] < $disponibles[$campo]) {
                    $resultado[$campo]++;
                    $restante--;
                }
            }
            foreach ($pesos as $campo => $peso) {
                $pesos[$campo] = $disponibles[$campo] - $resultado[$campo];
            }
        }

        return $resultado;
    }
}
