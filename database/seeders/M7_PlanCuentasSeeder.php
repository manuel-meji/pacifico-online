<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\M7_Contabilidad\PlanCuentasBaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class M7_PlanCuentasSeeder extends Seeder
{
    /**
     * Comercios oficiales del escenario de demostración de M2.
     *
     * Se identifican por cédula jurídica y no por id_comercio,
     * porque el identificador numérico puede cambiar después
     * de reinicios o recargas de la base compartida.
     */
    private const COMERCIOS_DEMOSTRACION = [
        '3-101-100001',
        '3-101-100002',
        '3-101-100003',
        '3-101-100004',
        '3-101-100005',
    ];

    /** @see PlanCuentasBaseService::PLAN_BASE */
    public const PLAN_BASE = PlanCuentasBaseService::PLAN_BASE;

    public function run(): void
    {
        $comercios = DB::table('comercios')
            ->select([
                'id_comercio',
                'cedula_juridica',
                'nombre_comercial',
                'estado',
            ])
            ->whereIn(
                'cedula_juridica',
                self::COMERCIOS_DEMOSTRACION
            )
            ->get();

        $this->validarComercios($comercios);

        foreach ($comercios as $comercio) {
            $this->sembrarComercio(
                (int) $comercio->id_comercio
            );

            $this->command?->info(
                sprintf(
                    'M7: plan de cuentas cargado para %s (%s), id_comercio=%d.',
                    $comercio->nombre_comercial,
                    $comercio->cedula_juridica,
                    $comercio->id_comercio
                )
            );
        }
    }

    private function validarComercios($comercios): void
    {
        $porCedula = $comercios->keyBy('cedula_juridica');

        foreach (self::COMERCIOS_DEMOSTRACION as $cedula) {
            if (! $porCedula->has($cedula)) {
                throw new RuntimeException(
                    sprintf(
                        'No se encontró el comercio de demostración con cédula jurídica %s.',
                        $cedula
                    )
                );
            }

            $comercio = $porCedula->get($cedula);

            if ($comercio->estado !== 'Activo') {
                throw new RuntimeException(
                    sprintf(
                        'El comercio %s (%s) no está en estado Activo.',
                        $comercio->nombre_comercial,
                        $cedula
                    )
                );
            }
        }
    }

    public function sembrarComercio(int $idComercio): void
    {
        app(PlanCuentasBaseService::class)->crearParaComercio($idComercio, 'seeder');
    }
}
