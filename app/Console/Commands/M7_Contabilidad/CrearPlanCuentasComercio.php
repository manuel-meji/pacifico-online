<?php

declare(strict_types=1);

namespace App\Console\Commands\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\DirectorioComerciosM7GatewayInterface;
use App\Services\M7_Contabilidad\PlanCuentasBaseService;
use Illuminate\Console\Command;
use Throwable;

final class CrearPlanCuentasComercio extends Command
{
    protected $signature = 'm7:crear-plan-cuentas {id_comercio* : Uno o varios comercios habilitados en M2}';

    protected $description = 'Crea las cuentas base y el centro de costo general que le falten a un comercio';

    public function handle(PlanCuentasBaseService $planBase, DirectorioComerciosM7GatewayInterface $directorio): int
    {
        $ids = array_values(array_unique(array_map('intval', (array) $this->argument('id_comercio'))));
        if (in_array(0, $ids, true) || array_filter($ids, fn (int $id): bool => $id < 0) !== []) {
            $this->error('Los identificadores de comercio deben ser enteros positivos.');

            return self::INVALID;
        }

        $habilitados = $directorio->obtenerNombres($ids);
        $fallos = 0;

        foreach ($ids as $id) {
            if (! isset($habilitados[$id])) {
                $this->error("Comercio {$id}: M2 no lo reporta como habilitado; no se crea el plan.");
                $fallos++;

                continue;
            }

            try {
                $resultado = $planBase->crearParaComercio($id, 'comando');
                $centro = $resultado['centro_creado'] ? 'creado' : 'ya existía';
                $this->info("Comercio {$id} ({$habilitados[$id]}): {$resultado['cuentas_creadas']} cuentas creadas; centro GEN {$centro}.");
            } catch (Throwable $error) {
                $this->error("Comercio {$id}: {$error->getMessage()}");
                $fallos++;
            }
        }

        return $fallos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
