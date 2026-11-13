<?php

namespace App\Services\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ContabilidadComercioServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContabilidadService implements ContabilidadComercioServiceInterface
{
    public function registrarAsientoDespacho(int $pedidoId, float $totalVenta, float $costoVentas): bool
    {
        /*
         * Según el PC3 colectivo, PedidoConfirmado originará el asiento de venta
         * y SubpedidoDespachado el de costo. Su integración definitiva se
         * coordinará con M5 y M4. Se conserva el retorno anterior hasta acordar
         * y versionar el reemplazo del contrato; no implica persistencia.
         */
        Log::warning('M7: registrarAsientoDespacho conserva el retorno anterior por compatibilidad temporal; no genera asientos. Su reemplazo requiere coordinación y versionado del contrato.', [
            'id_pedido' => $pedidoId,
        ]);

        return true;
    }

    public function consultarRegistroContablePago(int $idPago): array
    {
        return $this->buscarRegistroPago($idPago, null);
    }

    /** Uso HTTP de M7: limita la búsqueda a los asientos del comercio indicado. */
    public function consultarRegistroContablePagoDeComercio(int $idPago, int $idComercio): array
    {
        return $this->buscarRegistroPago($idPago, $idComercio);
    }

    private function buscarRegistroPago(int $idPago, ?int $idComercio): array
    {
        if ($idPago <= 0) {
            return [
                'id_pago' => $idPago,
                'registrado' => false,
                'asiento_id' => null,
                'fecha_registro' => null,
                'subpedidos_registrados' => [],
            ];
        }

        $pago = DB::table('pagos_contables_m6 as pagos')
            ->join('asientos_plataforma_m7 as asientos', 'asientos.id_asiento', '=', 'pagos.id_asiento_plataforma')
            ->where('pagos.id_pago', $idPago)->where('asientos.estado', 'REGISTRADO')
            ->select('pagos.*')->first();
        $asiento = DB::table('asientos_contables')
            ->where('id_pago', $idPago)
            ->where('estado', 'REGISTRADO')
            ->where('tipo', 'DISTRIBUCION')
            ->when($idComercio !== null, fn ($consulta) => $consulta->where('id_comercio', $idComercio))
            ->orderByDesc('fecha')
            ->orderByDesc('id_asiento')
            ->first();

        $subpedidos = DB::table('asientos_contables')
            ->where('id_pago', $idPago)
            ->when($idComercio !== null, fn ($consulta) => $consulta->where('id_comercio', $idComercio))
            ->where('tipo', 'DISTRIBUCION')
            ->where('estado', 'REGISTRADO')
            ->whereNotNull('id_subpedido')
            ->distinct()
            ->orderBy('id_subpedido')
            ->pluck('id_subpedido')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $resultado = [
            'id_pago' => $idPago,
            'registrado' => $pago !== null && ($idComercio === null || $asiento !== null),
            'asiento_id' => $pago === null ? null : ($idComercio === null ? (int) $pago->id_asiento_plataforma : ($asiento !== null ? (int) $asiento->id_asiento : null)),
            'fecha_registro' => $pago === null ? null : ($idComercio === null ? $pago->fecha : $asiento?->fecha),
            'subpedidos_registrados' => $subpedidos,
            'libro' => $idComercio === null ? 'PLATAFORMA' : 'COMERCIO',
        ];
        if ($idComercio === null && $pago !== null) {
            $cuadre = DB::table('cuadres_pagos_m6')->where('id_pago', $idPago)->first();
            $resultado['estado_cuadre'] = $cuadre?->estado ?? 'PENDIENTE_EVALUACION';
            $resultado['monto_cobro_centimos'] = (int) $pago->monto_centimos;
            $resultado['monto_facturado_centimos'] = $cuadre?->facturado_centimos !== null ? (int) $cuadre->facturado_centimos : null;
        }

        return $resultado;
    }
}
