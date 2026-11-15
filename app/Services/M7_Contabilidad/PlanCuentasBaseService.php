<?php

declare(strict_types=1);

namespace App\Services\M7_Contabilidad;

use App\Contracts\Nucleo\NucleoServiceInterface;
use App\Models\M7_Contabilidad\CentroCosto;
use App\Models\M7_Contabilidad\CuentaContable;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Support\Facades\DB;

/**
 * Crea el plan de cuentas base y el centro de costo general de un comercio.
 * Solo agrega lo que falta: no renombra ni cambia el tipo de cuentas existentes.
 */
final class PlanCuentasBaseService
{
    public const CODIGO_CENTRO_GENERAL = 'GEN';

    /**
     * [codigo, nombre, tipo, codigo padre]
     */
    public const PLAN_BASE = [
        ['1', 'Activo', 'ACTIVO', null],
        ['1.1', 'Activo corriente', 'ACTIVO', '1'],
        ['1.1.01', 'Caja y bancos', 'ACTIVO', '1.1'],
        ['1.1.02', 'Cuentas por cobrar', 'ACTIVO', '1.1'],
        ['1.1.03', 'Inventarios', 'ACTIVO', '1.1'],
        ['1.1.04', 'Fondos por cobrar a plataforma', 'ACTIVO', '1.1'],

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

    public function __construct(
        private readonly ContextoComercio $contexto,
        private readonly NucleoServiceInterface $nucleo,
    ) {}

    /**
     * @return array{cuentas_creadas: int, centro_creado: bool}
     */
    public function crearParaComercio(int $idComercio, string $origen): array
    {
        return $this->contexto->ejecutarComoSistema(fn (): array => DB::transaction(function () use ($idComercio, $origen): array {
            $porCodigo = [];
            $creadas = 0;

            foreach (self::PLAN_BASE as [$codigo, $nombre, $tipo, $codigoPadre]) {
                $padre = $codigoPadre !== null ? $porCodigo[$codigoPadre] : null;
                $cuenta = CuentaContable::query()
                    ->where('id_comercio', $idComercio)
                    ->where('codigo', $codigo)
                    ->lockForUpdate()
                    ->first();

                if ($cuenta === null) {
                    $cuenta = CuentaContable::create([
                        'id_comercio' => $idComercio,
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                        'tipo' => $tipo,
                        'nivel' => $padre ? $padre->nivel + 1 : 1,
                        'id_cuenta_padre' => $padre?->getKey(),
                        'activa' => true,
                    ]);
                    $creadas++;
                } elseif ($cuenta->id_cuenta_padre === null && $padre !== null && $cuenta->tipo === $padre->tipo) {
                    $cuenta->update(['id_cuenta_padre' => $padre->getKey(), 'nivel' => $padre->nivel + 1]);
                }

                $porCodigo[$codigo] = $cuenta;
            }

            $centro = CentroCosto::firstOrCreate(
                ['id_comercio' => $idComercio, 'codigo' => self::CODIGO_CENTRO_GENERAL],
                ['nombre' => 'General', 'activo' => true],
            );

            if ($creadas > 0 || $centro->wasRecentlyCreated) {
                $this->nucleo->registrarAuditoria('plan_cuentas_base_creado', 'comercio', $idComercio, [
                    'modulo' => 'M7',
                    'origen' => $origen,
                    'cuentas_creadas' => $creadas,
                    'centro_creado' => $centro->wasRecentlyCreated,
                ]);
            }

            return ['cuentas_creadas' => $creadas, 'centro_creado' => $centro->wasRecentlyCreated];
        }));
    }
}
