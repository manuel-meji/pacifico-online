<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Models\M7_Contabilidad\AsientoContable;
use App\Models\M7_Contabilidad\DetalleAsiento;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\DB;
use LogicException;

final class AsientoRepository
{
    public function __construct(private readonly ContextoComercio $contexto) {}

    public function buscarPorSubpedido(int $comercioId, int $subpedidoId, string $tipo): ?AsientoContable
    {
        $this->verificarContexto($comercioId);
        $fila = DB::table('asientos_contables')->where('id_comercio', $comercioId)
            ->where('id_subpedido', $subpedidoId)->where('tipo', $tipo)->first();

        if ($fila === null) {
            return null;
        }

        $asiento = new AsientoContable((int) $fila->id_asiento, (int) $fila->id_comercio, (int) $fila->id_pedido, (int) $fila->id_subpedido, $fila->evento_id, $fila->tipo_evento, $fila->tipo, $fila->fecha);
        foreach (DB::table('detalle_asientos')->where('id_asiento', $asiento->id)->orderBy('numero_linea')->get() as $detalle) {
            $asiento->agregarDetalle(new DetalleAsiento((int) $detalle->id_cuenta_contable, (string) $detalle->debe, (string) $detalle->haber));
        }

        return $asiento;
    }

    public function guardar(AsientoContable $asiento): AsientoContable
    {
        $this->verificarContexto($asiento->comercioId);
        if (! $asiento->estaBalanceado()) {
            throw new LogicException('No se puede guardar un asiento sin partida doble.');
        }

        $id = DB::table('asientos_contables')->insertGetId([
            'id_comercio' => $asiento->comercioId, 'id_pedido' => $asiento->pedidoId,
            'id_subpedido' => $asiento->subpedidoId, 'evento_id' => $asiento->eventoId,
            'tipo_evento' => $asiento->tipoEvento, 'tipo' => $asiento->tipo,
            'fecha' => $asiento->fecha, 'estado' => 'REGISTRADO',
            'created_at' => now(), 'updated_at' => now(),
        ], 'id_asiento');

        $guardado = new AsientoContable((int) $id, $asiento->comercioId, $asiento->pedidoId, $asiento->subpedidoId, $asiento->eventoId, $asiento->tipoEvento, $asiento->tipo, $asiento->fecha);
        foreach ($asiento->detalles() as $indice => $detalle) {
            DB::table('detalle_asientos')->insert([
                'id_asiento' => $id, 'id_cuenta_contable' => $detalle->cuentaId,
                'numero_linea' => $indice + 1, 'debe' => $detalle->debe, 'haber' => $detalle->haber,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $guardado->agregarDetalle($detalle);
        }

        return $guardado;
    }

    private function verificarContexto(int $comercioId): void
    {
        if (! $this->contexto->esModoSistema() && $this->contexto->idActual() !== $comercioId) {
            throw new LogicException('El comercio no corresponde al contexto actual.');
        }
    }
}
