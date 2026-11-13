<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Contracts\M6_Pagos\ConsultaEventosPagosInterface;
use App\Events\M6_Pagos\ReembolsoProcesado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class RecuperarEventosM6Service
{
    public function __construct(
        private readonly ConsultaEventosPagosInterface $consulta,
        private readonly BandejaEventosM6Service $bandeja,
    ) {}

    /** @return array{pagos:int, facturas:int, reembolsos:int, pendientes:int, errores:list<string>} */
    public function recuperar(string $desde, string $hasta, ?int $comercioId = null): array
    {
        if (($comercioId !== null && $comercioId <= 0) || ! $this->fechaValida($desde) || ! $this->fechaValida($hasta) || $desde > $hasta) {
            throw new InvalidArgumentException('Indique un comercio válido y un período válido en formato AAAA-MM-DD.');
        }
        $resultado = ['pagos' => 0, 'facturas' => 0, 'reembolsos' => 0, 'pendientes' => 0, 'errores' => []];
        foreach ([
            ['PagoConfirmado', 'pagos', 'listarPagosConfirmados'],
            ['FacturaGenerada', 'facturas', 'listarFacturasGeneradas'],
            ['ReembolsoProcesado', 'reembolsos', 'listarReembolsosProcesados'],
        ] as [$tipo, $contador, $metodo]) {
            try {
                $datosEventos = $this->consulta->{$metodo}($desde, $hasta, $comercioId);
            } catch (Throwable $error) {
                $resultado['errores'][] = "{$tipo}: ".$error->getMessage();

                continue;
            }
            foreach ($datosEventos as $datos) {
                try {
                    if ($tipo !== 'PagoConfirmado' && $comercioId !== null && ($datos['id_comercio'] ?? null) !== $comercioId) {
                        throw new InvalidArgumentException('El evento de M6 pertenece a otro comercio.');
                    }
                    $evento = $this->bandeja->desdePayload($tipo, $datos);
                    $procesado = $evento instanceof ReembolsoProcesado
                        ? $this->procesarReembolso($evento) : $this->bandeja->recibir($evento);
                    if ($procesado) {
                        $resultado[$contador]++;
                    }
                } catch (Throwable $error) {
                    $resultado['errores'][] = "{$tipo}: ".$error->getMessage();
                }
            }
        }
        $reintento = $this->bandeja->reintentarPendientes(comercioId: $comercioId);
        $resultado['pendientes'] = $reintento['pendientes'];
        if ($reintento['pendientes'] > 0) {
            $resultado['errores'][] = "Quedan {$reintento['pendientes']} eventos pendientes de recuperación o revisión.";
        }

        return $resultado;
    }

    public function procesarReembolso(ReembolsoProcesado $reembolso): bool
    {
        if ($this->bandeja->recibir($reembolso)) {
            return true;
        }
        $hasta = Carbon::parse($reembolso->fecha)->setTimezone(config('app.timezone'))->toDateString();
        if (! DB::table('pagos_contables_m6')->where('id_pago', $reembolso->idPago)->exists()) {
            foreach ($this->consulta->listarPagosConfirmados('1900-01-01', $hasta) as $datos) {
                if (($datos['id_pago'] ?? null) === $reembolso->idPago && ($datos['id_pedido'] ?? null) === $reembolso->idPedido) {
                    $this->bandeja->recibir($this->bandeja->desdePayload('PagoConfirmado', $datos));
                }
            }
        }
        if (! DB::table('registros_financieros_m6')->where('tipo', 'FACTURA')
            ->where('id_pago', $reembolso->idPago)->where('id_subpedido', $reembolso->idSubpedido)->exists()) {
            foreach ($this->consulta->listarFacturasGeneradas('1900-01-01', $hasta, $reembolso->idComercio) as $datos) {
                if (($datos['id_pago'] ?? null) === $reembolso->idPago && ($datos['id_subpedido'] ?? null) === $reembolso->idSubpedido
                    && ($datos['id_comercio'] ?? null) === $reembolso->idComercio) {
                    $this->bandeja->recibir($this->bandeja->desdePayload('FacturaGenerada', $datos));
                }
            }
        }

        return $this->bandeja->recibir($reembolso);
    }

    private function fechaValida(string $fecha): bool
    {
        $fechaParseada = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $fechaParseada !== false && $fechaParseada->format('Y-m-d') === $fecha;
    }
}
