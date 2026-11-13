<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Contracts\M6_Pagos\ConsultaEventosPagosInterface;
use App\Events\M6_Pagos\FacturaGenerada;
use App\Events\M6_Pagos\ReembolsoProcesado;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Throwable;

final class RecuperarEventosM6Service
{
    public function __construct(
        private readonly ConsultaEventosPagosInterface $consulta,
        private readonly IntegracionFinancieraM6Service $integracion,
        private readonly ContextoComercio $contexto,
    ) {}

    /**
     * @return array{facturas:int, reembolsos:int, errores:list<string>}
     */
    public function recuperar(string $desde, string $hasta, int $comercioId): array
    {
        if ($comercioId <= 0 || ! $this->fechaValida($desde) || ! $this->fechaValida($hasta) || $desde > $hasta) {
            throw new InvalidArgumentException('Indique un comercio válido y un período válido en formato AAAA-MM-DD.');
        }

        return $this->contexto->ejecutarComoSistema(function () use ($desde, $hasta, $comercioId): array {
            $resultado = ['facturas' => 0, 'reembolsos' => 0, 'errores' => []];
            $facturas = $this->consulta->listarFacturasGeneradas($desde, $hasta, $comercioId);
            $reembolsos = $this->consulta->listarReembolsosProcesados($desde, $hasta, $comercioId);

            foreach ($facturas as $datos) {
                try {
                    $factura = $this->factura($datos, $comercioId);
                    $this->integracion->procesarFactura($factura);
                    $resultado['facturas']++;
                } catch (Throwable $error) {
                    $resultado['errores'][] = 'Factura '.($datos['id_factura'] ?? 'desconocida').': '.$error->getMessage();
                }
            }

            foreach ($reembolsos as $datos) {
                try {
                    $reembolso = $this->reembolso($datos, $comercioId);
                    $referencia = "factura:{$reembolso->idPago}:{$reembolso->idSubpedido}";
                    if (! DB::table('registros_financieros_m6')->where('referencia', $referencia)->exists()) {
                        $historicas = $this->consulta->listarFacturasGeneradas('1900-01-01', Carbon::parse($reembolso->fecha)->toDateString(), $comercioId);
                        $coincidentes = array_values(array_filter($historicas, fn (array $factura): bool => ($factura['id_pago'] ?? null) === $reembolso->idPago
                            && ($factura['id_subpedido'] ?? null) === $reembolso->idSubpedido
                            && ($factura['id_comercio'] ?? null) === $comercioId));
                        if (count($coincidentes) !== 1) {
                            throw new LogicException('No se encontró una factura única para el reembolso.');
                        }
                        $this->integracion->procesarFactura($this->factura($coincidentes[0], $comercioId));
                    }
                    $this->integracion->procesarReembolso($reembolso);
                    $resultado['reembolsos']++;
                } catch (Throwable $error) {
                    $resultado['errores'][] = 'Reembolso '.($datos['id_reembolso'] ?? 'desconocido').': '.$error->getMessage();
                }
            }

            return $resultado;
        });
    }

    private function fechaValida(string $fecha): bool
    {
        $fechaParseada = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $fechaParseada !== false && $fechaParseada->format('Y-m-d') === $fecha;
    }

    private function factura(array $datos, int $comercioId): FacturaGenerada
    {
        $this->exigirCampos($datos, ['id_evento', 'id_factura', 'id_pedido', 'id_subpedido', 'id_comercio', 'monto', 'xml', 'fecha', 'id_pago', 'subtotal', 'impuesto', 'envio'], $comercioId);

        return new FacturaGenerada(
            $datos['id_evento'], $datos['id_factura'], $datos['id_pedido'], $datos['id_subpedido'],
            $datos['id_comercio'], $datos['monto'], $datos['xml'], $datos['fecha'],
            $datos['id_pago'], $datos['subtotal'], $datos['impuesto'], $datos['envio'],
        );
    }

    private function reembolso(array $datos, int $comercioId): ReembolsoProcesado
    {
        $this->exigirCampos($datos, ['id_evento', 'id_pedido', 'id_subpedido', 'id_comercio', 'id_reembolso', 'monto', 'nota_credito_id', 'fecha', 'id_pago'], $comercioId);

        return new ReembolsoProcesado(
            $datos['id_evento'], $datos['id_pedido'], $datos['id_subpedido'], $datos['id_comercio'],
            $datos['id_reembolso'], $datos['monto'], $datos['nota_credito_id'], $datos['fecha'], $datos['id_pago'],
        );
    }

    /** @param list<string> $campos */
    private function exigirCampos(array $datos, array $campos, int $comercioId): void
    {
        foreach ($campos as $campo) {
            if (! array_key_exists($campo, $datos)) {
                throw new InvalidArgumentException("Falta el campo {$campo} en el evento de M6.");
            }
        }
        if ($datos['id_comercio'] !== $comercioId) {
            throw new LogicException('El evento de M6 pertenece a otro comercio.');
        }
    }
}
