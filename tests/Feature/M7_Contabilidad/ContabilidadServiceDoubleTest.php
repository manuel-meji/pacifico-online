<?php

namespace Tests\Feature\M7_Contabilidad;

use App\Contracts\M7_Contabilidad\ContabilidadServiceInterface;
use Tests\TestCase;

class ContabilidadServiceDoubleTest extends TestCase
{
    public function test_m7_puede_sustituir_el_servicio_por_un_stub(): void
    {
        $stub = $this->createStub(ContabilidadServiceInterface::class);

        $stub->method('registrarAsientoDespacho')
            ->willReturn(true);

        $this->app->instance(
            ContabilidadServiceInterface::class,
            $stub
        );

        $servicio = app(ContabilidadServiceInterface::class);

        $this->assertTrue(
            $servicio->registrarAsientoDespacho(
                100,
                15000.00,
                9000.00
            )
        );

        $this->assertSame(
            $stub,
            app(ContabilidadServiceInterface::class)
        );
    }
}
