<?php

declare(strict_types=1);

namespace App\Console\Commands\M7_Contabilidad;

use App\Services\M7_Contabilidad\PlanCuentasBaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ImputarCentroGeneral extends Command
{
    protected $signature = 'm7:imputar-centro-general
        {id_comercio?* : Comercios a procesar; si se omite, todos los que tienen GEN activo}
        {--simular : Solo cuenta las líneas que se imputarían, sin modificar datos}';

    protected $description = 'Asigna el centro de costo GEN a las líneas históricas de ingreso y gasto sin centro';

    public function handle(PlanCuentasBaseService $planBase): int
    {
        $ids = array_values(array_unique(array_map('intval', (array) $this->argument('id_comercio'))));
        if (array_filter($ids, fn (int $id): bool => $id <= 0) !== []) {
            $this->error('Los identificadores de comercio deben ser enteros positivos.');

            return self::INVALID;
        }

        if ($ids === []) {
            $ids = DB::table('centros_costo')
                ->where('codigo', PlanCuentasBaseService::CODIGO_CENTRO_GENERAL)
                ->where('activo', true)
                ->orderBy('id_comercio')
                ->pluck('id_comercio')
                ->map(fn (int|string $id): int => (int) $id)
                ->all();
        }

        $simular = (bool) $this->option('simular');
        $verbo = $simular ? 'se imputarían' : 'imputadas';
        $fallos = 0;
        $total = 0;

        foreach ($ids as $id) {
            try {
                $lineas = $planBase->imputarLineasSinCentro($id, $simular);
                $total += $lineas;
                $this->info("Comercio {$id}: {$lineas} líneas {$verbo} a GEN.");
            } catch (Throwable $error) {
                $this->error("Comercio {$id}: {$error->getMessage()}");
                $fallos++;
            }
        }

        $this->info("Total: {$total} líneas {$verbo}.");

        return $fallos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
