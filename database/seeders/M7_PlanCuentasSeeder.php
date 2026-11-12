<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\M7_Contabilidad\CentroCosto;
use App\Models\M7_Contabilidad\CuentaContable;
use App\Services\Nucleo\ContextoComercio;
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

    /**
     * [codigo, nombre, tipo, codigo padre]
     */
    public const PLAN_BASE = [
        ['1', 'Activo', 'ACTIVO', null],
        ['1.1', 'Activo corriente', 'ACTIVO', '1'],
        ['1.1.01', 'Caja y bancos', 'ACTIVO', '1.1'],
        ['1.1.02', 'Cuentas por cobrar', 'ACTIVO', '1.1'],
        ['1.1.03', 'Inventarios', 'ACTIVO', '1.1'],

        ['2', 'Pasivo', 'PASIVO', null],
        ['2.1', 'Pasivo corriente', 'PASIVO', '2'],
        ['2.1.01', 'Cuentas por pagar', 'PASIVO', '2.1'],
        ['2.1.02', 'IVA por pagar', 'PASIVO', '2.1'],

        ['3', 'Patrimonio', 'PATRIMONIO', null],
        ['3.1', 'Capital y resultados', 'PATRIMONIO', '3'],
        ['3.1.01', 'Capital social', 'PATRIMONIO', '3.1'],
        ['3.1.02', 'Utilidades acumuladas', 'PATRIMONIO', '3.1'],

        ['4', 'Ingresos', 'INGRESO', null],
        ['4.1', 'Ingresos operativos', 'INGRESO', '4'],
        ['4.1.01', 'Ventas', 'INGRESO', '4.1'],
        ['4.1.02', 'Ingresos por envío', 'INGRESO', '4.1'],

        ['5', 'Costos y gastos', 'GASTO', null],
        ['5.1', 'Costos', 'GASTO', '5'],
        ['5.1.01', 'Costo de ventas', 'GASTO', '5.1'],
        ['5.2', 'Gastos operativos', 'GASTO', '5'],
        ['5.2.01', 'Comisiones de plataforma', 'GASTO', '5.2'],
    ];

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
        app(ContextoComercio::class)
            ->ejecutarComoSistema(
                function () use ($idComercio): void {
                    DB::transaction(
                        function () use ($idComercio): void {
                            $porCodigo = [];

                            foreach (
                                self::PLAN_BASE
                                as [$codigo, $nombre, $tipo, $codigoPadre]
                            ) {
                                $padre = $codigoPadre !== null
                                    ? $porCodigo[$codigoPadre]
                                    : null;

                                $porCodigo[$codigo] =
                                    CuentaContable::updateOrCreate(
                                        [
                                            'id_comercio' => $idComercio,
                                            'codigo' => $codigo,
                                        ],
                                        [
                                            'nombre' => $nombre,
                                            'tipo' => $tipo,
                                            'nivel' => $padre
                                                ? $padre->nivel + 1
                                                : 1,
                                            'id_cuenta_padre' =>
                                                $padre?->getKey(),
                                            'activa' => true,
                                        ]
                                    );
                            }

                            CentroCosto::updateOrCreate(
                                [
                                    'id_comercio' => $idComercio,
                                    'codigo' => 'GEN',
                                ],
                                [
                                    'nombre' => 'General',
                                    'activo' => true,
                                ]
                            );
                        }
                    );
                }
            );
    }
}