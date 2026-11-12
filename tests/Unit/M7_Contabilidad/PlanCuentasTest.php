<?php

namespace Tests\Unit\M7_Contabilidad;

use App\Models\M7_Contabilidad\CuentaContable;
use App\Services\M7_Contabilidad\PlanCuentasService;
use Database\Seeders\M7_PlanCuentasSeeder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pruebas unitarias del plan de cuentas (6.1). No tocan la base de datos.
 */
class PlanCuentasTest extends TestCase
{
    private PlanCuentasService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = new PlanCuentasService;
    }

    private function padre(string $tipo = 'ACTIVO', int $nivel = 1, int $comercio = 1): CuentaContable
    {
        $cuenta = new CuentaContable([
            'id_comercio' => $comercio,
            'codigo' => '1',
            'nombre' => 'Activo',
            'tipo' => $tipo,
            'nivel' => $nivel,
        ]);
        $cuenta->forceFill(['id_cuenta_contable' => 10]);

        return $cuenta;
    }

    public static function tiposYNaturaleza(): array
    {
        return [
            ['ACTIVO', 'DEUDORA'],
            ['GASTO', 'DEUDORA'],
            ['PASIVO', 'ACREEDORA'],
            ['PATRIMONIO', 'ACREEDORA'],
            ['INGRESO', 'ACREEDORA'],
        ];
    }

    #[DataProvider('tiposYNaturaleza')]
    public function test_naturaleza_se_deriva_del_tipo(string $tipo, string $esperada): void
    {
        $this->assertSame($esperada, CuentaContable::naturalezaDeTipo($tipo));
    }

    public function test_cuenta_raiz_tiene_nivel_uno_y_sin_padre(): void
    {
        $datos = $this->servicio->validarDatos(1, ['codigo' => '1', 'nombre' => 'Activo', 'tipo' => 'activo']);

        $this->assertSame(1, $datos['nivel']);
        $this->assertNull($datos['id_cuenta_padre']);
        $this->assertSame('ACTIVO', $datos['tipo']);
    }

    public function test_subcuenta_hereda_nivel_del_padre_mas_uno(): void
    {
        $datos = $this->servicio->validarDatos(
            1,
            ['codigo' => '1.1', 'nombre' => 'Activo corriente', 'tipo' => 'ACTIVO'],
            $this->padre('ACTIVO', 1)
        );

        $this->assertSame(2, $datos['nivel']);
        $this->assertSame(10, $datos['id_cuenta_padre']);
    }

    public function test_rechaza_tipo_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->servicio->validarDatos(1, ['codigo' => '9', 'nombre' => 'X', 'tipo' => 'OTRO']);
    }

    public function test_rechaza_codigo_vacio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->servicio->validarDatos(1, ['codigo' => '  ', 'nombre' => 'X', 'tipo' => 'ACTIVO']);
    }

    public function test_rechaza_subcuenta_de_tipo_distinto_al_padre(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->servicio->validarDatos(
            1,
            ['codigo' => '1.1', 'nombre' => 'X', 'tipo' => 'PASIVO'],
            $this->padre('ACTIVO')
        );
    }

    public function test_rechaza_padre_de_otro_comercio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->servicio->validarDatos(
            1,
            ['codigo' => '1.1', 'nombre' => 'X', 'tipo' => 'ACTIVO'],
            $this->padre('ACTIVO', 1, 2)
        );
    }

    public function test_plan_base_tiene_codigos_unicos_y_tipos_validos(): void
    {
        $codigos = array_column(M7_PlanCuentasSeeder::PLAN_BASE, 0);

        $this->assertCount(count($codigos), array_unique($codigos));

        foreach (M7_PlanCuentasSeeder::PLAN_BASE as [, , $tipo]) {
            $this->assertContains($tipo, CuentaContable::TIPOS);
        }
    }

    public function test_plan_base_declara_padres_antes_que_hijos(): void
    {
        $vistos = [];

        foreach (M7_PlanCuentasSeeder::PLAN_BASE as [$codigo, , , $padre]) {
            if ($padre !== null) {
                $this->assertContains($padre, $vistos, "El padre {$padre} de {$codigo} debe declararse antes.");
            }
            $vistos[] = $codigo;
        }
    }

    public function test_plan_base_incluye_cuentas_que_usan_los_asientos(): void
    {
        $nombres = array_column(M7_PlanCuentasSeeder::PLAN_BASE, 1);

        foreach (['Caja y bancos', 'Cuentas por cobrar', 'Ventas', 'IVA por pagar', 'Costo de ventas', 'Inventarios'] as $requerida) {
            $this->assertContains($requerida, $nombres);
        }
    }
}
