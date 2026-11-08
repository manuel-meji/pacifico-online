<?php

namespace Database\Seeders;

use App\Models\M7_Contabilidad\CentroCosto;
use App\Models\M7_Contabilidad\CuentaContable;
use App\Services\Nucleo\ContextoComercio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el plan de cuentas base del M7 por comercio.
 *
 * Uso (NUNCA el db:seed global, la base de Supabase es compartida):
 *   php artisan db:seed --class=M7_PlanCuentasSeeder
 *
 * Comercios a sembrar: variable M7_SEED_COMERCIOS en .env (ej. "1,2"). Por defecto "1".
 * No lee tablas de M2 (frontera de datos). Es idempotente: se puede ejecutar varias veces.
 *
 * Corre sin contexto de usuario, por eso la siembra se ejecuta mediante
 * app(ContextoComercio::class)->ejecutarComoSistema() (RN-12 / ADR-006).
 * En modo sistema el Núcleo no asigna ni valida id_comercio, así que se pasa
 * explícito en las condiciones del updateOrCreate.
 */
class M7_PlanCuentasSeeder extends Seeder
{
    /** [codigo, nombre, tipo, codigo del padre]. Los padres van antes que sus hijos. */
    public const PLAN_BASE = [
        ['1',      'Activo',                    'ACTIVO',     null],
        ['1.1',    'Activo corriente',          'ACTIVO',     '1'],
        ['1.1.01', 'Caja y bancos',             'ACTIVO',     '1.1'],
        ['1.1.02', 'Cuentas por cobrar',        'ACTIVO',     '1.1'],
        ['1.1.03', 'Inventarios',               'ACTIVO',     '1.1'],
        ['2',      'Pasivo',                    'PASIVO',     null],
        ['2.1',    'Pasivo corriente',          'PASIVO',     '2'],
        ['2.1.01', 'Cuentas por pagar',         'PASIVO',     '2.1'],
        ['2.1.02', 'IVA por pagar',             'PASIVO',     '2.1'],
        ['3',      'Patrimonio',                'PATRIMONIO', null],
        ['3.1',    'Capital y resultados',      'PATRIMONIO', '3'],
        ['3.1.01', 'Capital social',            'PATRIMONIO', '3.1'],
        ['3.1.02', 'Utilidades acumuladas',     'PATRIMONIO', '3.1'],
        ['4',      'Ingresos',                  'INGRESO',    null],
        ['4.1',    'Ingresos operativos',       'INGRESO',    '4'],
        ['4.1.01', 'Ventas',                    'INGRESO',    '4.1'],
        ['4.1.02', 'Ingresos por envío',        'INGRESO',    '4.1'],
        ['5',      'Costos y gastos',           'GASTO',      null],
        ['5.1',    'Costos',                    'GASTO',      '5'],
        ['5.1.01', 'Costo de ventas',           'GASTO',      '5.1'],
        ['5.2',    'Gastos operativos',         'GASTO',      '5'],
        ['5.2.01', 'Comisiones de plataforma',  'GASTO',      '5.2'],
    ];

    public function run(): void
    {
        $ids = array_filter(array_map('intval', explode(',', (string) env('M7_SEED_COMERCIOS', '1'))));

        foreach ($ids as $idComercio) {
            $this->sembrarComercio($idComercio);
        }
    }

    public function sembrarComercio(int $idComercio): void
    {
        app(ContextoComercio::class)->ejecutarComoSistema(function () use ($idComercio): void {
            DB::transaction(function () use ($idComercio): void {
                $porCodigo = [];

                foreach (self::PLAN_BASE as [$codigo, $nombre, $tipo, $codigoPadre]) {
                    $padre = $codigoPadre !== null ? $porCodigo[$codigoPadre] : null;

                    $porCodigo[$codigo] = CuentaContable::updateOrCreate(
                        ['id_comercio' => $idComercio, 'codigo' => $codigo],
                        [
                            'nombre' => $nombre,
                            'tipo' => $tipo,
                            'nivel' => $padre ? $padre->nivel + 1 : 1,
                            'id_cuenta_padre' => $padre?->getKey(),
                            'activa' => true,
                        ]
                    );
                }

                CentroCosto::updateOrCreate(
                    ['id_comercio' => $idComercio, 'codigo' => 'GEN'],
                    ['nombre' => 'General', 'activo' => true]
                );
            });
        });
    }
}