<?php

namespace App\Services\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContabilidadService implements ContabilidadServiceInterface
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
        if ($idPago <= 0) {
            return [
                'id_pago' => $idPago,
                'registrado' => false,
                'asiento_id' => null,
                'fecha_registro' => null,
                'subpedidos_registrados' => [],
            ];
        }

        $asiento = DB::table('asientos_contables')
            ->where('id_pago', $idPago)
            ->where('estado', 'REGISTRADO')
            ->orderByDesc('fecha')
            ->orderByDesc('id_asiento')
            ->first();

        $subpedidos = DB::table('asientos_contables')
            ->where('id_pago', $idPago)
            ->where('tipo', 'COBRO')
            ->where('estado', 'REGISTRADO')
            ->whereNotNull('id_subpedido')
            ->distinct()
            ->orderBy('id_subpedido')
            ->pluck('id_subpedido')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return [
            'id_pago' => $idPago,
            'registrado' => $asiento !== null,
            'asiento_id' => $asiento !== null
                ? (int) $asiento->id_asiento
                : null,
            'fecha_registro' => $asiento?->fecha,
            'subpedidos_registrados' => $subpedidos,
        ];
    }
}
