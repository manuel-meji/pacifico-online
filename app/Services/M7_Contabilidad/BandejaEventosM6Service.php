<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Events\M6_Pagos\FacturaGenerada;
use App\Events\M6_Pagos\PagoConfirmado;
use App\Events\M6_Pagos\ReembolsoProcesado;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Throwable;

final class BandejaEventosM6Service
{
    public function __construct(
        private readonly IntegracionFinancieraM6Service $integracion,
        private readonly ContextoComercio $contexto,
        private readonly CuadrePagoM6Service $cuadre,
    ) {}

    public function recibir(PagoConfirmado|FacturaGenerada|ReembolsoProcesado $evento): bool
    {
        if (! Str::isUuid($evento->idEvento) || $evento->idPago === null || $evento->idPago <= 0) {
            throw new InvalidArgumentException('El evento financiero requiere UUID y pago válidos.');
        }
        $tipo = class_basename($evento);
        $payload = json_encode($evento->payload(), JSON_THROW_ON_ERROR);
        $huella = hash('sha256', $payload);
        DB::transaction(function () use ($evento, $tipo, $payload, $huella): void {
            DB::table('bandeja_eventos_m6')->insertOrIgnore([
                'evento_id' => $evento->idEvento, 'tipo_evento' => $tipo,
                'id_pago' => $evento->idPago,
                'id_comercio' => $evento instanceof PagoConfirmado ? null : $evento->idComercio,
                'payload' => $payload, 'huella' => $huella, 'estado' => 'PENDIENTE',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $existente = DB::table('bandeja_eventos_m6')->where('evento_id', $evento->idEvento)->lockForUpdate()->first();
            if ($existente === null || ! hash_equals($existente->huella, $huella)) {
                throw new LogicException('El UUID financiero ya fue recibido con datos diferentes.');
            }
        });
        $this->reintentarPendientes($evento->idPago);

        return DB::table('bandeja_eventos_m6')->where('evento_id', $evento->idEvento)->value('estado') === 'PROCESADO';
    }

    /** @return array{procesados:int, pendientes:int} */
    public function reintentarPendientes(?int $pagoId = null, ?int $comercioId = null): array
    {
        $procesados = 0;
        $consulta = DB::table('bandeja_eventos_m6')->where('estado', 'PENDIENTE')
            ->when($pagoId !== null, fn ($q) => $q->where('id_pago', $pagoId))
            ->when($comercioId !== null, fn ($q) => $q->where(function ($filtro) use ($comercioId): void {
                $filtro->where('id_comercio', $comercioId)->orWhere(function ($global) use ($comercioId): void {
                    $global->where('tipo_evento', 'PagoConfirmado')->whereExists(function ($facturas) use ($comercioId): void {
                        $facturas->selectRaw('1')->from('bandeja_eventos_m6 as fuentes')
                            ->whereColumn('fuentes.id_pago', 'bandeja_eventos_m6.id_pago')
                            ->where('fuentes.tipo_evento', 'FacturaGenerada')->where('fuentes.id_comercio', $comercioId);
                    });
                });
            }))
            ->orderByRaw("CASE tipo_evento WHEN 'PagoConfirmado' THEN 0 WHEN 'FacturaGenerada' THEN 1 ELSE 2 END")
            ->orderBy('created_at')->orderBy('evento_id');
        foreach ($consulta->cursor() as $entrada) {
            try {
                $this->contexto->ejecutarComoSistema(function () use ($entrada): void {
                    DB::transaction(function () use ($entrada): void {
                        $actual = DB::table('bandeja_eventos_m6')->where('evento_id', $entrada->evento_id)->lockForUpdate()->first();
                        if ($actual->estado === 'PROCESADO') {
                            return;
                        }
                        $evento = $this->desdePayload($actual->tipo_evento, json_decode($actual->payload, true, 512, JSON_THROW_ON_ERROR));
                        match (true) {
                            $evento instanceof PagoConfirmado => $this->integracion->procesarPago($evento),
                            $evento instanceof FacturaGenerada => $this->integracion->procesarFactura($evento),
                            $evento instanceof ReembolsoProcesado => $this->integracion->procesarReembolso($evento),
                        };
                        DB::table('bandeja_eventos_m6')->where('evento_id', $entrada->evento_id)->update([
                            'estado' => 'PROCESADO', 'ultimo_error' => null, 'fecha_procesamiento' => now(),
                            'intentos' => DB::raw('intentos + 1'), 'updated_at' => now(),
                        ]);
                    });
                });
                $procesados++;
            } catch (Throwable $error) {
                DB::table('bandeja_eventos_m6')->where('evento_id', $entrada->evento_id)->where('estado', 'PENDIENTE')->update([
                    'ultimo_error' => $error->getMessage(), 'intentos' => DB::raw('intentos + 1'), 'updated_at' => now(),
                ]);
                Log::error('M7: evento financiero pendiente de recuperación o revisión.', [
                    'evento_id' => $entrada->evento_id, 'id_pago' => $entrada->id_pago, 'exception' => $error,
                ]);
            }
        }
        $this->actualizarCuadres($pagoId);

        return [
            'procesados' => $procesados,
            'pendientes' => (clone $consulta)->reorder()->count(),
        ];
    }

    public function desdePayload(string $tipo, array $datos): PagoConfirmado|FacturaGenerada|ReembolsoProcesado
    {
        $campos = ['id_evento', 'id_pago', 'id_pedido', 'monto', 'fecha'];
        $campos = match ($tipo) {
            'PagoConfirmado' => $campos,
            'FacturaGenerada' => [...$campos, 'id_factura', 'id_subpedido', 'id_comercio', 'xml', 'subtotal', 'impuesto', 'envio'],
            'ReembolsoProcesado' => [...$campos, 'id_reembolso', 'id_subpedido', 'id_comercio', 'nota_credito_id'],
            default => throw new InvalidArgumentException('Tipo de evento financiero no soportado.'),
        };
        foreach ($campos as $campo) {
            if (! array_key_exists($campo, $datos)) {
                throw new InvalidArgumentException("Falta el campo {$campo} en el evento de M6.");
            }
        }

        return match ($tipo) {
            'PagoConfirmado' => new PagoConfirmado($datos['id_evento'], $datos['id_pedido'], $datos['id_pago'], $datos['monto'], $datos['fecha']),
            'FacturaGenerada' => new FacturaGenerada($datos['id_evento'], $datos['id_factura'], $datos['id_pedido'], $datos['id_subpedido'], $datos['id_comercio'], $datos['monto'], $datos['xml'], $datos['fecha'], $datos['id_pago'], $datos['subtotal'], $datos['impuesto'], $datos['envio']),
            'ReembolsoProcesado' => new ReembolsoProcesado($datos['id_evento'], $datos['id_pedido'], $datos['id_subpedido'], $datos['id_comercio'], $datos['id_reembolso'], $datos['monto'], $datos['nota_credito_id'], $datos['fecha'], $datos['id_pago']),
        };
    }

    private function actualizarCuadres(?int $pagoId): void
    {
        foreach (DB::table('pagos_contables_m6')->when($pagoId !== null, fn ($q) => $q->where('id_pago', $pagoId))->cursor() as $pago) {
            try {
                DB::transaction(function () use ($pago): void {
                    DB::table('pagos_contables_m6')->where('id_pago', $pago->id_pago)->lockForUpdate()->first();
                    $confirmado = new PagoConfirmado('', (int) $pago->id_pedido, (int) $pago->id_pago, (int) $pago->monto_centimos / 100, $pago->fecha);
                    $facturas = [];
                    foreach (DB::table('bandeja_eventos_m6')->where('id_pago', $pago->id_pago)->where('tipo_evento', 'FacturaGenerada')->orderBy('created_at')->get() as $entrada) {
                        $evento = $this->desdePayload('FacturaGenerada', json_decode($entrada->payload, true, 512, JSON_THROW_ON_ERROR));
                        if ($evento instanceof FacturaGenerada) {
                            $facturas[] = $evento;
                        }
                    }
                    $resultado = $this->cuadre->evaluar($confirmado, $facturas);
                    DB::table('cuadres_pagos_m6')->updateOrInsert(['id_pago' => $pago->id_pago], [
                        'facturado_centimos' => $resultado['facturado_centimos'],
                        'saldo_pendiente_centimos' => $resultado['saldo_pendiente_centimos'],
                        'exceso_centimos' => $resultado['exceso_centimos'],
                        'estado' => $resultado['estado'], 'ultimo_error' => null, 'updated_at' => now(),
                    ]);
                    if ($resultado['estado'] !== 'COMPLETO_POR_IMPORTES' && Carbon::parse($pago->fecha, 'UTC')->addHours(24)->lte(now())
                        && DB::table('cuadres_pagos_m6')->where('id_pago', $pago->id_pago)->whereNull('fecha_alerta')->update(['fecha_alerta' => now()])) {
                        Log::warning('M7: pago sin cuadre después de 24 horas.', ['id_pago' => $pago->id_pago, ...$resultado]);
                    }
                });
            } catch (InvalidArgumentException|LogicException|\TypeError|\JsonException $error) {
                DB::table('cuadres_pagos_m6')->updateOrInsert(['id_pago' => $pago->id_pago], [
                    'facturado_centimos' => null, 'saldo_pendiente_centimos' => null, 'exceso_centimos' => null,
                    'estado' => 'REQUIERE_REVISION', 'ultimo_error' => $error->getMessage(), 'updated_at' => now(),
                ]);
                Log::error('M7: no se pudo evaluar el cuadre del pago.', ['id_pago' => $pago->id_pago, 'exception' => $error]);
            }
        }
    }
}
