<?php

declare(strict_types=1);

namespace App\Console\Commands\M7_Contabilidad;

use App\Services\M7_Contabilidad\CuentaPagarPendienteRepository;
use App\Contracts\M7_Contabilidad\ProcesadorCuentaPagarComercioInterface;
use Illuminate\Console\Command;
use Throwable;

final class ReintentarCuentasPagarM7 extends Command
{
    protected $signature = 'm7:reintentar-cuentas-pagar';

    protected $description =
        'Reintenta procesar cuentas por pagar pendientes de comercios';

    public function __construct(
        private readonly CuentaPagarPendienteRepository $pendientes,
        private readonly ProcesadorCuentaPagarComercioInterface $procesador,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $pendientes = $this->pendientes->pendientes();

        if ($pendientes === []) {
            $this->info('No existen cuentas por pagar pendientes.');

            return self::SUCCESS;
        }

        $procesadas = 0;
        $aunPendientes = 0;
        $errores = 0;

        foreach ($pendientes as $pendiente) {
            try {
                $resultado = $this->procesador->procesar(
                    eventoId: $pendiente->evento_id,
                    pedidoId: (int) $pendiente->id_pedido,
                    subpedidoId: (int) $pendiente->id_subpedido,
                    comercioId: (int) $pendiente->id_comercio,
                    fecha: $pendiente->fecha_evento,
                );

                if ($resultado) {
                    $this->pendientes->marcarProcesada(
                        (int) $pendiente->id_subpedido
                    );

                    $procesadas++;

                    continue;
                }

                $this->pendientes->registrarIntento(
                    (int) $pendiente->id_subpedido,
                    'Dependencias contables todavía no disponibles.'
                );

                $aunPendientes++;
            } catch (Throwable $error) {
                $this->pendientes->registrarIntento(
                    (int) $pendiente->id_subpedido,
                    $error->getMessage()
                );

                $errores++;

                $this->error(
                    "Subpedido {$pendiente->id_subpedido}: {$error->getMessage()}"
                );
            }
        }

        $this->info(
            "Procesadas: {$procesadas} | ".
            "Pendientes: {$aunPendientes} | ".
            "Errores: {$errores}"
        );

        return $errores > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}