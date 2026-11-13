<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Models\M7_Contabilidad\AsientoContable;
use App\Models\M7_Contabilidad\DetalleAsiento;
use App\Services\M7_Contabilidad\DTOs\CostoVentaDTO;
use App\Services\M7_Contabilidad\DTOs\VentaDTO;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class AsientoService
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly CuentaContableRepository $cuentas,
        private readonly ValidadorPartidaDoble $validador,
        private readonly ContextoComercio $contexto,
    ) {
    }

    public function crearAsientoVenta(VentaDTO $datos, bool $verificarExistente = false): AsientoContable
    {
        return $this->crear($datos->eventoId, $datos->fecha, $datos->pedidoId, $datos->subpedidoId, $datos->comercioId, $datos->subtotal, 'PedidoConfirmado', 'VENTA', '1.1.02', '4.1.01', $verificarExistente);
    }

    public function crearAsientoCostoVenta(CostoVentaDTO $datos, bool $verificarExistente = false): AsientoContable
    {
        return $this->crear($datos->eventoId, $datos->fecha, $datos->pedidoId, $datos->subpedidoId, $datos->comercioId, $datos->costoTotal, 'SubpedidoDespachado', 'COSTO_VENTA', '5.1.01', '1.1.03', $verificarExistente);
    }

    /** @param list<DetalleAsiento> $detalles */
    public function validarPartidaDoble(array $detalles): bool
    {
        try {
            $this->validador->validar(array_map(
                fn(DetalleAsiento $detalle): array => ['debe' => $detalle->debe, 'haber' => $detalle->haber],
                $detalles,
            ));

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function crear(string $eventoId, string $fecha, int $pedidoId, int $subpedidoId, int $comercioId, string $importe, string $tipoEvento, string $tipo, string $debe, string $haber, bool $verificarExistente): AsientoContable
    {
        if (!Str::isUuid($eventoId) || $pedidoId <= 0 || $subpedidoId <= 0 || $comercioId <= 0 || trim($fecha) === '') {
            throw new InvalidArgumentException('El evento, fecha y los identificadores deben ser válidos.');
        }
        if (!$this->contexto->esModoSistema() && $this->contexto->idActual() !== $comercioId) {
            throw new LogicException('El comercio no corresponde al contexto actual.');
        }

        $fechaNormalizada = Carbon::parse($fecha)->utc()->format('Y-m-d H:i:s');
        $movimientos = [['debe' => $importe, 'haber' => '0'], ['debe' => '0', 'haber' => $importe]];
        $this->validador->validar($movimientos);

        return DB::transaction(function () use ($eventoId, $fechaNormalizada, $pedidoId, $subpedidoId, $comercioId, $importe, $tipoEvento, $tipo, $debe, $haber, $verificarExistente): AsientoContable {
            $cuentas = $this->cuentas->bloquearCuentas($comercioId, $debe, $haber);
            $existente = $this->asientos->buscarPorSubpedido($comercioId, $subpedidoId, $tipo);

            if ($existente !== null) {
                if (
                    $existente->tipoEvento !== $tipoEvento
                    || $existente->pedidoId !== $pedidoId
                    || !$existente->estaBalanceado()
                    || $this->validador->aCentimos($existente->totalDebe())
                    !== $this->validador->aCentimos($importe)
                    || $existente->fecha !== $fechaNormalizada
                ) {
                    throw new LogicException('El asiento existente no coincide con los datos recibidos.');
                }

                return $existente;
            }

            if ($verificarExistente) {
                throw new LogicException('El evento procesado no coincide con los asientos existentes.');
            }

            $asiento = new AsientoContable(0, $comercioId, $pedidoId, $subpedidoId, $eventoId, $tipoEvento, $tipo, $fechaNormalizada);
            $asiento->agregarDetalle(new DetalleAsiento($cuentas[$debe], $importe, '0'));
            $asiento->agregarDetalle(new DetalleAsiento($cuentas[$haber], '0', $importe));

            return $this->asientos->guardar($asiento);
        });
    }
}
