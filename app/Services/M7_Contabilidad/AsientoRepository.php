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
    /** Centro creado por M7_PlanCuentasSeeder para cada comercio. */
    public const CENTRO_PREDETERMINADO = 'GEN';

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

        $datos = [
            'id_comercio' => $asiento->comercioId, 'id_pedido' => $asiento->pedidoId,
            'id_subpedido' => $asiento->subpedidoId, 'evento_id' => $asiento->eventoId,
            'tipo_evento' => $asiento->tipoEvento, 'tipo' => $asiento->tipo,
            'fecha' => $asiento->fecha, 'estado' => 'REGISTRADO',
            'created_at' => now(), 'updated_at' => now(),
        ];
        if ($asiento->pagoId !== null) {
            $datos['id_pago'] = $asiento->pagoId;
        }
        if ($asiento->asientoOriginalId !== null) {
            $datos['id_asiento_original'] = $asiento->asientoOriginalId;
        }
        $id = DB::table('asientos_contables')->insertGetId($datos, 'id_asiento');

        $guardado = new AsientoContable((int) $id, $asiento->comercioId, $asiento->pedidoId, $asiento->subpedidoId, $asiento->eventoId, $asiento->tipoEvento, $asiento->tipo, $asiento->fecha, $asiento->pagoId, $asiento->asientoOriginalId);
        [$cuentasResultado, $centro] = $this->imputacionPredeterminada($asiento);
        foreach ($asiento->detalles() as $indice => $detalle) {
            DB::table('detalle_asientos')->insert([
                'id_asiento' => $id, 'id_cuenta_contable' => $detalle->cuentaId,
                'id_centro_costo' => in_array($detalle->cuentaId, $cuentasResultado, true) ? $centro : null,
                'numero_linea' => $indice + 1, 'debe' => $detalle->debe, 'haber' => $detalle->haber,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $guardado->agregarDetalle($detalle);
        }

        return $guardado;
    }

    /**
     * Las líneas de ingreso y gasto se imputan al centro predeterminado activo del comercio;
     * si no existe, quedan sin centro y se reportan aparte.
     *
     * @return array{0: list<int>, 1: int|null}
     */
    private function imputacionPredeterminada(AsientoContable $asiento): array
    {
        $centro = DB::table('centros_costo')->where('id_comercio', $asiento->comercioId)
            ->where('codigo', self::CENTRO_PREDETERMINADO)->where('activo', true)->value('id_centro_costo');
        if ($centro === null) {
            return [[], null];
        }
        $cuentas = DB::table('cuentas_contables')->where('id_comercio', $asiento->comercioId)
            ->whereIn('id_cuenta_contable', array_map(fn (DetalleAsiento $detalle): int => $detalle->cuentaId, $asiento->detalles()))
            ->whereIn('tipo', ['INGRESO', 'GASTO'])->pluck('id_cuenta_contable')
            ->map(fn (int|string $cuenta): int => (int) $cuenta)->all();

        return [$cuentas, (int) $centro];
    }

    private function verificarContexto(int $comercioId): void
    {
        if (! $this->contexto->esModoSistema() && $this->contexto->idActual() !== $comercioId) {
            throw new LogicException('El comercio no corresponde al contexto actual.');
        }
    }
}
