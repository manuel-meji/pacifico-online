<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Contracts\M6_Pagos\PasarelaPagoInterface;
use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Exceptions\Nucleo\ErrorNucleoException;
use App\Models\M6_Pagos\EstadoPago;
use App\Models\M6_Pagos\MedioPago;
use App\Models\M6_Pagos\Pago;
use App\Services\M6_Pagos\Clients\PedidoClient;
use App\Services\Nucleo\ContextoCliente;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CU-001 Procesar pago del pedido (RF-M6-01, RN-M6-01).
 *
 * Orden del flujo:
 *  1. Idempotency-Key repetida  → se devuelve el mismo intento, sin cobrar otra vez.
 *  2. Validaciones de negocio   → medio habilitado, pedido "confirmado" en M5, monto exacto.
 *  3. Candado por pedido        → dos cobros simultáneos del mismo pedido no se cruzan.
 *  4. Registro "pendiente"      → antes de hablar con la pasarela.
 *  5. Cobro en la pasarela      → si responde, su resultado entra por la MISMA ruta
 *                                 idempotente del webhook (ConfirmacionPagoService).
 */
class ProcesamientoPagosService
{
    public function __construct(
        private readonly PedidoClient $pedidos,
        private readonly PasarelaPagoInterface $pasarela,
        private readonly ConfirmacionPagoService $confirmaciones,
        private readonly ContextoCliente $contextoCliente,
    ) {}

    /**
     * @param  array<string, mixed>  $datosPago  Solo tokens (token_medio_pago); nunca PAN ni CVV.
     * @return array{pago: Pago, reutilizado: bool}
     */
    public function procesar(int $idPedido, string $medioPago, float $montoTotal, string $claveIdempotencia, array $datosPago = []): array
    {
        if ($existente = $this->buscarPorClave($claveIdempotencia)) {
            return ['pago' => $this->validarReintento($existente, $idPedido, $montoTotal), 'reutilizado' => true];
        }

        $idMedio = MedioPago::idDe($medioPago) ?? throw ErrorContratoException::de(CodigoErrorM6::MedioPagoNoDisponible);

        $pedido = $this->validarPedido($idPedido, $montoTotal);

        try {
            $pago = Cache::lock("m6:pedido:{$idPedido}", 30)->block(
                (int) config('m6.espera_candado_segundos', 5),
                fn () => $this->registrarIntento($pedido->idPedido, $pedido->idCliente, $idMedio, $pedido->montoTotal, $claveIdempotencia)
            );
        } catch (LockTimeoutException) {
            throw ErrorContratoException::de(CodigoErrorM6::PedidoConPagoActivo);
        }

        if ($pago->wasRecentlyCreated === false) {
            return ['pago' => $this->validarReintento($pago, $idPedido, $montoTotal), 'reutilizado' => true];
        }

        return ['pago' => $this->cobrar($pago, $medioPago, $datosPago), 'reutilizado' => false];
    }

    public function consultar(int $idPago): Pago
    {
        $pago = Pago::query()->with(['estado', 'medio'])->find($idPago)
            ?? throw ErrorContratoException::de(CodigoErrorM6::PagoNoEncontrado);

        $this->exigirMismoCliente($pago->id_cliente);

        return $pago;
    }

    private function buscarPorClave(string $clave): ?Pago
    {
        return Pago::query()->with(['estado', 'medio'])->where('clave_idempotencia', $clave)->first();
    }

    private function validarReintento(Pago $pago, int $idPedido, float $montoTotal): Pago
    {
        if ($pago->id_pedido !== $idPedido || round((float) $pago->monto, 2) !== round($montoTotal, 2)) {
            throw ErrorContratoException::de(CodigoErrorM6::ClaveIdempotenciaEnConflicto);
        }

        $this->exigirMismoCliente($pago->id_cliente);

        return $pago;
    }

    /** RN-M6-01: pedido en estado "confirmado" y monto idéntico al calculado por M5. */
    private function validarPedido(int $idPedido, float $montoTotal): Clients\PedidoDTO
    {
        $estado = strtolower($this->pedidos->consultarEstadoPedido($idPedido));

        if ($estado !== 'confirmado') {
            throw ErrorContratoException::de(CodigoErrorM6::PedidoNoConfirmado, null, ['estado_pedido' => $estado]);
        }

        $pedido = $this->pedidos->obtenerDatosPedido($idPedido);

        if (round($pedido->montoTotal, 2) !== round($montoTotal, 2)) {
            throw ErrorContratoException::de(CodigoErrorM6::MontoInconsistente);
        }

        $this->exigirMismoCliente($pedido->idCliente);

        return $pedido;
    }

    private function registrarIntento(int $idPedido, int $idCliente, int $idMedio, float $monto, string $clave): Pago
    {
        // Dentro del candado: la misma clave pudo registrarse mientras se esperaba.
        if ($existente = $this->buscarPorClave($clave)) {
            return $existente;
        }

        // ¿Quedó otro intento activo para este pedido?
        $activo = Pago::query()
            ->where('id_pedido', $idPedido)
            ->whereIn('id_estado_pago', [EstadoPago::idDe(EstadoPago::PENDIENTE), EstadoPago::idDe(EstadoPago::CONFIRMADO)])
            ->exists();

        if ($activo) {
            throw ErrorContratoException::de(CodigoErrorM6::PedidoConPagoActivo);
        }

        try {
            return Pago::query()->create([
                'id_pedido' => $idPedido,
                'id_cliente' => $idCliente,
                'id_medio_pago' => $idMedio,
                'id_estado_pago' => EstadoPago::idDe(EstadoPago::PENDIENTE),
                'monto' => $monto,
                'clave_idempotencia' => $clave,
                'fecha_creacion' => now(),
            ])->load(['estado', 'medio']);
        } catch (UniqueConstraintViolationException) {
            // Misma Idempotency-Key en paralelo: se devuelve el intento que ganó.
            return $this->buscarPorClave($clave) ?? throw ErrorContratoException::de(CodigoErrorM6::ClaveIdempotenciaEnConflicto);
        }
    }

    private function cobrar(Pago $pago, string $medioPago, array $datosPago): Pago
    {
        try {
            $respuesta = $this->pasarela->procesarCobro(
                'M6-PAGO-'.$pago->id_pago,
                (float) $pago->monto,
                ['medio_pago' => $medioPago] + array_intersect_key($datosPago, ['token_medio_pago' => true])
            );
        } catch (Throwable $e) {
            // CU-001 flujo alterno: el proveedor no responde → queda "pendiente" a la espera del webhook.
            Log::warning("[M6_Pagos] La pasarela no respondió para el pago {$pago->id_pago}: {$e->getMessage()}");

            return $pago;
        }

        $referencia = $respuesta['referencia_proveedor'] ?? null;

        if ($referencia !== null) {
            $pago->forceFill(['referencia_proveedor' => $referencia])->save();
        }

        if (! config('m6.confirmacion_inmediata', true) || $referencia === null) {
            return $pago->refresh()->load(['estado', 'medio']);
        }

        $estado = ($respuesta['exitoso'] ?? false) ? ConfirmacionPagoService::APROBADO : ConfirmacionPagoService::RECHAZADO;

        return $this->confirmaciones
            ->confirmar($pago->id_pago, $referencia, $estado, $respuesta['mensaje'] ?? null)['pago']
            ->load('medio');
    }

    /** RN-12: si hay un cliente en sesión, solo puede operar sus propios pagos (responde 404, no 403). */
    private function exigirMismoCliente(int $idCliente): void
    {
        $actual = $this->contextoCliente->idActual();

        if (! $this->contextoCliente->esModoSistema() && $actual !== null && $actual !== $idCliente) {
            throw ErrorNucleoException::recursoNoAccesible();
        }
    }
}
