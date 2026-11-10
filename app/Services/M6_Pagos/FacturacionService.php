<?php

declare(strict_types=1);

namespace App\Services\M6_Pagos;

use App\Contracts\M6_Pagos\HaciendaInterface;
use App\Enums\M6_Pagos\CodigoErrorM6;
use App\Events\M6_Pagos\FacturaGenerada;
use App\Exceptions\M6_Pagos\ErrorContratoException;
use App\Models\M6_Pagos\EstadoFacturaHacienda;
use App\Models\M6_Pagos\EstadoPago;
use App\Models\M6_Pagos\FacturaElectronica;
use App\Models\M6_Pagos\Pago;
use App\Services\M6_Pagos\Clients\ClienteClient;
use App\Services\M6_Pagos\Clients\ClienteDTO;
use App\Services\M6_Pagos\Clients\ComercioClient;
use App\Services\M6_Pagos\Clients\PedidoClient;
use App\Services\M6_Pagos\Clients\SubpedidoDTO;
use App\Services\M6_Pagos\Facturacion\GeneradorXmlComprobante;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * CU-004 Emitir factura electrónica (RF-004, RN-003).
 *
 * - Una factura por subpedido/comercio (decisión 3.1 del Comité).
 * - Idempotente: facturas_electronicas.id_subpedido es UNIQUE; si la factura
 *   ya existe se devuelve la misma y no se vuelve a publicar FacturaGenerada.
 * - Facturar nunca deshace un cobro: si M1 o M2 fallan, o los datos fiscales
 *   están incompletos, la factura de ese subpedido queda sin emitir y se
 *   reintenta con facturarPedido() (PagosService::generarFactura).
 */
class FacturacionService
{
    public function __construct(
        private readonly PedidoClient $pedidos,
        private readonly ComercioClient $comercios,
        private readonly ClienteClient $clientes,
        private readonly HaciendaInterface $hacienda,
        private readonly GeneradorXmlComprobante $generador,
        private readonly AmbitoAccesoM6 $ambito,
    ) {}

    /**
     * Ruta automática, después de publicar PagoConfirmado. Nunca lanza:
     * cada fallo queda en el log diario para reintentarlo.
     */
    public function facturarPagoConfirmado(Pago $pago): void
    {
        try {
            $errores = $this->facturar($pago)['errores'];
        } catch (Throwable $e) {
            $errores = [$e];
        }

        foreach ($errores as $error) {
            Log::warning("[M6_Pagos] Factura pendiente del pago {$pago->id_pago} (pedido {$pago->id_pedido}): {$error->getMessage()}");
        }
    }

    /**
     * Ruta explícita (fachada y reintentos). Factura los subpedidos que falten y
     * reenvía a Hacienda las que quedaron pendientes de envío.
     *
     * @return Collection<int, FacturaElectronica>
     *
     * @throws ErrorContratoException PAGO_NO_CONFIRMADO (RN-003) | DATOS_FISCALES_INCOMPLETOS | COMERCIO_NO_ENCONTRADO
     */
    public function facturarPedido(int $idPedido): Collection
    {
        $pago = $this->pagoConfirmadoDe($idPedido) ?? throw ErrorContratoException::de(CodigoErrorM6::PagoNoConfirmado);

        ['facturas' => $facturas, 'errores' => $errores] = $this->facturar($pago);

        // Las facturas que sí se pudieron emitir quedan guardadas; se informa el primer fallo.
        if ($errores !== []) {
            throw $errores[0];
        }

        return $facturas;
    }

    /** @return Collection<int, FacturaElectronica> */
    public function listarPorPedido(int $idPedido): Collection
    {
        return $this->ambito->filtrar(FacturaElectronica::query())
            ->with(['estado', 'pago'])
            ->whereHas('pago', fn ($pago) => $pago->where('id_pedido', $idPedido))
            ->orderBy('id_factura_electronica')
            ->get();
    }

    /** @throws ErrorContratoException FACTURA_NO_ENCONTRADA */
    public function obtener(int $idFactura): FacturaElectronica
    {
        $factura = FacturaElectronica::query()->with(['estado', 'pago'])->find($idFactura)
            ?? throw ErrorContratoException::de(CodigoErrorM6::FacturaNoEncontrada);

        $this->ambito->exigir($factura->id_cliente, $factura->id_comercio);

        return $factura;
    }

    /**
     * @return array{facturas: Collection<int, FacturaElectronica>, errores: list<Throwable>}
     */
    private function facturar(Pago $pago): array
    {
        // RN-003: solo se factura un pago confirmado.
        if ($pago->codigoEstado() !== EstadoPago::CONFIRMADO) {
            throw ErrorContratoException::de(CodigoErrorM6::PagoNoConfirmado);
        }

        $pedido = $this->pedidos->obtenerDatosPedido($pago->id_pedido);

        // El receptor se pide a M1 una sola vez y solo si hace falta emitir algo.
        $receptor = null;
        $obtenerReceptor = function () use (&$receptor, $pago): ClienteDTO {
            return $receptor ??= $this->clientes->obtenerCliente($pago->id_cliente);
        };

        $facturas = new Collection;
        $errores = [];

        foreach ($pedido->subpedidos as $subpedido) {
            try {
                $facturas->push($this->facturarSubpedido($pago, $subpedido, $obtenerReceptor));
            } catch (Throwable $e) {
                $errores[] = $e;
            }
        }

        return ['facturas' => $facturas, 'errores' => $errores];
    }

    /** @param Closure(): ClienteDTO $obtenerReceptor */
    private function facturarSubpedido(Pago $pago, SubpedidoDTO $subpedido, Closure $obtenerReceptor): FacturaElectronica
    {
        if ($existente = $this->facturaDe($subpedido->idSubpedido)) {
            if ($existente->codigoEstado() === EstadoFacturaHacienda::PENDIENTE_ENVIO) {
                $this->enviarAHacienda($existente);
            }

            return $existente;
        }

        $emisor = $this->comercios->obtenerDatosFiscales($subpedido->idComercio);
        $receptor = $obtenerReceptor();

        $total = round($subpedido->subtotal + $subpedido->impuesto + $subpedido->envio, 2);
        $fecha = now();
        $consecutivo = $this->generador->consecutivo(GeneradorXmlComprobante::TIPO_FACTURA, $subpedido->idSubpedido);
        $identificacionReceptor = trim($receptor->identificacionTributaria) !== '' ? trim($receptor->identificacionTributaria) : null;

        $xml = $this->generador->factura([
            'clave' => $this->generador->clave($emisor->cedulaJuridica, $consecutivo, $fecha),
            'consecutivo' => $consecutivo,
            'fecha' => $fecha,
            'emisor' => ['nombre' => $emisor->razonSocial, 'cedula' => $emisor->cedulaJuridica, 'condicion' => $emisor->condicionContribuyente],
            'receptor' => ['nombre' => $receptor->nombre, 'identificacion' => $identificacionReceptor],
            'subtotal' => $subpedido->subtotal,
            'impuesto' => $subpedido->impuesto,
            'envio' => $subpedido->envio,
            'total' => $total,
        ]);

        try {
            $factura = FacturaElectronica::query()->create([
                'id_subpedido' => $subpedido->idSubpedido,
                'id_comercio' => $subpedido->idComercio,
                'id_pago' => $pago->id_pago,
                'id_cliente' => $pago->id_cliente,
                'id_estado_factura_hacienda' => EstadoFacturaHacienda::idDe(EstadoFacturaHacienda::PENDIENTE_ENVIO),
                'consecutivo' => $consecutivo,
                'xml_documento' => $xml,
                'monto_total' => $total,
                'razon_social_emisor' => $emisor->razonSocial,
                'cedula_juridica_emisor' => $emisor->cedulaJuridica,
                'identificacion_receptor' => $identificacionReceptor,
                'fecha_emision' => $fecha,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Otra confirmación del mismo pago facturó el subpedido en paralelo.
            return $this->facturaDe($subpedido->idSubpedido) ?? throw ErrorContratoException::de(CodigoErrorM6::FacturaNoEncontrada);
        }

        $factura->load(['estado', 'pago']);
        $this->enviarAHacienda($factura);

        event(new FacturaGenerada(
            (string) Str::uuid(),
            $factura->id_factura_electronica,
            $pago->id_pedido,
            $factura->id_subpedido,
            $factura->id_comercio,
            (float) $factura->monto_total,
            $factura->xml_documento,
            $fecha->toIso8601String(),
        ));

        return $factura;
    }

    /** pendiente_envio → (Hacienda) enviada | aceptada | rechazada. Si Hacienda no responde, queda pendiente. */
    private function enviarAHacienda(FacturaElectronica $factura): void
    {
        try {
            $respuesta = $this->hacienda->enviarComprobante((string) $factura->claveNumerica(), $factura->xml_documento);
        } catch (Throwable $e) {
            Log::warning("[M6_Pagos] Hacienda no recibió la factura {$factura->id_factura_electronica}: {$e->getMessage()}");

            return;
        }

        $estado = match ($respuesta) {
            HaciendaInterface::ACEPTADA => EstadoFacturaHacienda::ACEPTADA,
            HaciendaInterface::RECHAZADA => EstadoFacturaHacienda::RECHAZADA,
            default => EstadoFacturaHacienda::ENVIADA,
        };

        $factura->forceFill(['id_estado_factura_hacienda' => EstadoFacturaHacienda::idDe($estado)])->save();
        $factura->load('estado');
    }

    private function facturaDe(int $idSubpedido): ?FacturaElectronica
    {
        return FacturaElectronica::query()->with(['estado', 'pago'])->where('id_subpedido', $idSubpedido)->first();
    }

    private function pagoConfirmadoDe(int $idPedido): ?Pago
    {
        return Pago::query()
            ->with('estado')
            ->where('id_pedido', $idPedido)
            ->where('id_estado_pago', EstadoPago::idDe(EstadoPago::CONFIRMADO))
            ->latest('id_pago')
            ->first();
    }
}
