<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Contracts\M6_Pagos\ConsultaEventosPagosInterface;
use App\Models\M6_Pagos\EstadoPago;
use App\Models\M6_Pagos\EstadoReembolso;
use App\Models\M6_Pagos\FacturaElectronica;
use App\Models\M6_Pagos\Pago;
use App\Models\M6_Pagos\Reembolso;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Implementación de ConsultaEventosPagosInterface (contrato M6 v1.4.0,
 * conexión M6–M7). Solo lee tablas de M6 y arma cada payload con
 * FabricaEventosM6, igual que cuando el evento se publicó.
 */
class ConsultaEventosPagosService implements ConsultaEventosPagosInterface
{
    public function listarPagosConfirmados(string $desde, string $hasta, ?int $idComercio = null): array
    {
        [$inicio, $fin] = $this->periodo($desde, $hasta);

        return Pago::query()
            ->where('id_estado_pago', EstadoPago::idDe(EstadoPago::CONFIRMADO))
            ->whereBetween('fecha_confirmacion', [$inicio, $fin])
            ->when($idComercio !== null, fn ($q) => $q->whereIn(
                'id_pago',
                FacturaElectronica::query()->select('id_pago')->where('id_comercio', $idComercio)
            ))
            ->orderBy('fecha_confirmacion')
            ->orderBy('id_pago')
            ->get()
            ->map(fn (Pago $pago): array => FabricaEventosM6::pagoConfirmado($pago)->payload())
            ->values()
            ->all();
    }

    public function listarReembolsosProcesados(string $desde, string $hasta, ?int $idComercio = null): array
    {
        [$inicio, $fin] = $this->periodo($desde, $hasta);

        return Reembolso::query()
            ->with(['pago', 'notaCredito.factura'])
            ->where('id_estado_reembolso', EstadoReembolso::idDe(EstadoReembolso::PROCESADO))
            ->whereNotNull('id_nota_credito')
            ->whereBetween('fecha_procesado', [$inicio, $fin])
            ->when($idComercio !== null, fn ($q) => $q->whereHas(
                'notaCredito.factura',
                fn ($factura) => $factura->where('id_comercio', $idComercio)
            ))
            ->orderBy('fecha_procesado')
            ->orderBy('id_reembolso')
            ->get()
            ->map(fn (Reembolso $reembolso): array => FabricaEventosM6::reembolsoProcesado($reembolso)->payload())
            ->values()
            ->all();
    }

    public function listarFacturasGeneradas(string $desde, string $hasta, ?int $idComercio = null): array
    {
        [$inicio, $fin] = $this->periodo($desde, $hasta);

        return FacturaElectronica::query()
            ->with('pago')
            ->whereBetween('fecha_emision', [$inicio, $fin])
            ->when($idComercio !== null, fn ($q) => $q->where('id_comercio', $idComercio))
            ->orderBy('fecha_emision')
            ->orderBy('id_factura_electronica')
            ->get()
            ->map(fn (FacturaElectronica $factura): array => FabricaEventosM6::facturaGenerada($factura)->payload())
            ->values()
            ->all();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function periodo(string $desde, string $hasta): array
    {
        $inicio = $this->fecha($desde, 'desde');
        $fin = $this->fecha($hasta, 'hasta');

        // Solo la fecha: "hasta" cubre el día completo.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($hasta)) === 1) {
            $fin = $fin->endOfDay();
        }

        if ($inicio->greaterThan($fin)) {
            throw new InvalidArgumentException('El período no es válido: "desde" es posterior a "hasta".');
        }

        return [$inicio, $fin];
    }

    private function fecha(string $valor, string $campo): CarbonImmutable
    {
        if (trim($valor) === '') {
            throw new InvalidArgumentException("La fecha \"{$campo}\" es obligatoria.");
        }

        try {
            // Se lleva a la zona de la aplicación, que es como se guardan las fechas de M6.
            return CarbonImmutable::parse(trim($valor))->setTimezone((string) config('app.timezone'));
        } catch (Throwable) {
            throw new InvalidArgumentException("La fecha \"{$campo}\" no es válida: {$valor}");
        }
    }
}
