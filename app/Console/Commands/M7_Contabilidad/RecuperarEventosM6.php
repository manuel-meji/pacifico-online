<?php

declare(strict_types=1);

namespace App\Console\Commands\M7_Contabilidad;

use App\Services\M7_Contabilidad\RecuperarEventosM6Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecuperarEventosM6 extends Command
{
    protected $signature = 'm7:recuperar-eventos-m6 {desde? : Fecha inicial AAAA-MM-DD} {hasta? : Fecha final AAAA-MM-DD} {--comercio= : Identificador del comercio}';

    protected $description = 'Recupera facturas y reembolsos de M6; sin fechas procesa el día anterior.';

    public function handle(RecuperarEventosM6Service $recuperacion): int
    {
        $comercio = $this->option('comercio');
        if ($comercio !== null && (! is_string($comercio) || ! preg_match('/^[1-9][0-9]*$/', $comercio) || (string) (int) $comercio !== $comercio)) {
            $this->error('Indique un identificador de comercio válido con --comercio.');

            return self::FAILURE;
        }

        $desde = $this->argument('desde');
        $hasta = $this->argument('hasta');
        if (($desde === null) !== ($hasta === null)) {
            $this->error('Indique ambas fechas o ninguna.');

            return self::FAILURE;
        }
        if ($desde === null) {
            $desde = now()->subDay()->toDateString();
            $hasta = $desde;
        }

        try {
            $resultado = $recuperacion->recuperar($desde, $hasta, $comercio !== null ? (int) $comercio : null);
        } catch (Throwable $error) {
            $this->error('No se pudieron consultar los eventos de M6: '.$error->getMessage());
            Log::error('M7: falló la recuperación de eventos de M6.', ['desde' => $desde, 'hasta' => $hasta, 'comercio' => $comercio, 'exception' => $error]);

            return self::FAILURE;
        }

        $this->info("Facturas procesadas: {$resultado['facturas']}; reembolsos procesados: {$resultado['reembolsos']}.");
        foreach ($resultado['errores'] as $error) {
            $this->error($error);
            Log::error('M7: falló una operación durante la recuperación de eventos de M6.', ['desde' => $desde, 'hasta' => $hasta, 'comercio' => $comercio, 'detalle' => $error]);
        }

        return $resultado['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
