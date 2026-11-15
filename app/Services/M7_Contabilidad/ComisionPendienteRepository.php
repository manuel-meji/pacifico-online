<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use Illuminate\Support\Facades\DB;

final class ComisionPendienteRepository
{
    public function registrar(
        string $eventoId,
        int $pedidoId,
        int $subpedidoId,
        int $comercioId,
        string $fechaEvento,
    ): void {
        DB::table('comisiones_contables_pendientes')->insertOrIgnore([
            'evento_id' => $eventoId,
            'id_pedido' => $pedidoId,
            'id_subpedido' => $subpedidoId,
            'id_comercio' => $comercioId,
            'fecha_evento' => $fechaEvento,
            'estado' => 'PENDIENTE',
            'intentos' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function pendientes(): array
    {
        return DB::table('comisiones_contables_pendientes')
            ->where('estado', 'PENDIENTE')
            ->orderBy('id_comision_pendiente')
            ->get()
            ->all();
    }

    public function marcarDisponible(
        int $subpedidoId,
        float $monto
    ): void {
        DB::table('comisiones_contables_pendientes')
            ->where('id_subpedido', $subpedidoId)
            ->update([
                'estado' => 'DISPONIBLE',
                'monto_comision' => $monto,
                'ultimo_error' => null,
                'updated_at' => now(),
            ]);
    }

    public function registrarIntentoPendiente(
        int $subpedidoId,
        string $error
    ): void {
        DB::table('comisiones_contables_pendientes')
            ->where('id_subpedido', $subpedidoId)
            ->update([
                'estado' => 'PENDIENTE',
                'intentos' => DB::raw('intentos + 1'),
                'ultimo_error' => $error,
                'updated_at' => now(),
            ]);
    }

    public function marcarProcesada(int $subpedidoId): void
    {
        DB::table('comisiones_contables_pendientes')
            ->where('id_subpedido', $subpedidoId)
            ->update([
                'estado' => 'PROCESADO',
                'fecha_procesamiento' => now(),
                'ultimo_error' => null,
                'updated_at' => now(),
            ]);
    }
}
