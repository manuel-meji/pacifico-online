<?php

declare(strict_types=1);

namespace App\Contracts\M6_Pagos;

/**
 * Consulta de los eventos financieros de M6 por período (contrato M6 v1.4.0,
 * conexión M6–M7).
 *
 * Es una interfaz aparte de PagosServiceInterface para no romper a quien ya la
 * implementa (por ejemplo los dobles de M5). Se usa dentro del mismo proceso,
 * inyectada por el contenedor; no expone rutas HTTP.
 *
 * Sirve para recuperar eventos perdidos y para cargar históricos: cada elemento
 * es exactamente el payload() del evento que M6 publicó, con el mismo id_evento.
 * Si el consumidor ya procesó ese id_evento, lo descarta por idempotencia.
 *
 * Período: $desde y $hasta son fechas ISO 8601 (o Y-m-d), ambas inclusive.
 * Si solo se da la fecha, $hasta cubre el día completo.
 *
 * Aislamiento (RN-12): con $idComercio solo se devuelven datos de ese comercio.
 * Un pago es de todo el pedido; con $idComercio se devuelven los pagos en los
 * que el comercio tiene factura, pero el monto sigue siendo el del pedido. El
 * importe por comercio está en listarFacturasGeneradas.
 *
 * @throws \InvalidArgumentException si las fechas no son válidas o $desde > $hasta.
 */
interface ConsultaEventosPagosInterface
{
    /**
     * Payloads de PagoConfirmado (PagoConfirmadoEvent) por fecha de confirmación.
     *
     * @return list<array{id_evento: string, id_pedido: int, id_pago: int, monto: float, fecha: string}>
     */
    public function listarPagosConfirmados(string $desde, string $hasta, ?int $idComercio = null): array;

    /**
     * Payloads de ReembolsoProcesado (ReembolsoProcesadoEvent) por fecha de procesamiento.
     * Solo reembolsos procesados (con nota de crédito).
     *
     * @return list<array<string, mixed>>
     */
    public function listarReembolsosProcesados(string $desde, string $hasta, ?int $idComercio = null): array;

    /**
     * Payloads de FacturaGenerada (FacturaGeneradaEvent) por fecha de emisión.
     * Incluye id_pago y el desglose subtotal / impuesto / envio.
     *
     * @return list<array<string, mixed>>
     */
    public function listarFacturasGeneradas(string $desde, string $hasta, ?int $idComercio = null): array;
}
