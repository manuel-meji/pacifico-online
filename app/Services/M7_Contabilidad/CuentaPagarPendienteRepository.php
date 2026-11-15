<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use Illuminate\Support\Facades\DB;
use LogicException;

final class CuentaPagarPendienteRepository
{
    public function registrar(
        string $eventoId,
        int $pedidoId,
        int $subpedidoId,
        int $comercioId,
        string $fechaEvento,
    ): void {
        DB::table('cuentas_pagar_comercio_pendientes')->insertOrIgnore([
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

        $registro = DB::table('cuentas_pagar_comercio_pendientes')
            ->where('id_subpedido', $subpedidoId)
            ->first();

        if ($registro === null) {
            throw new LogicException(
                'No se pudo registrar la cuenta por pagar pendiente.'
            );
        }

        if (
            $registro->evento_id !== $eventoId
            || (int) $registro->id_pedido !== $pedidoId
            || (int) $registro->id_comercio !== $comercioId
        ) {
            throw new LogicException(
                'El subpedido ya tiene una entrega registrada con datos diferentes.'
            );
        }
    }

    public function pendientes(): array
    {
        return DB::table('cuentas_pagar_comercio_pendientes')
            ->where('estado', 'PENDIENTE')
            ->orderBy('id_cuenta_pagar_pendiente')
            ->get()
            ->all();
    }

    public function marcarProcesada(int $subpedidoId): void
    {
        DB::table('cuentas_pagar_comercio_pendientes')
            ->where('id_subpedido', $subpedidoId)
            ->update([
                'estado' => 'PROCESADO',
                'ultimo_error' => null,
                'fecha_procesamiento' => now(),
                'updated_at' => now(),
            ]);
    }

    public function registrarIntento(
        int $subpedidoId,
        ?string $error = null,
    ): void {
        DB::table('cuentas_pagar_comercio_pendientes')
            ->where('id_subpedido', $subpedidoId)
            ->where('estado', 'PENDIENTE')
            ->update([
                'intentos' => DB::raw('intentos + 1'),
                'ultimo_error' => $error,
                'updated_at' => now(),
            ]);
    }
}