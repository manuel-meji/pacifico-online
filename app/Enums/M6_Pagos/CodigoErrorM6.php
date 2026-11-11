<?php

declare(strict_types=1);

namespace App\Enums\M6_Pagos;

/**
 * Catálogo de errores de negocio de M6 (Pagos y Facturación).
 *
 * - El "case" es el código del contrato OpenAPI de M6 (MONTO_INCONSISTENTE, ...).
 * - codigoEstandar() devuelve el formato del Núcleo (ERR-M6-nnn, Sección 5.5 del PC3).
 * - ERR-M6-001 = pago rechazado, igual que el doble PagosStub de M5.
 */
enum CodigoErrorM6: string
{
    // Pagos (CU-001, CU-002)
    case PagoRechazado = 'PAGO_RECHAZADO';
    case PedidoNoEncontrado = 'PEDIDO_NO_ENCONTRADO';
    case MontoInconsistente = 'MONTO_INCONSISTENTE';
    case MedioPagoNoDisponible = 'MEDIO_PAGO_NO_DISPONIBLE';
    case PedidoNoConfirmado = 'PEDIDO_NO_CONFIRMADO';
    case PedidoConPagoActivo = 'PEDIDO_CON_PAGO_ACTIVO';
    case ClaveIdempotenciaEnConflicto = 'CLAVE_IDEMPOTENCIA_EN_CONFLICTO';
    case PagoNoEncontrado = 'PAGO_NO_ENCONTRADO';
    case TransaccionNoEncontrada = 'TRANSACCION_NO_ENCONTRADA';
    case NotificacionNoAutentica = 'NOTIFICACION_NO_AUTENTICA';

    // Datos de otros módulos (puertos Clients/)
    case ClienteNoEncontrado = 'CLIENTE_NO_ENCONTRADO';
    case ComercioNoEncontrado = 'COMERCIO_NO_ENCONTRADO';
    case DatosFiscalesIncompletos = 'DATOS_FISCALES_INCOMPLETOS';

    // Reembolsos, facturación, conciliación e historial (fases siguientes)
    case PagoNoConfirmado = 'PAGO_NO_CONFIRMADO';
    case ReembolsoExcedeMonto = 'REEMBOLSO_EXCEDE_MONTO';
    case FacturaNoEncontrada = 'FACTURA_NO_ENCONTRADA';
    case NotaExcedeMontoFactura = 'NOTA_EXCEDE_MONTO_FACTURA';
    case FacturaNoAceptada = 'FACTURA_NO_ACEPTADA';
    case PeriodoInvalido = 'PERIODO_INVALIDO';
    case SinResultados = 'SIN_RESULTADOS';

    public function codigoEstandar(): string
    {
        return match ($this) {
            self::PagoRechazado => 'ERR-M6-001',
            self::PedidoNoEncontrado => 'ERR-M6-002',
            self::MontoInconsistente => 'ERR-M6-003',
            self::MedioPagoNoDisponible => 'ERR-M6-004',
            self::PedidoNoConfirmado => 'ERR-M6-005',
            self::PedidoConPagoActivo => 'ERR-M6-006',
            self::ClaveIdempotenciaEnConflicto => 'ERR-M6-007',
            self::PagoNoEncontrado => 'ERR-M6-008',
            self::TransaccionNoEncontrada => 'ERR-M6-009',
            self::NotificacionNoAutentica => 'ERR-M6-010',
            self::ClienteNoEncontrado => 'ERR-M6-011',
            self::ComercioNoEncontrado => 'ERR-M6-012',
            self::DatosFiscalesIncompletos => 'ERR-M6-013',
            self::PagoNoConfirmado => 'ERR-M6-014',
            self::ReembolsoExcedeMonto => 'ERR-M6-015',
            self::FacturaNoEncontrada => 'ERR-M6-016',
            self::NotaExcedeMontoFactura => 'ERR-M6-017',
            self::FacturaNoAceptada => 'ERR-M6-018',
            self::PeriodoInvalido => 'ERR-M6-019',
            self::SinResultados => 'ERR-M6-020',
        };
    }

    public function codigoHttp(): int
    {
        return match ($this) {
            self::PagoRechazado => 402,
            self::MontoInconsistente => 400,
            self::NotificacionNoAutentica => 401,
            self::PedidoNoEncontrado,
            self::PagoNoEncontrado,
            self::TransaccionNoEncontrada,
            self::ClienteNoEncontrado,
            self::ComercioNoEncontrado,
            self::FacturaNoEncontrada,
            self::SinResultados => 404,
            self::PedidoNoConfirmado,
            self::PedidoConPagoActivo,
            self::ClaveIdempotenciaEnConflicto,
            self::PagoNoConfirmado,
            self::FacturaNoAceptada => 409,
            self::MedioPagoNoDisponible,
            self::DatosFiscalesIncompletos,
            self::ReembolsoExcedeMonto,
            self::NotaExcedeMontoFactura,
            self::PeriodoInvalido => 422,
        };
    }

    public function mensaje(): string
    {
        return match ($this) {
            self::PagoRechazado => 'El proveedor de pago rechazó el cobro.',
            self::PedidoNoEncontrado => 'No se encontró el pedido indicado.',
            self::MontoInconsistente => 'El monto recibido no coincide con el monto calculado del pedido.',
            self::MedioPagoNoDisponible => 'El medio de pago solicitado no está habilitado.',
            self::PedidoNoConfirmado => 'Solo se puede cobrar un pedido en estado confirmado.',
            self::PedidoConPagoActivo => 'El pedido ya tiene un pago pendiente o confirmado.',
            self::ClaveIdempotenciaEnConflicto => 'La clave de idempotencia ya se usó con otros datos de cobro.',
            self::PagoNoEncontrado => 'No se encontró el pago indicado.',
            self::TransaccionNoEncontrada => 'No existe un intento de pago con ese identificador.',
            self::NotificacionNoAutentica => 'La notificación recibida no pudo autenticarse.',
            self::ClienteNoEncontrado => 'No se encontró el cliente indicado.',
            self::ComercioNoEncontrado => 'No se encontró el comercio indicado.',
            self::DatosFiscalesIncompletos => 'Los datos fiscales del comercio están incompletos.',
            self::PagoNoConfirmado => 'No existe un pago confirmado asociado a este pedido.',
            self::ReembolsoExcedeMonto => 'El monto solicitado supera el monto pagado menos lo ya reembolsado.',
            self::FacturaNoEncontrada => 'No se encontró la factura indicada.',
            self::NotaExcedeMontoFactura => 'El monto de la nota de crédito no puede superar el de la factura original.',
            self::FacturaNoAceptada => 'Solo se puede emitir una nota de débito sobre una factura aceptada por Hacienda.',
            self::PeriodoInvalido => 'El rango de fechas solicitado no es válido.',
            self::SinResultados => 'No se encontraron pagos ni facturas para los filtros indicados.',
        };
    }
}
