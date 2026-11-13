<?php

declare(strict_types=1);

namespace App\Console\Commands\M7_Contabilidad;

use App\Services\M7_Contabilidad\RecuperarEventosM6Service;
use Illuminate\Console\Command;
use Throwable;

final class RecuperarEventosM6 extends Command
{
    protected $signature = 'm7:recuperar-eventos-m6 {desde : Fecha inicial AAAA-MM-DD} {hasta : Fecha final AAAA-MM-DD} {--comercio= : Identificador del comercio}';

    protected $description = 'Recupera facturas y reembolsos de M6 para un comercio.';

    public function handle(RecuperarEventosM6Service $recuperacion): int
    {
        $comercio = $this->option('comercio');
        if (! is_string($comercio) || ! preg_match('/^[1-9][0-9]*$/', $comercio) || (string) (int) $comercio !== $comercio) {
            $this->error('Indique un identificador de comercio válido con --comercio.');

            return self::FAILURE;
        }

        try {
            $resultado = $recuperacion->recuperar((string) $this->argument('desde'), (string) $this->argument('hasta'), (int) $comercio);
        } catch (Throwable $error) {
            $this->error('No se pudieron consultar los eventos de M6: '.$error->getMessage());

            return self::FAILURE;
        }

        $this->info("Facturas procesadas: {$resultado['facturas']}; reembolsos procesados: {$resultado['reembolsos']}.");
        foreach ($resultado['errores'] as $error) {
            $this->error($error);
        }

        return $resultado['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
