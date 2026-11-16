<?php

namespace App\Services\M5_Pedidos;

use App\Contracts\M6_Pagos\PagosServiceInterface;
use App\Events\M5_Pedidos\DevolucionAprobada;
use App\Models\M5_Pedidos\CancelacionPedido;
use App\Models\M5_Pedidos\DevolucionEvidencia;
use App\Models\M5_Pedidos\DevolucionLinea;
use App\Models\M5_Pedidos\DevolucionSubpedido;
use App\Models\M5_Pedidos\PedidoLinea;
use App\Models\M5_Pedidos\PedidoMaestro;
use App\Models\M5_Pedidos\Subpedido;
use App\Services\M5_Pedidos\Excepciones\ErrorDevolucionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Devoluciones de subpedidos entregados (RF-019, RF-020, RF-021).
 *
 *  RF-019  El cliente solicita una devolución total o parcial (líneas y cantidades), con motivo
 *          y evidencia obligatorios, sobre un subpedido en estado "entregado".
 *  RF-020  El comercio revisa la solicitud y la aprueba o la rechaza (con motivo).
 *  RF-021  Al aprobar: M6 reembolsa al cliente (llamada directa por su contrato) y se publica
 *          DevolucionAprobada para que M4 reingrese al kardex y M2 revierta la comisión.
 *
 * Solo toca tablas de M5. Los demás módulos se alcanzan por su contrato o por evento.
 */
class DevolucionesService
{
    public function __construct(
        private readonly ?PagosServiceInterface $pagos = null,
    ) {}

    // ------------------------------------------------------------------ RF-019

    /**
     * @param  list<array{id_linea: int, cantidad: int}>  $lineasSolicitadas  Vacío = devolución total.
     * @param  list<UploadedFile>  $archivos  Evidencia (al menos uno).
     */
    public function solicitar(int $idCliente, int $idSubpedido, string $motivo, array $lineasSolicitadas, array $archivos): DevolucionSubpedido
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ErrorDevolucionException::validacion(['motivo' => 'Indique el motivo de la devolución.']);
        }

        if ($archivos === []) {
            throw ErrorDevolucionException::validacion(['evidencias' => 'Debe adjuntar al menos un archivo de evidencia.']);
        }

        $rutasGuardadas = [];
        $disco = $this->disco();

        try {
            return DB::transaction(function () use ($idCliente, $idSubpedido, $motivo, $lineasSolicitadas, $archivos, $disco, &$rutasGuardadas): DevolucionSubpedido {
                // Serializa solicitudes simultáneas sobre el mismo subpedido.
                $subpedido = Subpedido::withoutGlobalScopes()->with('lineas')->whereKey($idSubpedido)->lockForUpdate()->first()
                    ?? throw ErrorDevolucionException::noEncontrada('Subpedido');

                $pedido = PedidoMaestro::withoutGlobalScopes()->find($subpedido->id_pedido);

                if ($pedido === null || (int) $pedido->id_cliente !== $idCliente) {
                    throw ErrorDevolucionException::noAutorizado('El subpedido no pertenece al cliente.');
                }

                $estado = strtolower((string) $subpedido->estado_subpedido);

                if ($estado !== 'entregado') {
                    throw ErrorDevolucionException::estadoInvalido(
                        "Solo se puede solicitar devolución de un subpedido que ya fue entregado (estado actual: '{$estado}')."
                    );
                }

                $this->validarPlazo($subpedido);

                [$items, $tipo, $subtotalDevuelto] = $this->resolverLineas($subpedido, $lineasSolicitadas);
                $monto = $this->calcularMonto($subpedido, $items, $tipo);

                $devolucion = DevolucionSubpedido::create([
                    'id_pedido' => (int) $subpedido->id_pedido,
                    'id_subpedido' => (int) $subpedido->id_subpedido,
                    'id_comercio' => (int) $subpedido->id_comercio,
                    'id_cliente' => $idCliente,
                    'tipo' => $tipo,
                    'estado' => DevolucionSubpedido::ESTADO_SOLICITADA,
                    'motivo' => $motivo,
                    'monto_solicitado' => $monto,
                    'fecha_solicitud' => now(),
                ]);

                foreach ($items as $item) {
                    DevolucionLinea::create([
                        'id_devolucion' => $devolucion->id_devolucion,
                        'id_linea' => $item['id_linea'],
                        'id_variacion' => $item['id_variacion'],
                        'id_reserva' => $item['id_reserva'],
                        'cantidad' => $item['cantidad'],
                        'monto' => $item['monto'],
                    ]);
                }

                foreach ($archivos as $archivo) {
                    $ruta = Storage::disk($disco)->putFile("devoluciones/{$devolucion->id_devolucion}", $archivo);

                    if ($ruta === false) {
                        throw new RuntimeException('No se pudo guardar el archivo de evidencia.');
                    }

                    $rutasGuardadas[] = $ruta;

                    DevolucionEvidencia::create([
                        'id_devolucion' => $devolucion->id_devolucion,
                        'disco' => $disco,
                        'ruta' => $ruta,
                        'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                        'mime' => (string) ($archivo->getClientMimeType() ?: 'application/octet-stream'),
                        'tamano' => (int) $archivo->getSize(),
                    ]);
                }

                // Compatibilidad con las vistas existentes, que detectan la devolución por el prefijo del motivo.
                // requiere_reembolso se mantiene en false hasta que el comercio la apruebe.
                $subpedido->cancelaciones()->create([
                    'id_pedido' => (int) $subpedido->id_pedido,
                    'motivo' => $this->motivoLegado($devolucion, $motivo),
                    'requiere_reembolso' => false,
                    'fecha_cancelacion' => now(),
                ]);

                $this->registrarHito($subpedido, "Devolución #{$devolucion->id_devolucion} solicitada por el cliente: {$motivo}");

                return $devolucion->load(['lineas', 'evidencias']);
            });
        } catch (Throwable $e) {
            // Si la transacción se revirtió, los archivos ya subidos quedarían huérfanos.
            foreach ($rutasGuardadas as $ruta) {
                try {
                    Storage::disk($disco)->delete($ruta);
                } catch (Throwable) {
                }
            }

            throw $e;
        }
    }

    // ------------------------------------------------------------------ RF-020

    /**
     * Aprueba la devolución y dispara RF-021.
     *
     * @return array{devolucion: DevolucionSubpedido, advertencia: string|null}
     */
    public function aprobar(int $idComercio, int $idDevolucion, ?int $idUsuario = null): array
    {
        /** @var array{0: DevolucionSubpedido, 1: list<array<string, mixed>>, 2: float, 3: float} $resultado */
        $resultado = DB::transaction(function () use ($idComercio, $idDevolucion, $idUsuario): array {
            $devolucion = $this->cargarParaRevision($idComercio, $idDevolucion);

            $devolucion->update([
                'estado' => DevolucionSubpedido::ESTADO_APROBADA,
                'estado_reembolso' => DevolucionSubpedido::REEMBOLSO_PENDIENTE,
                'id_usuario_revision' => $idUsuario,
                'fecha_revision' => now(),
            ]);

            $this->actualizarCancelacionLegada($devolucion, requiereReembolso: true);

            $subpedido = Subpedido::withoutGlobalScopes()->with('lineas')->find($devolucion->id_subpedido);

            if ($subpedido !== null) {
                $this->registrarHito($subpedido, "Devolución #{$devolucion->id_devolucion} aprobada por el comercio.");
            }

            [$lineasEvento, $subtotalDevuelto, $subtotalSubpedido] = $this->datosParaEvento($devolucion, $subpedido);

            return [$devolucion, $lineasEvento, $subtotalDevuelto, $subtotalSubpedido];
        });

        [$devolucion, $lineasEvento, $subtotalDevuelto, $subtotalSubpedido] = $resultado;

        // M6: reembolso al cliente. Un fallo no revierte la aprobación; queda registrado para reintentar.
        $advertencia = $this->solicitarReembolso($devolucion);

        // M4 (reingreso a kardex) y M2 (reversión de comisión) reaccionan a este evento.
        try {
            DevolucionAprobada::dispatch($devolucion->fresh(), $lineasEvento, $subtotalDevuelto, $subtotalSubpedido);
        } catch (Throwable $e) {
            Log::error('M5: error al publicar DevolucionAprobada.', [
                'id_devolucion' => $devolucion->id_devolucion,
                'exception' => $e->getMessage(),
            ]);
        }

        return [
            'devolucion' => $devolucion->fresh(['lineas', 'evidencias']),
            'advertencia' => $advertencia,
        ];
    }

    public function rechazar(int $idComercio, int $idDevolucion, string $motivoRechazo, ?int $idUsuario = null): DevolucionSubpedido
    {
        $motivoRechazo = trim($motivoRechazo);

        if ($motivoRechazo === '') {
            throw ErrorDevolucionException::validacion(['motivo_rechazo' => 'Indique el motivo del rechazo.']);
        }

        return DB::transaction(function () use ($idComercio, $idDevolucion, $motivoRechazo, $idUsuario): DevolucionSubpedido {
            $devolucion = $this->cargarParaRevision($idComercio, $idDevolucion);

            $devolucion->update([
                'estado' => DevolucionSubpedido::ESTADO_RECHAZADA,
                'motivo_rechazo' => $motivoRechazo,
                'id_usuario_revision' => $idUsuario,
                'fecha_revision' => now(),
            ]);

            // Deja de coincidir con el prefijo "[DEVOLUCIÓN]" que usan las vistas existentes.
            CancelacionPedido::query()
                ->where('id_subpedido', $devolucion->id_subpedido)
                ->where('motivo', 'like', $this->patronLegado($devolucion))
                ->update(['motivo' => '[DEVOLUCIÓN RECHAZADA] '.$motivoRechazo.' (solicitud #'.$devolucion->id_devolucion.')']);

            $subpedido = Subpedido::withoutGlobalScopes()->find($devolucion->id_subpedido);

            if ($subpedido !== null) {
                $this->registrarHito($subpedido, "Devolución #{$devolucion->id_devolucion} rechazada por el comercio: {$motivoRechazo}");
            }

            return $devolucion->load(['lineas', 'evidencias']);
        });
    }

    /**
     * Reintenta el reembolso de M6 de una devolución aprobada cuyo reembolso falló.
     *
     * @return array{devolucion: DevolucionSubpedido, advertencia: string|null}
     */
    public function reintentarReembolso(int $idComercio, int $idDevolucion): array
    {
        $devolucion = DevolucionSubpedido::query()->find($idDevolucion)
            ?? throw ErrorDevolucionException::noEncontrada();

        if ((int) $devolucion->id_comercio !== $idComercio) {
            throw ErrorDevolucionException::noAutorizado();
        }

        if ($devolucion->estado !== DevolucionSubpedido::ESTADO_APROBADA
            || $devolucion->estado_reembolso !== DevolucionSubpedido::REEMBOLSO_ERROR
            || $devolucion->id_reembolso_m6 !== null) {
            throw ErrorDevolucionException::estadoInvalido('Solo se puede reintentar el reembolso de una devolución aprobada cuyo reembolso falló.');
        }

        $advertencia = $this->solicitarReembolso($devolucion);

        return ['devolucion' => $devolucion->fresh(['lineas', 'evidencias']), 'advertencia' => $advertencia];
    }

    // ------------------------------------------------------------------ consultas

    /** @return Collection<int, DevolucionSubpedido> */
    public function listarDelCliente(int $idCliente, ?string $estado = null): Collection
    {
        return DevolucionSubpedido::query()
            ->with(['lineas', 'evidencias'])
            ->where('id_cliente', $idCliente)
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('id_devolucion')
            ->get();
    }

    /** @return Collection<int, DevolucionSubpedido> */
    public function listarDelComercio(int $idComercio, ?string $estado = null): Collection
    {
        return DevolucionSubpedido::query()
            ->with(['lineas', 'evidencias'])
            ->where('id_comercio', $idComercio)
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('id_devolucion')
            ->get();
    }

    /** @return Collection<int, DevolucionSubpedido> */
    public function listarPorSubpedido(int $idSubpedido, ?int $idCliente, ?int $idComercio): Collection
    {
        return DevolucionSubpedido::query()
            ->with(['lineas', 'evidencias'])
            ->where('id_subpedido', $idSubpedido)
            ->when($idCliente !== null, fn ($q) => $q->where('id_cliente', $idCliente))
            ->when($idComercio !== null, fn ($q) => $q->where('id_comercio', $idComercio))
            ->orderByDesc('id_devolucion')
            ->get();
    }

    public function obtener(int $idDevolucion, ?int $idCliente, ?int $idComercio): DevolucionSubpedido
    {
        $devolucion = DevolucionSubpedido::query()->with(['lineas', 'evidencias'])->find($idDevolucion)
            ?? throw ErrorDevolucionException::noEncontrada();

        $esDelCliente = $idCliente !== null && (int) $devolucion->id_cliente === $idCliente;
        $esDelComercio = $idComercio !== null && (int) $devolucion->id_comercio === $idComercio;

        if (! $esDelCliente && ! $esDelComercio) {
            throw ErrorDevolucionException::noAutorizado();
        }

        return $devolucion;
    }

    public function descargarEvidencia(int $idDevolucion, int $idEvidencia, ?int $idCliente, ?int $idComercio): StreamedResponse
    {
        $this->obtener($idDevolucion, $idCliente, $idComercio);

        $evidencia = DevolucionEvidencia::query()
            ->where('id_devolucion', $idDevolucion)
            ->whereKey($idEvidencia)
            ->first() ?? throw ErrorDevolucionException::noEncontrada('Evidencia');

        return Storage::disk($evidencia->disco)->response($evidencia->ruta, $evidencia->nombre_original);
    }

    // ------------------------------------------------------------------ internos

    private function disco(): string
    {
        return (string) (config('devoluciones.disco') ?: config('filesystems.default'));
    }

    private function validarPlazo(Subpedido $subpedido): void
    {
        $plazo = (int) config('devoluciones.plazo_dias', 0);

        if ($plazo <= 0) {
            return;
        }

        $fechaEntrega = $subpedido->estados()->where('estado', 'entregado')->orderBy('fecha_estado')->value('fecha_estado');

        if ($fechaEntrega !== null && now()->diffInDays($fechaEntrega, absolute: true) > $plazo) {
            throw ErrorDevolucionException::estadoInvalido(
                "El plazo para solicitar devoluciones ({$plazo} días desde la entrega) ya venció."
            );
        }
    }

    /**
     * Valida las líneas pedidas contra lo que aún se puede devolver.
     *
     * @param  list<array{id_linea: int, cantidad: int}>  $solicitadas
     * @return array{0: list<array{id_linea: int, id_variacion: int, id_reserva: string|null, cantidad: int, monto: float, bruto: float}>, 1: string, 2: float}
     */
    private function resolverLineas(Subpedido $subpedido, array $solicitadas): array
    {
        $lineas = $subpedido->lineas->keyBy(fn (PedidoLinea $l): int => (int) $l->id_linea);

        if ($lineas->isEmpty()) {
            throw ErrorDevolucionException::validacion(['lineas' => 'El subpedido no tiene líneas.']);
        }

        $comprometidas = DevolucionLinea::query()
            ->join('devoluciones_subpedido as d', 'd.id_devolucion', '=', 'devoluciones_lineas.id_devolucion')
            ->where('d.id_subpedido', $subpedido->id_subpedido)
            ->whereIn('d.estado', [DevolucionSubpedido::ESTADO_SOLICITADA, DevolucionSubpedido::ESTADO_APROBADA])
            ->groupBy('devoluciones_lineas.id_linea')
            ->selectRaw('devoluciones_lineas.id_linea as id_linea, SUM(devoluciones_lineas.cantidad) as total')
            ->pluck('total', 'id_linea');

        $disponible = $lineas->map(fn (PedidoLinea $l, int $id): int => max(0, (int) $l->cantidad - (int) ($comprometidas[$id] ?? 0)));

        if ($disponible->sum() === 0) {
            throw ErrorDevolucionException::yaEnCurso('Todas las unidades de este subpedido ya tienen una devolución solicitada o aprobada.');
        }

        // Sin líneas indicadas: devolución total (todo lo que aún quede disponible).
        if ($solicitadas === []) {
            $solicitadas = $disponible
                ->filter(fn (int $cant): bool => $cant > 0)
                ->map(fn (int $cant, int $id): array => ['id_linea' => $id, 'cantidad' => $cant])
                ->values()
                ->all();
        }

        $pedidas = [];

        foreach ($solicitadas as $pedida) {
            $idLinea = (int) ($pedida['id_linea'] ?? 0);
            $cantidad = (int) ($pedida['cantidad'] ?? 0);

            if (! $lineas->has($idLinea)) {
                throw ErrorDevolucionException::validacion(['lineas' => "La línea {$idLinea} no pertenece al subpedido."]);
            }

            if (isset($pedidas[$idLinea])) {
                throw ErrorDevolucionException::validacion(['lineas' => "La línea {$idLinea} está repetida."]);
            }

            if ($cantidad < 1 || $cantidad > $disponible[$idLinea]) {
                throw ErrorDevolucionException::validacion(
                    ['lineas' => "La cantidad de la línea {$idLinea} debe estar entre 1 y {$disponible[$idLinea]}."],
                );
            }

            $pedidas[$idLinea] = $cantidad;
        }

        $subtotalLineas = (float) $lineas->sum(fn (PedidoLinea $l): float => $l->calcularSubtotalLinea());
        $descuentoSubpedido = (float) ($subpedido->descuento ?? 0.0);

        $items = [];
        $subtotalDevuelto = 0.0;
        $esTotal = true;

        foreach ($lineas as $idLinea => $linea) {
            $cantidad = $pedidas[$idLinea] ?? 0;

            if ($cantidad !== (int) $linea->cantidad) {
                $esTotal = false;
            }

            if ($cantidad === 0) {
                continue;
            }

            $bruto = round($cantidad * (float) $linea->precio_unitario, 2);
            $impuesto = $bruto * ((float) $linea->tarifa_impuesto / 100);
            $descuento = $subtotalLineas > 0 ? $descuentoSubpedido * ($bruto / $subtotalLineas) : 0.0;

            $items[] = [
                'id_linea' => (int) $idLinea,
                'id_variacion' => (int) $linea->id_variacion,
                'id_reserva' => $linea->id_reserva !== null ? (string) $linea->id_reserva : null,
                'cantidad' => $cantidad,
                'bruto' => $bruto,
                'monto' => round(max(0.0, $bruto + $impuesto - $descuento), 2),
            ];

            $subtotalDevuelto += $bruto;
        }

        return [$items, $esTotal ? DevolucionSubpedido::TIPO_TOTAL : DevolucionSubpedido::TIPO_PARCIAL, round($subtotalDevuelto, 2)];
    }

    /**
     * Total: el monto del subpedido (incluye envío). Parcial: suma de las líneas, sin pasar
     * de lo que aún queda por reembolsar del subpedido.
     *
     * @param  list<array{monto: float}>  $items
     */
    private function calcularMonto(Subpedido $subpedido, array $items, string $tipo): float
    {
        $totalSubpedido = round((float) $subpedido->total, 2);

        if ($tipo === DevolucionSubpedido::TIPO_TOTAL) {
            return $totalSubpedido;
        }

        $yaComprometido = (float) DevolucionSubpedido::query()
            ->where('id_subpedido', $subpedido->id_subpedido)
            ->whereIn('estado', [DevolucionSubpedido::ESTADO_SOLICITADA, DevolucionSubpedido::ESTADO_APROBADA])
            ->sum('monto_solicitado');

        $restante = round($totalSubpedido - $yaComprometido, 2);
        $monto = round(array_sum(array_column($items, 'monto')), 2);
        $monto = min($monto, $restante);

        if ($monto <= 0) {
            throw ErrorDevolucionException::yaEnCurso('No queda monto por reembolsar en este subpedido.');
        }

        return $monto;
    }

    private function cargarParaRevision(int $idComercio, int $idDevolucion): DevolucionSubpedido
    {
        $devolucion = DevolucionSubpedido::query()->lockForUpdate()->find($idDevolucion)
            ?? throw ErrorDevolucionException::noEncontrada();

        if ((int) $devolucion->id_comercio !== $idComercio) {
            throw ErrorDevolucionException::noAutorizado('La devolución pertenece a otro comercio.');
        }

        if ($devolucion->estado !== DevolucionSubpedido::ESTADO_SOLICITADA) {
            throw ErrorDevolucionException::estadoInvalido(
                "La devolución ya fue revisada (estado actual: '{$devolucion->estado}')."
            );
        }

        return $devolucion;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: float, 2: float}
     */
    private function datosParaEvento(DevolucionSubpedido $devolucion, ?Subpedido $subpedido): array
    {
        $lineasDevueltas = $devolucion->lineas()->get();
        $preciosLinea = PedidoLinea::query()
            ->whereIn('id_linea', $lineasDevueltas->pluck('id_linea'))
            ->get()
            ->keyBy('id_linea');

        $lineasEvento = [];
        $subtotalDevuelto = 0.0;

        foreach ($lineasDevueltas as $linea) {
            $precio = (float) ($preciosLinea[$linea->id_linea]->precio_unitario ?? 0.0);
            $subtotalDevuelto += $linea->cantidad * $precio;

            $lineasEvento[] = [
                'id_linea' => (int) $linea->id_linea,
                'id_variacion' => (int) $linea->id_variacion,
                'id_reserva' => $linea->id_reserva !== null ? (string) $linea->id_reserva : null,
                'cantidad' => (int) $linea->cantidad,
                'monto' => (float) $linea->monto,
            ];
        }

        $subtotalSubpedido = $subpedido !== null
            ? (float) $subpedido->lineas->sum(fn (PedidoLinea $l): float => $l->calcularSubtotalLinea())
            : 0.0;

        return [$lineasEvento, round($subtotalDevuelto, 2), round($subtotalSubpedido, 2)];
    }

    /**
     * Pide el reembolso a M6 por su contrato (misma convención que PedidosService::solicitarReembolsoM6).
     *
     * @return string|null Advertencia para el comercio si el reembolso no quedó procesado.
     */
    private function solicitarReembolso(DevolucionSubpedido $devolucion): ?string
    {
        $pagos = $this->pagos;

        if ($pagos === null || ! method_exists($pagos, 'solicitarReembolso')) {
            return $this->marcarErrorReembolso($devolucion, 'M6 no está disponible para solicitar reembolsos.');
        }

        try {
            $respuesta = $pagos->solicitarReembolso(
                (int) $devolucion->id_pedido,
                (float) $devolucion->monto_solicitado,
                "[DEVOLUCIÓN #{$devolucion->id_devolucion}] {$devolucion->motivo}",
                (int) $devolucion->id_subpedido,
            );
        } catch (Throwable $e) {
            Log::error('M5: error al solicitar el reembolso de una devolución en M6.', [
                'id_devolucion' => $devolucion->id_devolucion,
                'exception' => $e->getMessage(),
            ]);

            return $this->marcarErrorReembolso($devolucion, $e->getMessage());
        }

        $estadoM6 = strtolower((string) ($respuesta['estado'] ?? ''));

        if ($estadoM6 === 'rechazado') {
            return $this->marcarErrorReembolso($devolucion, 'M6 rechazó el reembolso.');
        }

        $devolucion->update([
            'estado_reembolso' => $estadoM6 === 'procesado'
                ? DevolucionSubpedido::REEMBOLSO_PROCESADO
                : DevolucionSubpedido::REEMBOLSO_PENDIENTE,
            'id_reembolso_m6' => $respuesta['id_reembolso'] ?? null,
            'monto_reembolsado' => $respuesta['monto_reembolsado'] ?? $devolucion->monto_solicitado,
            'error_reembolso' => null,
        ]);

        return null;
    }

    private function marcarErrorReembolso(DevolucionSubpedido $devolucion, string $detalle): string
    {
        $devolucion->update([
            'estado_reembolso' => DevolucionSubpedido::REEMBOLSO_ERROR,
            'error_reembolso' => mb_substr($detalle, 0, 1000),
        ]);

        return 'La devolución quedó aprobada, pero el reembolso en Pagos (M6) no se pudo procesar. Puede reintentarlo.';
    }

    private function registrarHito(Subpedido $subpedido, string $motivo): void
    {
        $estado = strtolower((string) $subpedido->estado_subpedido);

        $subpedido->estados()->create([
            'id_pedido' => (int) $subpedido->id_pedido,
            'estado_anterior' => $estado,
            'estado' => $estado,
            'motivo' => $motivo,
            'fecha_estado' => now(),
        ]);
    }

    private function motivoLegado(DevolucionSubpedido $devolucion, string $motivo): string
    {
        return "[DEVOLUCIÓN] {$motivo} (solicitud #{$devolucion->id_devolucion})";
    }

    private function patronLegado(DevolucionSubpedido $devolucion): string
    {
        return '[DEVOLUCIÓN]%(solicitud #'.$devolucion->id_devolucion.')';
    }

    private function actualizarCancelacionLegada(DevolucionSubpedido $devolucion, bool $requiereReembolso): void
    {
        CancelacionPedido::query()
            ->where('id_subpedido', $devolucion->id_subpedido)
            ->where('motivo', 'like', $this->patronLegado($devolucion))
            ->update(['requiere_reembolso' => $requiereReembolso]);
    }
}
