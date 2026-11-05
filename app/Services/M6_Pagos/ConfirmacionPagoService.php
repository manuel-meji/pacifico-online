<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Models\M6_Pagos\EstadoNotificacionPago;
use App\Models\M6_Pagos\EstadoPago;
use App\Models\M6_Pagos\NotificacionPago;
use App\Models\M6_Pagos\Pago;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * CU-002 Confirmar pago recibido (webhook de pasarela / SINPE / PayPal).
 *
 * Idempotencia (RN-M6-02, ADR-003), en tres capas:
 *  1. La fila del pago se bloquea (SELECT ... FOR UPDATE) mientras se decide.
 *  2. notificaciones_pago.referencia_proveedor es UNIQUE: la misma notificación
 *     nunca se aplica dos veces, ni aunque lleguen dos a la vez.
 *  3. Solo un pago "pendiente" cambia de estado; los efectos (eventos, M5, M7)
 *     se publican una única vez, después del commit.
 */
class ConfirmacionPagoService
{
    public const APROBADO = 'aprobado';

    public const RECHAZADO = 'rechazado';

    public function __construct(private readonly PublicadorResultadoPago $publicador) {}

    /**
     * @return array{pago: Pago, duplicada: bool}
     *
     * @throws ErrorContratoException TRANSACCION_NO_ENCONTRADA | NOTIFICACION_NO_AUTENTICA
     */
    public function confirmar(int $idPago, string $referenciaProveedor, string $estadoRecibido, ?string $motivo = null): array
    {
        try {
            [$pago, $aplicada] = DB::transaction(
                fn () => $this->aplicar($idPago, $referenciaProveedor, $estadoRecibido)
            );
        } catch (UniqueConstraintViolationException) {
            // Otra petición registró la misma referencia en paralelo: es un duplicado.
            $pago = Pago::query()->with('estado')->findOrFail($idPago);

            return ['pago' => $pago, 'duplicada' => true];
        }

        if ($aplicada) {
            $this->publicador->publicar($pago, $motivo);
        }

        return ['pago' => $pago, 'duplicada' => ! $aplicada];
    }

    /** @return array{0: Pago, 1: bool} [pago, ¿se aplicó un cambio de estado?] */
    private function aplicar(int $idPago, string $referencia, string $estadoRecibido): array
    {
        $pago = Pago::query()->with('estado')->lockForUpdate()->find($idPago)
            ?? throw ErrorContratoException::de(CodigoErrorM6::TransaccionNoEncontrada);

        // Capa 2: la misma referencia ya se procesó → no se repite nada.
        if (NotificacionPago::query()->where('referencia_proveedor', $referencia)->exists()) {
            return [$pago, false];
        }

        // Si la pasarela ya asignó una referencia a este cobro, no se acepta otra distinta.
        if ($pago->referencia_proveedor !== null && $pago->referencia_proveedor !== $referencia) {
            throw ErrorContratoException::de(CodigoErrorM6::NotificacionNoAutentica);
        }

        // Capa 3: el pago ya estaba resuelto → se deja constancia como duplicada.
        if (! $pago->estaPendiente()) {
            $this->registrarNotificacion($pago, $referencia, EstadoNotificacionPago::DUPLICADA);

            return [$pago, false];
        }

        $this->registrarNotificacion($pago, $referencia, EstadoNotificacionPago::PROCESADA);

        $pago->forceFill([
            'id_estado_pago' => EstadoPago::idDe($estadoRecibido === self::APROBADO ? EstadoPago::CONFIRMADO : EstadoPago::RECHAZADO),
            'referencia_proveedor' => $referencia,
            'fecha_confirmacion' => now(),
        ])->save();

        return [$pago->refresh()->load('estado'), true];
    }

    private function registrarNotificacion(Pago $pago, string $referencia, string $estado): void
    {
        NotificacionPago::query()->create([
            'id_pago' => $pago->id_pago,
            'id_medio_pago' => $pago->id_medio_pago,
            'id_estado_notificacion_pago' => EstadoNotificacionPago::idDe($estado),
            'referencia_proveedor' => $referencia,
            'fecha_recepcion' => now(),
        ]);
    }
}
