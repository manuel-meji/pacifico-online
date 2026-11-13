<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\M7_Contabilidad\ComisionPendienteRepository;
use App\Services\M7_Contabilidad\ProcesarComisionPendienteService;
use Illuminate\Console\Command;
use Throwable;

final class ReintentarComisionesM7 extends Command
{
    protected $signature = 'm7:reintentar-comisiones';

    protected $description =
        'Reintenta obtener desde M2 las comisiones pendientes de M7';

    public function __construct(
        private readonly ComisionPendienteRepository $pendientes,
        private readonly ProcesarComisionPendienteService $procesador,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $pendientes = $this->pendientes->pendientes();

        if ($pendientes === []) {
            $this->info('No existen comisiones pendientes.');

            return self::SUCCESS;
        }

        $disponibles = 0;
        $aunPendientes = 0;
        $errores = 0;

        foreach ($pendientes as $pendiente) {
            try {
                $resultado = $this->procesador->intentar(
                    (int) $pendiente->id_subpedido
                );

                if ($resultado) {
                    $disponibles++;

                    $this->info(
                        "Subpedido {$pendiente->id_subpedido}: comisión disponible."
                    );

                    continue;
                }

                $aunPendientes++;

                $this->warn(
                    "Subpedido {$pendiente->id_subpedido}: comisión todavía pendiente."
                );
            } catch (Throwable $error) {
                $errores++;

                $this->error(
                    "Subpedido {$pendiente->id_subpedido}: {$error->getMessage()}"
                );
            }
        }

        $this->newLine();

        $this->info(
            "Disponibles: {$disponibles} | ".
            "Pendientes: {$aunPendientes} | ".
            "Errores: {$errores}"
        );

        return $errores > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
