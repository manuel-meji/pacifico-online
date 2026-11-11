<?php

namespace App\Enums\M4_Inventario;

/**
 * Motivo por el que una reserva se cierra sin salida física (motivo_cierre del
 * contrato y LiberarReservaSolicitud.motivo). Queda en el historial de la reserva.
 */
enum MotivoCierre: string
{
    case PagoRechazado = 'pago_rechazado';
    case PedidoCancelado = 'pedido_cancelado';
    case SolicitudM5 = 'solicitud_m5';
    case Vencimiento = 'vencimiento';
}
