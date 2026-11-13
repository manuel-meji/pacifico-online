<?php

declare(strict_types=1);

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class RegistroContablePagoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            DB::getDriverName() !== 'sqlite'
            || DB::connection()->getDatabaseName() !== ':memory:'
        ) {
            throw new LogicException(
                'Estas pruebas requieren SQLite en memoria.'
            );
        }

        Schema::create('asientos_contables', function (Blueprint $tabla): void {
            $tabla->id('id_asiento');
            $tabla->unsignedBigInteger('id_comercio');
            $tabla->unsignedBigInteger('id_pago')->nullable();
            $tabla->unsignedBigInteger('id_subpedido')->nullable();
            $tabla->uuid('evento_id');
            $tabla->string('tipo_evento');
            $tabla->string('tipo');
            $tabla->timestamp('fecha');
            $tabla->string('estado');
            $tabla->timestamps();
        });
    }

    public function test_indica_que_un_pago_tiene_registro_contable(): void
    {
        $idAsiento = DB::table('asientos_contables')->insertGetId([
            'id_comercio' => 1,
            'id_pago' => 50,
            'evento_id' => (string) Str::uuid(),
            'tipo_evento' => 'PagoConfirmado',
            'tipo' => 'PAGO',
            'fecha' => '2026-11-10 12:00:00',
            'estado' => 'REGISTRADO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultado = app(ContabilidadServiceInterface::class)
            ->consultarRegistroContablePago(50);

        $this->assertTrue($resultado['registrado']);
        $this->assertSame(50, $resultado['id_pago']);
        $this->assertSame($idAsiento, $resultado['asiento_id']);
        $this->assertNotNull($resultado['fecha_registro']);
    }

    public function test_indica_que_un_pago_no_tiene_registro_contable(): void
    {
        $resultado = app(ContabilidadServiceInterface::class)
            ->consultarRegistroContablePago(999);

        $this->assertFalse($resultado['registrado']);
        $this->assertSame(999, $resultado['id_pago']);
        $this->assertNull($resultado['asiento_id']);
        $this->assertNull($resultado['fecha_registro']);
        $this->assertSame([], $resultado['subpedidos_registrados']);
    }

    public function test_solo_incluye_subpedidos_con_cobro_registrado_del_pago(): void
    {
        foreach ([
            [50, 1002, 'COBRO', 'REGISTRADO'],
            [50, 1001, 'COBRO', 'REGISTRADO'],
            [50, 1001, 'COBRO', 'REGISTRADO'],
            [50, 1003, 'COBRO', 'ANULADO'],
            [50, 1004, 'REEMBOLSO', 'REGISTRADO'],
            [51, 1005, 'COBRO', 'REGISTRADO'],
        ] as [$pago, $subpedido, $tipo, $estado]) {
            DB::table('asientos_contables')->insert([
                'id_comercio' => 1, 'id_pago' => $pago, 'id_subpedido' => $subpedido,
                'evento_id' => (string) Str::uuid(), 'tipo_evento' => 'PRUEBA', 'tipo' => $tipo,
                'fecha' => '2026-11-10 12:00:00', 'estado' => $estado, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $resultado = app(ContabilidadServiceInterface::class)->consultarRegistroContablePago(50);

        $this->assertTrue($resultado['registrado']);
        $this->assertSame([1001, 1002], $resultado['subpedidos_registrados']);
        $this->assertSame([], app(ContabilidadServiceInterface::class)->consultarRegistroContablePago(0)['subpedidos_registrados']);
    }
}
